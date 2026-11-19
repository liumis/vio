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
        $subject = (string) ($message['subject'] ?? '');
        $preview = (string) ($message['preview'] ?? '');
        if (IncomingFineMailScanner::isNonFineNotice($subject, $preview)) {
            return $this->skipNonFine($message, $userId, 'Skipped — not a fine notice (annulment / payment / move-car)');
        }

        $extract = ['text' => '', 'files' => [], 'saved_dir' => null];
        try {
            if (! empty($message['has_attachments'])) {
                $extract = $this->extractor->extractDetailedFromMessage($message, persist: true);
            }
        } catch (Throwable $e) {
            // Keep going — subject/preview may still parse.
            $extract['text'] = '';
        }

        $bodyText = '';
        // JUDU / Unipark / Parkavimas mieste: Print email → PDF even when there are no attachments.
        try {
            $emailPrint = app(IncomingFineEmailPrintPdf::class)->ensureLocalPdf(
                $message,
                $extract['saved_dir'] ?? null,
            );
            if ($emailPrint !== null) {
                $extract['saved_dir'] = $emailPrint['saved_dir'];
                $bodyText = trim((string) ($emailPrint['body_text'] ?? ''));
            }
        } catch (Throwable) {
            // Non-fatal during Find driver.
        }

        $haystackBuilder = app(IncomingFineTextHaystack::class);
        if ($bodyText === '') {
            $bodyText = $haystackBuilder->fetchBodyText($message);
        }

        $message['body_text'] = $bodyText;
        $message['attachment_text'] = (string) ($extract['text'] ?? '');
        $message['attachment_extract'] = $extract;

        // Body may carry annulment text when subject/preview alone do not.
        if (IncomingFineMailScanner::isNonFineNotice($subject, $preview."\n".$bodyText)) {
            return $this->skipNonFine($message, $userId, 'Skipped — not a fine notice (annulment / payment / move-car)');
        }

        $haystack = $haystackBuilder->build($message, $extract, $bodyText);
        $message['attachment_text'] = $haystack;
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
            if ($fields['vehicle'] !== '' && ($fields['ticket_datetime'] !== '' || $fields['ticket_date'] !== '')) {
                $rentals = $this->wheelsys->findRentalsCoveringDate(
                    $fields['vehicle'],
                    $fields['ticket_date'] !== '' ? $fields['ticket_date'] : substr($fields['ticket_datetime'], 0, 10),
                    $fields['ticket_datetime'] !== '' ? $fields['ticket_datetime'] : (
                        $fields['ticket_date'] !== ''
                            ? $fields['ticket_date'].' '.($fields['ticket_time'] !== ''
                                ? $fields['ticket_time']
                                : WheelsysTrafficViolationExcelExporter::DEFAULT_TICKET_TIME)
                            : null
                    ),
                );
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
                        : ($fields['ticket_date'] === '' && $fields['ticket_datetime'] === ''
                            ? 'No covering rental (no ticket date/time)'
                            : 'No covering rental for plate/date/time'))));

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
            // Wheelsys needs a time; default 09:00 when notice has date only (e.g. Julianus).
            'ticket_time' => $fields['ticket_time'] !== ''
                ? $fields['ticket_time']
                : ($fields['ticket_date'] !== ''
                    ? WheelsysTrafficViolationExcelExporter::DEFAULT_TICKET_TIME
                    : null),
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
                'wheelsys' => is_array($wheelsys) ? $wheelsys : null,
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

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    private function skipNonFine(array $message, ?int $userId, string $detail): array
    {
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
            'status' => 'skipped',
            'wheels_export_status' => FineProcessLog::WHEELS_EXPORT_SKIPPED,
            'processed_by' => $userId ?? $log->processed_by,
            'processed_at' => $log->processed_at ?: now(),
            'error' => null,
            'workflow' => [
                'steps' => [
                    ['key' => 'lookup', 'ok' => false, 'detail' => $detail],
                ],
            ],
        ])->save();

        return [
            'message' => $message,
            'lookup_status' => 'not_found',
            'lookup_detail' => $detail,
            'vehicle' => '',
            'reference' => '',
            'ticket_date' => '',
            'ticket_time' => '',
            'ticket_datetime' => '',
            'rental' => null,
            'wheelsys' => null,
            'draft' => [],
            'log' => $log,
            'rentals' => [],
        ];
    }
}
