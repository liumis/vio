<?php

namespace App\Support;

use App\Models\FineProcessLog;
use Throwable;

/**
 * End-to-end incoming fine pipeline:
 * extract → rental lookup → SharePoint (mail stays in inbox) → reply draft (Send is manual).
 */
final class IncomingFineWorkflow
{
    public function __construct(
        private readonly IncomingFineAttachmentTextExtractor $extractor = new IncomingFineAttachmentTextExtractor,
        private readonly IncomingFineProcessor $processor = new IncomingFineProcessor,
        private readonly IncomingFineReplyBuilder $replyBuilder = new IncomingFineReplyBuilder,
        private readonly WheelsysClient $wheelsys = new WheelsysClient,
    ) {
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{
     *     message: array<string, mixed>,
     *     extract: array<string, mixed>,
     *     fields: array{reference:string,vehicle:string,ticket_date:string,ticket_time:string,ticket_datetime:string},
     *     rentals: list<array<string, mixed>>,
     *     rental: array<string, mixed>|null,
     *     wheelsys: array<string, mixed>|null,
     *     process: array<string, mixed>,
     *     draft: array<string, mixed>,
     *     steps: list<array{key:string,ok:bool,detail:string}>,
     *     log: FineProcessLog
     * }
     */
    public function run(array $message, ?int $userId = null): array
    {
        $steps = [];

        $extract = ['text' => '', 'files' => [], 'saved_dir' => null];
        try {
            if (! empty($message['has_attachments'])) {
                $extract = $this->extractor->extractDetailedFromMessage($message, persist: true);
            }
            $chars = mb_strlen((string) ($extract['text'] ?? ''));
            $steps[] = [
                'key' => 'extract',
                'ok' => $chars > 0 || empty($message['has_attachments']),
                'detail' => $chars > 0
                    ? "Attachment text {$chars} chars"
                    : (! empty($message['has_attachments'])
                        ? 'No extractable PDF text (scanned?)'
                        : 'No attachments'),
            ];
        } catch (Throwable $e) {
            $steps[] = ['key' => 'extract', 'ok' => false, 'detail' => $e->getMessage()];
        }

        // JUDU / Unipark / Parkavimas mieste: Print email → PDF (instruction).
        try {
            $emailPrint = app(IncomingFineEmailPrintPdf::class)->ensureLocalPdf(
                $message,
                $extract['saved_dir'] ?? null,
            );
            if ($emailPrint !== null) {
                $extract['saved_dir'] = $emailPrint['saved_dir'];
                $extract['files'][] = [
                    'name' => IncomingFineEmailPrintPdf::LOCAL_FILE,
                    'path' => $emailPrint['relative_path'],
                    'bytes' => $emailPrint['bytes'],
                    'text_chars' => 0,
                    'source' => 'email-print',
                    'error' => null,
                ];
                $steps[] = [
                    'key' => 'email_print',
                    'ok' => true,
                    'detail' => 'Email Print to PDF · '.number_format($emailPrint['bytes'] / 1024, 1).' KB',
                ];
            }
        } catch (Throwable $e) {
            if (app(IncomingFineEmailPrintPdf::class)->shouldPrint($message)) {
                $steps[] = ['key' => 'email_print', 'ok' => false, 'detail' => $e->getMessage()];
            }
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
        $steps[] = [
            'key' => 'parse',
            'ok' => $fields['vehicle'] !== '' || $fields['reference'] !== '',
            'detail' => trim(implode(' · ', array_filter([
                $fields['reference'] !== '' ? $fields['reference'] : null,
                $fields['vehicle'] !== '' ? $fields['vehicle'] : null,
                $fields['ticket_date'] !== '' ? $fields['ticket_date'] : null,
                $fields['ticket_time'] !== '' ? $fields['ticket_time'] : null,
            ]))) ?: 'Could not parse plate/date/ref',
        ];

        $rentals = [];
        $rental = null;
        $wheelsys = null;
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
                    // Address enrichment is best-effort.
                }
            }

            $steps[] = [
                'key' => 'rental',
                'ok' => $rental !== null,
                'detail' => $rental !== null
                    ? trim(($rental['agr_no'] ?? '').' · '.($rental['driver'] ?? $rental['corporate'] ?? '—'))
                    : ($fields['vehicle'] !== '' ? 'No covering rental for plate/date' : 'Skipped (no plate)'),
            ];
        } catch (Throwable $e) {
            $steps[] = ['key' => 'rental', 'ok' => false, 'detail' => $e->getMessage()];
        }

