<?php

namespace App\Support;

/**
 * When a fine has a date but no offense time, decide whether the calendar day
 * (00:00–23:59) has a single driver or needs human review.
 */
final class TicketDayDriverResolver
{
    /**
     * @param  list<array<string, mixed>>  $rentals  Day-covering rentals
     * @return array{
     *     outcome: 'none'|'single'|'ambiguous',
     *     chosen: array<string, mixed>|null,
     *     rentals: list<array<string, mixed>>,
     *     driver_keys: list<string>,
     *     driver_labels: list<string>
     * }
     */
    public function resolveUniqueDrivers(array $rentals): array
    {
        /** @var array<string, array{label: string, rental: array<string, mixed>}> $byKey */
        $byKey = [];

        foreach ($rentals as $rental) {
            if (! is_array($rental)) {
                continue;
            }
            $label = $this->driverLabel($rental);
            $key = $this->driverKey($rental, $label);
            if ($key === '') {
                continue;
            }
            if (! isset($byKey[$key])) {
                $byKey[$key] = ['label' => $label !== '' ? $label : $key, 'rental' => $rental];
            }
        }

        $keys = array_keys($byKey);
        $labels = array_values(array_map(static fn (array $row): string => $row['label'], $byKey));

        if ($keys === []) {
            return [
                'outcome' => 'none',
                'chosen' => null,
                'rentals' => array_values($rentals),
                'driver_keys' => [],
                'driver_labels' => [],
            ];
        }

        if (count($keys) === 1) {
            $only = reset($byKey);

            return [
                'outcome' => 'single',
                'chosen' => $only['rental'],
                'rentals' => array_values($rentals),
                'driver_keys' => $keys,
                'driver_labels' => $labels,
            ];
        }

        return [
            'outcome' => 'ambiguous',
            'chosen' => null,
            'rentals' => array_values($rentals),
            'driver_keys' => $keys,
            'driver_labels' => $labels,
        ];
    }

    /**
     * @param  array<string, mixed>  $rental
     */
    public function driverLabel(array $rental): string
    {
        $driver = trim((string) ($rental['driver'] ?? ''));
        if ($driver !== '') {
            return $driver;
        }

        return trim((string) ($rental['corporate'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $rental
     */
    public function driverKey(array $rental, ?string $label = null): string
    {
        $driverId = trim((string) ($rental['driver_id'] ?? ''));
        if ($driverId !== '' && $driverId !== '0') {
            return 'id:'.$driverId;
        }

        $label ??= $this->driverLabel($rental);
        $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', trim($label)) ?? '');
        if ($normalized !== '') {
            return 'name:'.$normalized;
        }

        $agr = trim((string) ($rental['agr_no'] ?? ''));
        if ($agr !== '') {
            return 'agr:'.mb_strtolower($agr);
        }

        $id = $rental['id'] ?? null;
        if ($id !== null && $id !== '') {
            return 'rental:'.$id;
        }

        return '';
    }
}
