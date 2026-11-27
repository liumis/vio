<?php

namespace App\Support;

use App\Models\FineProcessLog;
use Throwable;

/**
 * Confirms that prepared import fines exist as Traffic Violations in Wheelsys.
 *
 * Default strategy (per import file): check the first and last Excel rows in Wheels.
 * - both found → mark the whole file imported (bookends prove the import landed)
 * - neither found → mark the whole file missing
 * - only one found → fall back to per-row lookup (partial / ambiguous import)
 *
 * Once marked imported, rows are not re-scanned.
 */
class WheelsysImportVerifier
{
    public function __construct(
        private readonly WheelsysClient $wheelsys,
    ) {}

    /**
     * @param  iterable<FineProcessLog>  $logs
     * @return array{verified: int, missing: int, skipped: int, errors: list<string>, batches: int}
     */
    public function verifyMany(iterable $logs, ?int $userId = null): array
    {
        $verified = 0;
        $missing = 0;
        $skipped = 0;
        $errors = [];
        $batchesChecked = 0;

        /** @var array<string, list<FineProcessLog>> $byBatch */
        $byBatch = [];
        /** @var list<FineProcessLog> $loose */
        $loose = [];

        foreach ($logs as $log) {
            if (! $log instanceof FineProcessLog) {
                continue;
            }

            if ($log->wheels_import_status === FineProcessLog::WHEELS_IMPORT_IMPORTED) {
                $skipped++;

                continue;
            }

            if ($log->wheels_export_status !== FineProcessLog::WHEELS_EXPORT_EXPORTED) {
                $skipped++;

                continue;
            }

            $batch = trim((string) ($log->wheels_export_batch ?? ''));
            if ($batch === '') {
                $loose[] = $log;

                continue;
            }

            $byBatch[$batch][] = $log;
        }

        foreach ($byBatch as $batch => $batchLogs) {
            $batchesChecked++;
            usort(
                $batchLogs,
                static fn (FineProcessLog $a, FineProcessLog $b): int => $a->id <=> $b->id
            );

            $result = $this->verifyBatchByBookends($batchLogs, $userId);
            $verified += $result['verified'];
            $missing += $result['missing'];
            $skipped += $result['skipped'];
            foreach ($result['errors'] as $error) {
                $errors[] = $error;
            }
        }

        if ($loose !== []) {
            $result = $this->verifyRowsIndividually($loose, $userId);
            $verified += $result['verified'];
            $missing += $result['missing'];
            $skipped += $result['skipped'];
            foreach ($result['errors'] as $error) {
                $errors[] = $error;
            }
        }

        return [
            'verified' => $verified,
            'missing' => $missing,
            'skipped' => $skipped,
            'errors' => $errors,
            'batches' => $batchesChecked,
        ];
    }

    /**
     * First + last row in the prepared file decide the batch status.
     *
     * @param  list<FineProcessLog>  $batchLogs  same wheels_export_batch, sorted by id
     * @return array{verified: int, missing: int, skipped: int, errors: list<string>}
     */
    private function verifyBatchByBookends(array $batchLogs, ?int $userId): array
    {
        $count = count($batchLogs);
        if ($count === 0) {
            return ['verified' => 0, 'missing' => 0, 'skipped' => 0, 'errors' => []];
        }

        if ($count === 1) {
            return $this->verifyRowsIndividually($batchLogs, $userId);
        }

        $first = $batchLogs[0];
        $last = $batchLogs[$count - 1];

        try {
            $firstMatch = $this->findMatch($first);
            $lastMatch = $first->id === $last->id ? $firstMatch : $this->findMatch($last);
        } catch (Throwable $e) {
            // Ambiguous — check every row so one Wheels outage does not wipe a file.
            $errors = ['batch '.$first->wheels_export_batch.': bookend check failed · '.$e->getMessage()];
            $fallback = $this->verifyRowsIndividually($batchLogs, $userId);
            $fallback['errors'] = array_merge($errors, $fallback['errors']);

            return $fallback;
        }

        $firstOk = $firstMatch !== null;
        $lastOk = $lastMatch !== null;

        if ($firstOk && $lastOk) {
            return $this->markBatchImported($batchLogs, $firstMatch, $lastMatch, $userId);
        }

        if (! $firstOk && ! $lastOk) {
            return $this->markBatchMissing(
                $batchLogs,
                'First + last rows not found in Wheelsys — file treated as not imported'
            );
        }

        // Only one bookend found → do not guess; resolve row by row.
        return $this->verifyRowsIndividually($batchLogs, $userId);
    }