        $process = $this->processor->process($message, $userId);
        /** @var FineProcessLog $log */
        $log = $process['log'];
        $steps[] = [
            'key' => 'sharepoint',
            'ok' => (int) ($process['attachments_uploaded'] ?? 0) > 0 || empty($message['has_attachments']),
            'detail' => ((int) ($process['attachments_uploaded'] ?? 0)).' file(s) uploaded',
        ];
        $steps[] = [
            'key' => 'inbox',
            'ok' => true,
            'detail' => 'Left in inbox · tracked in VIO',
        ];

        $draft = $this->replyBuilder->build($message, $wheelsys);
        $steps[] = [
            'key' => 'draft',
            'ok' => true,
            'detail' => ! empty($draft['missing_fields'])
                ? 'Draft ready · missing '.implode(', ', $draft['missing_fields'])
                : 'Draft ready',
        ];
        $exportReady = $fields['vehicle'] !== ''
            && ($fields['reference'] !== '' || $fields['ticket_date'] !== '');
        $steps[] = [
            'key' => 'wheels_create',
            'ok' => false,
            'detail' => $exportReady
                ? 'Queued for Wheelsys import'
                : 'Missing vehicle/reference/date for Wheelsys import',
        ];
        if (($draft['reply_channel'] ?? '') === 'epaslaugos') {
            $steps[] = [
                'key' => 'epaslaugos',
                'ok' => false,
                'detail' => 'Open ePaslaugos, paste ROIK/driver, submit naudotojo teikimas',
            ];
            $steps[] = [
                'key' => 'epaslaugos_atmesti',
                'ok' => false,
                'detail' => 'Later: check Atmesti in ePaslaugos',
            ];
        } elseif (($draft['info_pdf_template'] ?? '') === 'latvia'
            || str_contains(strtolower((string) ($draft['authority_name'] ?? '')), 'latvij')
            || str_contains(strtolower((string) ($message['from_email'] ?? '')), 'vp.gov.lv')
        ) {
            $steps[] = [
                'key' => 'latvia_marksign',
                'ok' => false,
                'detail' => 'Generate LV notice → MarkSign (Mykolas)',
            ];
            $steps[] = [
                'key' => 'latvia_send',
                'ok' => false,
                'detail' => 'Send package to kanceleja.cpp@vp.gov.lv (+ POA + original + RNT)',
            ];
        } else {
            $steps[] = [
                'key' => 'send',
                'ok' => false,
                'detail' => 'Review draft, then Send',
            ];
        }

        $log->fill([
            'vehicle' => $fields['vehicle'] !== '' ? $fields['vehicle'] : null,
            'reference' => $fields['reference'] !== '' ? $fields['reference'] : null,
            'ticket_date' => $fields['ticket_date'] !== '' ? $fields['ticket_date'] : null,
            'ticket_time' => $fields['ticket_time'] !== '' ? $fields['ticket_time'] : null,
            'agr_no' => $rental['agr_no'] ?? null,
            'rental_id' => isset($rental['id']) ? (int) $rental['id'] : null,
            'driver_name' => $wheelsys['driver'] ?? null,
            'local_attachment_dir' => $extract['saved_dir'] ?? null,
            'workflow' => [
                'steps' => $steps,
                'fields' => $fields,
                'rental_count' => count($rentals),
            ],
            // Do not overwrite an already-exported row if Process is re-run on same graph id
            // (graph_message_id is unique — Process typically creates new log; keep status pending when ready).
            'wheels_export_status' => $exportReady
                ? FineProcessLog::WHEELS_EXPORT_PENDING
                : FineProcessLog::WHEELS_EXPORT_SKIPPED,
            'status' => ! empty($process['error']) ? 'partial' : 'processed',
            'error' => $process['error'] ?? $log->error,
        ])->save();

        // Instruction: archive PDF as "{Driver} {Reference}" once identity is known.
        // Letter-form authorities also Print email → PDF first, then SharePoint.
        try {
            $printer = app(IncomingFineEmailPrintPdf::class);
            if ($printer->shouldPrint($message)) {
                $printer->ensureAndArchive($log->fresh(), $message);
            } else {
                app(IncomingFineDocumentArchiver::class)->archive($log->fresh());
            }
        } catch (Throwable) {
            // Non-fatal — Generate import / manual retry can archive later.
        }

        return [
            'message' => $message,
            'extract' => $extract,
            'fields' => $fields,
            'rentals' => $rentals,
            'rental' => $rental,
            'wheelsys' => $wheelsys,
            'process' => $process,
            'draft' => $draft,
            'steps' => $steps,
            'log' => $log,
        ];
    }
}
