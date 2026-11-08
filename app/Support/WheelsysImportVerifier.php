<?php

namespace App\Support;

use App\Models\FineProcessLog;
use Throwable;

/**
 * Confirms that prepared import fines exist as Traffic Violations in Wheelsys.
 * Once marked imported, rows are not re-scanned.
 */
class WheelsysImportVerifier
{
    public function __construct(
        private readonly WheelsysClient $wheelsys,
    ) {}

    /**
     * @param  iterable<FineProcessLog>  $logs
     * @return array{verified: int, missing: int, skipped: int, errors: list<string>}
     */
    public function verifyMany(iterable $logs, ?int $userId = null): array
    {
        $verified = 0;
        $missing = 0;
        $skipped = 0;
        $errors = [];

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

            $log->fill([
                'wheels_import_status' => FineProcessLog::WHEELS_IMPORT_IMPORTED,
                'wheels_violation_id' => isset($match['id']) ? (string) $match['id'] : null,
                'wheels_verify_detail' => $this->detailFromMatch($match),
                'wheels_imported_at' => now(),
                'wheels_imported_by' => $userId,
            ])->save();
            $verified++;
        }

        return compact('verified', 'missing', 'skipped', 'errors');
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
}