    /**
     * @param  list<FineProcessLog>  $batchLogs
     * @param  array<string, mixed>  $firstMatch
     * @param  array<string, mixed>  $lastMatch
     * @return array{verified: int, missing: int, skipped: int, errors: list<string>}
     */
    private function markBatchImported(
        array $batchLogs,
        array $firstMatch,
        array $lastMatch,
        ?int $userId,
    ): array {
        $verified = 0;
        $confirmedAt = now();
        $firstId = $batchLogs[0]->id;
        $lastId = $batchLogs[count($batchLogs) - 1]->id;

        foreach ($batchLogs as $log) {
            $match = null;
            if ($log->id === $firstId) {
                $match = $firstMatch;
            } elseif ($log->id === $lastId) {
                $match = $lastMatch;
            }

            // Middle rows: no extra Wheels calls — first+last already proved the file landed.
            $state = isset($match['state']) ? (string) $match['state'] : null;
            $paidDate = filled($match['paid_date'] ?? null) ? (string) $match['paid_date'] : null;
            $detail = $match !== null
                ? $this->detailFromMatch($match).' · bookend'
                : 'Batch imported (first+last found in Wheels)';

            $log->fill([
                'wheels_import_status' => FineProcessLog::WHEELS_IMPORT_IMPORTED,
                'wheels_violation_id' => isset($match['id']) ? (string) $match['id'] : null,
                'wheels_verify_detail' => $detail,
                'wheels_tv_state' => $state,
                'wheels_paid_date' => $paidDate,
                'wheels_imported_at' => $confirmedAt,
                'wheels_imported_by' => $userId,
            ])->save();
            $this->ensurePendingMailboxFolder($log);
            $verified++;
        }

        return ['verified' => $verified, 'missing' => 0, 'skipped' => 0, 'errors' => []];
    }

    /**
     * @param  list<FineProcessLog>  $batchLogs
     * @return array{verified: int, missing: int, skipped: int, errors: list<string>}
     */
    private function markBatchMissing(array $batchLogs, string $detail): array
    {
        $missing = 0;
        foreach ($batchLogs as $log) {
            $log->fill([
                'wheels_import_status' => FineProcessLog::WHEELS_IMPORT_MISSING,
                'wheels_violation_id' => null,
                'wheels_verify_detail' => $detail,
                'wheels_imported_at' => null,
                'wheels_imported_by' => null,
            ])->save();
            $missing++;
        }

        return ['verified' => 0, 'missing' => $missing, 'skipped' => 0, 'errors' => []];
    }

    /**
     * @param  list<FineProcessLog>  $logs
     * @return array{verified: int, missing: int, skipped: int, errors: list<string>}
     */
    private function verifyRowsIndividually(array $logs, ?int $userId): array
    {
        $verified = 0;
        $missing = 0;
        $skipped = 0;
        $errors = [];

        foreach ($logs as $log) {
            if ($log->wheels_import_status === FineProcessLog::WHEELS_IMPORT_IMPORTED) {
                $skipped++;

                continue;
            }

            try {
                $match = $this->findMatch($log);
            } catch (Throwable $e) {
                $errors[] = '#'.$log->id.' '.$e->getMessage();
                $log->fill([
                    'wheels_import_status' => FineProcessLog::WHEELS_IMPORT_MISSING,
                    'wheels_verify_detail' => 'Error: '.$e->getMessage(),
                ])->save();
                $missing++;

                continue;
            }

            if ($match === null) {
                $log->fill([
                    'wheels_import_status' => FineProcessLog::WHEELS_IMPORT_MISSING,
                    'wheels_violation_id' => null,
                    'wheels_verify_detail' => 'Not found in Wheelsys TV for plate'
                        .($log->ticket_date ? ' + ticket date' : ''),
                    'wheels_imported_at' => null,
                    'wheels_imported_by' => null,
                ])->save();
                $missing++;

                continue;
            }

            $state = isset($match['state']) ? (string) $match['state'] : null;
            $paidDate = filled($match['paid_date'] ?? null) ? (string) $match['paid_date'] : null;
            $log->fill([
                'wheels_import_status' => FineProcessLog::WHEELS_IMPORT_IMPORTED,
                'wheels_violation_id' => isset($match['id']) ? (string) $match['id'] : null,
                'wheels_verify_detail' => $this->detailFromMatch($match),
                'wheels_tv_state' => $state,
                'wheels_paid_date' => $paidDate,
                'wheels_imported_at' => now(),
                'wheels_imported_by' => $userId,
            ])->save();
            $this->ensurePendingMailboxFolder($log);
            $verified++;
        }

        return compact('verified', 'missing', 'skipped', 'errors');
    }

    /**
     * Inbox → "VIO pending" once Wheelsys import is confirmed (no-op if already moved / finished).
     */
    private function ensurePendingMailboxFolder(FineProcessLog $log): void
    {
        try {
            app(IncomingFineMailboxFolders::class)->moveToPending($log->fresh());
        } catch (Throwable) {
            // Non-fatal — import status already saved.
        }
    }

