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
        private readonly TicketDayDriverResolver $dayDriverResolver = new TicketDayDriverResolver,
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
            return $this->skipNonFine($message, $userId, 'Skipped — not a fine notice (annulment / payment reminder / move-car)');
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
            return $this->skipNonFine($message, $userId, 'Skipped — not a fine notice (annulment / payment reminder / move-car)');
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
        $dayOutcome = null;
        $driverLabels = [];
        $timeMissing = $fields['ticket_time'] === '';
        $resolvedTicketTime = $fields['ticket_time'];
        $ticketDate = $fields['ticket_date'] !== ''
            ? $fields['ticket_date']
            : ($fields['ticket_datetime'] !== '' ? substr($fields['ticket_datetime'], 0, 10) : '');

        try {

            if ($fields['vehicle'] !== '' && $ticketDate !== '') {
                if (! $timeMissing) {
                    // Precise offense moment — cover that timestamp only.
                    $rentals = $this->wheelsys->findRentalsCoveringDate(
                        $fields['vehicle'],
                        $ticketDate,
                        $fields['ticket_datetime'] !== ''
                            ? $fields['ticket_datetime']
                            : $ticketDate.' '.$fields['ticket_time'],
                    );
                    if ($rentals !== []) {
                        $rental = $rentals[0];
                    }
                } else {
                    // No time on notice: inspect the whole calendar day (00:00–23:59).
                    $rentals = $this->wheelsys->findRentalsCoveringDate($fields['vehicle'], $ticketDate, null);
                    $resolved = $this->dayDriverResolver->resolveUniqueDrivers($rentals);
                    $dayOutcome = $resolved['outcome'];
                    $driverLabels = $resolved['driver_labels'];
                    $rentals = $resolved['rentals'];

                    if ($resolved['outcome'] === 'single') {
                        $rental = $resolved['chosen'];
                        // Safe default for Wheelsys import when only one driver that day.
                        $resolvedTicketTime = WheelsysTrafficViolationExcelExporter::DEFAULT_TICKET_TIME;
                        $fields['ticket_time'] = $resolvedTicketTime;
                        $fields['ticket_datetime'] = $ticketDate.'T'.$resolvedTicketTime;
                    }
                }
            } elseif ($fields['vehicle'] !== '') {
                $rentals = $this->wheelsys->searchRentalsByVehicle($fields['vehicle']);
                if ($rentals !== []) {
                    $rental = $rentals[0];
                }
            }

            if ($rental !== null) {
                $wheelsys = $this->wheelsys->rentalAsWheelsysDraft(
                    $rental,
                    $ticketDate !== '' ? $ticketDate : null,
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

        $ambiguous = $dayOutcome === 'ambiguous';
        $driverName = trim((string) ($wheelsys['driver'] ?? $rental['driver'] ?? $rental['corporate'] ?? ''));
        $found = ! $ambiguous && $rental !== null && $driverName !== '';
        $lookupMissing = $ambiguous
            ? []
            : ($found
                ? []
                : $this->buildLookupMissing(
                    fields: $fields,
                    ticketDate: $ticketDate,
                    rental: $rental,
                    driverName: $driverName,
                    lookupError: $lookupError,
                ));
        $detail = $ambiguous
            ? 'Needs review — '.count($driverLabels).' drivers that day ('.implode(', ', $driverLabels).')'
            : ($found
                ? trim(($rental['agr_no'] ?? '').' · '.$driverName)
                    .($timeMissing && $dayOutcome === 'single' ? ' · time default 09:00 (single driver that day)' : '')
                : $this->formatLookupMissingDetail($lookupMissing, $lookupError));

        $draft = $this->replyBuilder->build($message, $wheelsys);

        $messageId = (string) ($message['id'] ?? '');
        $log = FineProcessLog::query()->firstOrNew(['graph_message_id' => $messageId]);

        $exportStatus = FineProcessLog::WHEELS_EXPORT_SKIPPED;
        if ($ambiguous) {
            $exportStatus = FineProcessLog::WHEELS_EXPORT_NEEDS_REVIEW;
        } elseif ($found && $fields['vehicle'] !== '') {
            $exportStatus = $log->wheels_export_status === FineProcessLog::WHEELS_EXPORT_EXPORTED
                ? FineProcessLog::WHEELS_EXPORT_EXPORTED
                : FineProcessLog::WHEELS_EXPORT_PENDING;
        }

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
            'ticket_date' => $fields['ticket_date'] !== ''
                ? $fields['ticket_date']
                : ($ticketDate !== '' ? $ticketDate : null),
            // Only persist default 09:00 when a single day-driver was resolved (or time was extracted).
            'ticket_time' => $ambiguous
                ? null
                : ($resolvedTicketTime !== ''
                    ? $resolvedTicketTime
                    : null),
            'agr_no' => $ambiguous ? null : ($rental['agr_no'] ?? null),
            'rental_id' => $ambiguous ? null : (isset($rental['id']) ? (int) $rental['id'] : null),
            'driver_name' => $ambiguous ? null : ($wheelsys['driver'] ?? null),
            'local_attachment_dir' => $extract['saved_dir'] ?? $log->local_attachment_dir,
            'status' => $ambiguous ? 'lookup_ambiguous' : ($found ? 'lookup_found' : 'lookup_missing'),
            'wheels_export_status' => $exportStatus,
            'processed_by' => $userId ?? $log->processed_by,
            'processed_at' => $log->processed_at ?: now(),
            'error' => $lookupError,
            'workflow' => [
                'steps' => [
                    ['key' => 'lookup', 'ok' => $found, 'detail' => $detail],
                ],
                'fields' => $fields,
                'lookup_missing' => $lookupMissing,
                'wheelsys' => $ambiguous ? null : (is_array($wheelsys) ? $wheelsys : null),
                'rental_count' => count($rentals),
                'day_driver_outcome' => $dayOutcome,
                'ambiguous_drivers' => $ambiguous ? $driverLabels : [],
                'ticket_time_defaulted' => $timeMissing && $dayOutcome === 'single',
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
            'lookup_status' => $ambiguous ? 'needs_review' : ($found ? 'found' : 'not_found'),
            'lookup_detail' => $detail,
            'lookup_missing' => $lookupMissing,
            'vehicle' => $fields['vehicle'],
            'reference' => $fields['reference'],
            'ticket_date' => $fields['ticket_date'] !== '' ? $fields['ticket_date'] : ($ticketDate ?? ''),
            'ticket_time' => $ambiguous ? '' : $resolvedTicketTime,
            'ticket_datetime' => $fields['ticket_datetime'],
            'rental' => $ambiguous ? null : $rental,
            'wheelsys' => $ambiguous ? null : $wheelsys,
            'draft' => $draft,
            'log' => $log,
            'rentals' => $rentals,
        ];
    }

    /**
     * Human labels for lookup gap keys (UI chip / tooltip).
     *
     * @return array<string, string>
     */
    public static function missingLabels(): array
    {
        return [
            'plate' => 'Plate / numeris',
            'ticket_date' => 'Ticket date',
            'ticket_time' => 'Ticket time',
            'reference' => 'Reference / bylos nr.',
            'rental' => 'Wheelsys rental',
            'driver' => 'Driver / pavardė',
            'company' => 'Company name',
            'error' => 'Lookup error',
        ];
    }

    /**
     * @param  list<string>  $missing
     * @return array{chip: string, tooltip: string, labels: list<string>}
     */
    public static function presentMissing(array $missing, ?string $detail = null): array
    {
        $map = self::missingLabels();
        $labels = [];
        foreach ($missing as $key) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }
            $labels[] = $map[$key] ?? str_replace('_', ' ', $key);
        }
        $labels = array_values(array_unique($labels));

        if ($labels === []) {
            $fallback = trim((string) $detail);
            return [
                'chip' => $fallback !== '' ? 'Not found · see tip' : 'Not found · retry',
                'tooltip' => $fallback !== '' ? $fallback : 'Could not find driver / rental',
                'labels' => $fallback !== '' ? [$fallback] : [],
            ];
        }

        $chip = count($labels) === 1
            ? 'Not found · '.$labels[0]
            : 'Not found · '.count($labels).' missing';

        $tooltipLines = array_map(static fn (string $label): string => '· '.$label, $labels);
        $detail = trim((string) $detail);
        if ($detail !== '' && ! in_array($detail, $labels, true)) {
            $tooltipLines[] = '';
            $tooltipLines[] = $detail;
        }

        return [
            'chip' => $chip,
            'tooltip' => implode("\n", $tooltipLines),
            'labels' => $labels,
        ];
    }

    /**
     * Rebuild gaps for older logs that predate lookup_missing.
     *
     * @return list<string>
     */
    public static function inferMissingFromLog(FineProcessLog $log): array
    {
        if (is_array($log->workflow['lookup_missing'] ?? null) && $log->workflow['lookup_missing'] !== []) {
            return array_values(array_map('strval', $log->workflow['lookup_missing']));
        }

        $fields = is_array($log->workflow['fields'] ?? null) ? $log->workflow['fields'] : [];
        $vehicle = trim((string) ($log->vehicle ?: ($fields['vehicle'] ?? '')));
        $ticketDate = trim((string) (
            ($log->ticket_date?->format('Y-m-d'))
            ?: ($fields['ticket_date'] ?? '')
            ?: (($fields['ticket_datetime'] ?? '') !== '' ? substr((string) $fields['ticket_datetime'], 0, 10) : '')
        ));
        $driverName = trim((string) ($log->driver_name ?? ''));
        $hasRental = filled($log->rental_id) || filled($log->agr_no);

        return (new self)->buildLookupMissing(
            fields: [
                'vehicle' => $vehicle,
                'ticket_date' => $ticketDate,
                'ticket_time' => (string) ($log->ticket_time ?: ($fields['ticket_time'] ?? '')),
                'ticket_datetime' => (string) ($fields['ticket_datetime'] ?? ''),
                'reference' => (string) ($log->reference ?: ($fields['reference'] ?? '')),
            ],
            ticketDate: $ticketDate,
            rental: $hasRental ? ['id' => $log->rental_id, 'agr_no' => $log->agr_no] : null,
            driverName: $driverName,
            lookupError: filled($log->error) ? (string) $log->error : null,
        );
    }

    /**
     * @param  array{vehicle?:string,ticket_date?:string,ticket_time?:string,ticket_datetime?:string,reference?:string}  $fields
     * @param  array<string, mixed>|null  $rental
     * @return list<string>
     */
    private function buildLookupMissing(
        array $fields,
        string $ticketDate,
        ?array $rental,
        string $driverName,
        ?string $lookupError,
    ): array {
        $missing = [];
        $vehicle = trim((string) ($fields['vehicle'] ?? ''));
        $reference = trim((string) ($fields['reference'] ?? ''));
        $ticketTime = trim((string) ($fields['ticket_time'] ?? ''));

        // What we failed to parse from the notice (blocks / explains Find driver).
        if ($vehicle === '') {
            $missing[] = 'plate';
        }
        if ($ticketDate === '') {
            $missing[] = 'ticket_date';
        }
        if ($reference === '' && ($vehicle === '' || $ticketDate === '')) {
            $missing[] = 'reference';
        }

        if ($lookupError) {
            $missing[] = 'error';
        } elseif ($rental === null && $vehicle !== '') {
            $missing[] = 'rental';
            // Time often decides which rental covers the offense.
            if ($ticketDate !== '' && $ticketTime === '') {
                $missing[] = 'ticket_time';
            }
        } elseif ($rental !== null && $driverName === '') {
            $missing[] = 'driver';
        }

        return array_values(array_unique($missing));
    }

    /**
     * @param  list<string>  $missing
     */
    private function formatLookupMissingDetail(array $missing, ?string $lookupError): string
    {
        if (filled($lookupError)) {
            return (string) $lookupError;
        }

        $presented = self::presentMissing($missing);
        if ($presented['labels'] === []) {
            return 'Could not find driver / rental';
        }

        return 'Missing: '.implode(', ', $presented['labels']);
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
                'lookup_missing' => [],
            ],
        ])->save();

        return [
            'message' => $message,
            'lookup_status' => 'not_found',
            'lookup_detail' => $detail,
            'lookup_missing' => [],
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
