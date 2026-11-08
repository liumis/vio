<?php

namespace App\Support;

use App\Models\FineProcessLog;
use Throwable;

/**
 * Extract + rental lookup only (no SharePoint / move / email).
 */
final class IncomingFineLookup
{
    public function __construct(
        private readonly IncomingFineAttachmentTextExtractor $extractor = new IncomingFineAttachmentTextExtractor,
        private readonly IncomingFineReplyBuilder $replyBuilder = new IncomingFineReplyBuilder,
        private readonly WheelsysClient $wheelsys = new WheelsysClient,
    ) {
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{
     *     message: array<string, mixed>,
     *     lookup_status: 'found'|'not_found',
     *     lookup_detail: string,
     *     vehicle: string,
     *     reference: string,
     *     ticket_date: string,
     *     ticket_time: string,
     *     ticket_datetime: string,
     *     rental: array<string, mixed>|null,
     *     wheelsys: array<string, mixed>|null,
     *     draft: array<string, mixed>,
     *     log: FineProcessLog
     * }
     */
    public function lookup(array $message, ?int $userId = null): array
    {
        $extract = ['text' => '', 'files' => [], 'saved_dir' => null];
        try {
            if (! empty($message['has_attachments'])) {
                $extract = $this->extractor->extractDetailedFromMessage($message, persist: true);
            }
        } catch (Throwable $e) {
            // Keep going — subject/preview may still parse.
            $extract['text'] = '';
        }

        // JUDU / Unipark / Parkavimas mieste: Print email → PDF even when there are no attachments.
        try {
            $emailPrint = app(IncomingFineEmailPrintPdf::class)->ensureLocalPdf(
                $message,
                $extract['saved_dir'] ?? null,
            );
            if ($emailPrint !== null) {
                $extract['saved_dir'] = $emailPrint['saved_dir'];
            }
        } catch (Throwable) {
            // Non-fatal during Find driver.
        }

        $message['attachment_text'] = (string) ($extract['text'] ?? '');
        $message['attachment_extract'] = $extract;

        $subject = (string) ($message['subject'] ?? '');
        $haystack = trim((string) ($message['preview'] ?? '')."\n".$message['attachment_text']);
        $fields = [
            'reference' => $this->replyBuilder->extractReference($subject, $haystack),
            'vehicle' => $this->replyBuilder->extractVehicle($subject, $haystack),
            'ticket_date' => $this->replyBuilder->extractTicketDate($subject, $haystack),
            'ticket_time' => $this->replyBuilder->extractTicketTime($subject, $haystack),
            'ticket_datetime' => $this->replyBuilder->extractTicketDateTime($subject, $haystack),
        ];

        $rentals = [];
        $rental = null;
        $wheelsys = null;
        $lookupError = null;

        try {
            if ($fields['vehicle'] !== '' && $fields['ticket_date'] !== '') {
                $rentals = $this->wheelsys->findRentalsCoveringDate($fields['vehicle'], $fields['ticket_date']);
            } elseif ($fields['vehicle'] !== '') {
                $rentals = $this->wheelsys->searchRentalsByVehicle($fields['vehicle']);
            }

            if ($rentals !== []) {
                $rental = $rentals[0];
                $wheelsys = $this->wheelsys->rentalAsWheelsysDraft(
                    $rental,
                    $fields['ticket_date'] !== '' ? $fields['ticket_date'] : null,
                    $fields['ticket_datetime'] !== '' ? $fields['ticket_datetime'] : null,
                );
                if ($fields['reference'] !== '') {
                    $wheelsys['ticket_number'] = $fields['reference'];
                }
                try {
                    $wheelsys = $this->wheelsys->enrichDraftWithDriver($wheelsys);
                } catch (Throwable) {
                }
            }
        } catch (Throwable $e) {
            $lookupError = $e->getMessage();
        }

        $driverName = trim((string) ($wheelsys['driver'] ?? $rental['driver'] ?? $rental['corporate'] ?? ''));
        $found = $rental !== null && $driverName !== '';
        $detail = $rental !== null && $driverName === ''
            ? 'Rental found but driver missing'
            : ($found
                ? trim(($rental['agr_no'] ?? '').' · '.$driverName)
                : ($lookupError
                    ?: ($fields['vehicle'] === ''
                        ? 'Missing vehicle / plate'
                        : ($fields['ticket_date'] === ''
                            ? 'No covering rental (no ticket date)'
                            : 'No covering rental for plate/date'))));

        $draft = $this->replyBuilder->build($message, $wheelsys);

        $messageId = (string) ($message['id'] ?? '');
        $log = FineProcessLog::query()->firstOrNew(['graph_message_id' => $messageId]);
        $log->fill([
            'mailbox' => $log->mailbox ?: app(MicrosoftGraphInbox::class)->mailbox(),
            'from_email' => $message['from_email'] ?? $log->from_email,
            'subject' => $message['subject'] ?? $log->subject,
            'received_at' => filled($message['received_at'] ?? null)
                ? \Illuminate\Support\Carbon::parse((string) $message['received_at'])
                : $log->received_at,
            'authority_id' => $message['authority_id'] ?? $log->authority_id,
            'authority_name' => (($message['authority_name'] ?? '—') !== '—' ? $message['authority_name'] : $log->authority_name),
            'vehicle' => $fields['vehicle'] !== '' ? $fields['vehicle'] : null,
            'reference' => $fields['reference'] !== '' ? $fields['reference'] : null,
            'ticket_date' => $fields['ticket_date'] !== '' ? $fields['ticket_date'] : null,
            'ticket_time' => $fields['ticket_time'] !== '' ? $fields['ticket_time'] : null,
            'agr_no' => $rental['agr_no'] ?? null,
            'rental_id' => isset($rental['id']) ? (int) $rental['id'] : null,
            'driver_name' => $wheelsys['driver'] ?? null,
            'local_attachment_dir' => $extract['saved_dir'] ?? $log->local_attachment_dir,
            'status' => $found ? 'lookup_found' : 'lookup_missing',
            'wheels_export_status' => $found && $fields['vehicle'] !== ''
                ? ($log->wheels_export_status === FineProcessLog::WHEELS_EXPORT_EXPORTED
                    ? FineProcessLog::WHEELS_EXPORT_EXPORTED
                    : FineProcessLog::WHEELS_EXPORT_PENDING)
                : FineProcessLog::WHEELS_EXPORT_SKIPPED,
            'processed_by' => $userId ?? $log->processed_by,
            'processed_at' => $log->processed_at ?: now(),
            'error' => $lookupError,
            'workflow' => [
                'steps' => [
                    ['key' => 'lookup', 'ok' => $found, 'detail' => $detail],
                ],
                'fields' => $fields,
                'rental_count' => count($rentals),
            ],
        ])->save();

        if ($found) {
            try {
                $printer = app(IncomingFineEmailPrintPdf::class);
                if ($printer->shouldPrint($message)) {
                    $printer->ensureAndArchive($log->fresh(), $message);
                } else {
                    app(IncomingFineDocumentArchiver::class)->archive($log->fresh());
                }
            } catch (Throwable) {
                // Non-fatal.
            }
        }

        return [
            'message' => $message,
            'lookup_status' => $found ? 'found' : 'not_found',
            'lookup_detail' => $detail,
            'vehicle' => $fields['vehicle'],
            'reference' => $fields['reference'],
            'ticket_date' => $fields['ticket_date'],
            'ticket_time' => $fields['ticket_time'],
            'ticket_datetime' => $fields['ticket_datetime'],
            'rental' => $rental,
            'wheelsys' => $wheelsys,
            'draft' => $draft,
            'log' => $log,
            'rentals' => $rentals,
        ];
    }
}