    /**
     * Pull Wheelsys TV State / PAIDDATE for unpaid fines; mark paid when PAIDDATE is set.
     *
     * @param  iterable<FineProcessLog>  $logs
     * @return array{checked: int, paid: int, updated: int, missing: int, errors: list<string>}
     */
    public function syncPaymentStatus(iterable $logs, ?int $userId = null): array
    {
        $checked = 0;
        $paid = 0;
        $updated = 0;
        $missing = 0;
        $errors = [];

        foreach ($logs as $log) {
            if (! $log instanceof FineProcessLog || $log->isPaid()) {
                continue;
            }

            $checked++;

            try {
                $match = $this->findMatch($log);
            } catch (Throwable $e) {
                $errors[] = '#'.$log->id.' '.$e->getMessage();

                continue;
            }

            if ($match === null) {
                $missing++;

                continue;
            }

            $state = isset($match['state']) ? (string) $match['state'] : null;
            $paidDate = filled($match['paid_date'] ?? null) ? (string) $match['paid_date'] : null;
            $fill = [
                'wheels_tv_state' => $state,
                'wheels_paid_date' => $paidDate,
                'wheels_violation_id' => isset($match['id']) ? (string) $match['id'] : $log->wheels_violation_id,
                'wheels_verify_detail' => $this->detailFromMatch($match)
                    .($state !== null && $state !== '' ? ' · state '.$state : '')
                    .($paidDate ? ' · paid '.$paidDate : ''),
            ];

            if ($paidDate !== null) {
                $fill['paid_at'] = \Illuminate\Support\Carbon::parse($paidDate)->startOfDay();
                $fill['paid_marked_by'] = $userId;
                $paid++;
            }

            $log->fill($fill)->save();
            $updated++;
        }

        return compact('checked', 'paid', 'updated', 'missing', 'errors');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findMatch(FineProcessLog $log): ?array
    {
        $vehicle = strtoupper(trim((string) $log->vehicle));
        if ($vehicle === '') {
            return null;
        }

        $ticketDate = $log->ticket_date?->format('Y-m-d');
        $rows = $this->wheelsys->searchTrafficViolations($vehicle, $ticketDate);

        if ($rows === []) {
            // Date filter may be too strict (OCR date mismatch) — retry plate-only.
            if ($ticketDate !== null) {
                $rows = $this->wheelsys->searchTrafficViolations($vehicle, null);
            }
            if ($rows === []) {
                return null;
            }
        }

        $wantedRef = $this->normalizeTicketKey((string) ($log->reference ?? ''));

        if ($wantedRef !== '') {
            foreach ($rows as $row) {
                $ticketNo = $this->normalizeTicketKey((string) ($row['ticket_number'] ?? ''));
                if ($ticketNo !== '' && $ticketNo === $wantedRef) {
                    return $row;
                }
            }

            // Reference present but no ticket match: still allow date+plate unique hit.
            if ($ticketDate !== null) {
                $dated = array_values(array_filter(
                    $rows,
                    static fn (array $row): bool => ($row['ticket_date'] ?? null) === $ticketDate
                ));
                if (count($dated) === 1) {
                    return $dated[0];
                }
            }

            return null;
        }

        if ($ticketDate !== null) {
            $dated = array_values(array_filter(
                $rows,
                static fn (array $row): bool => ($row['ticket_date'] ?? null) === $ticketDate
            ));
            if (count($dated) === 1) {
                return $dated[0];
            }
            if (count($dated) > 1) {
                return null;
            }
        }

        // No reference / no unique date — only accept single TV for the plate.
        if (count($rows) === 1) {
            return $rows[0];
        }

        return null;
    }

    private function normalizeTicketKey(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/\s+/', '', $value) ?? $value;
        $value = preg_replace('/^ROIK/', '', $value) ?? $value;

        return $value;
    }

    /**
     * @param  array<string, mixed>  $match
     */
    private function detailFromMatch(array $match): string
    {
        $parts = array_filter([
            isset($match['id']) ? 'TV #'.$match['id'] : null,
            filled($match['ticket_number'] ?? null) ? 'ticket '.$match['ticket_number'] : null,
            filled($match['ticket_date'] ?? null) ? (string) $match['ticket_date'] : null,
        ]);

        return implode(' · ', $parts);
    }

    /**
     * Human label for Wheelsys TV State (+ paid override).
     */
    public static function stateLabel(?string $state, mixed $paidDate = null): string
    {
        if (filled($paidDate)) {
            return 'Paid';
        }

        // Wheelsys stores numeric State; import default is "Open" (= 1 in live data).
        return match ((string) $state) {
            '1' => 'Open',
            '' => '—',
            default => 'State '.$state,
        };
    }
}
