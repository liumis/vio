<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class ViolationBirthDateMerger
{
    /**
     * Columns used to match a primary row to a birth-date row.
     *
     * @var list<string>
     */
    public const MERGE_KEY_COLUMNS = [
        'vehicle',
        'ticket_date',
        'driver',
    ];

    /**
     * @param  array<string, mixed>  $mapped
     */
    public static function mergeKey(array $mapped): string
    {
        return implode('|', array_map(
            fn (string $column): string => self::normalizeKeyPart($mapped[$column] ?? null),
            self::MERGE_KEY_COLUMNS,
        ));
    }

    /**
     * @param  array<int, array{row_number: int, row_data: array<string, mixed>}>  $rows
     * @return array<string, string> merge key => birth date (Y-m-d)
     */
    public static function buildBirthDateLookup(array $rows): array
    {
        $lookup = [];
        $duplicateKeys = [];

        foreach ($rows as $row) {
            $mapped = ViolationImportMapping::mapRowToAttributes($row['row_data']);
            $missingKeyColumns = array_values(array_filter(
                self::MERGE_KEY_COLUMNS,
                fn (string $column): bool => ! filled($mapped[$column] ?? null),
            ));

            if ($missingKeyColumns !== []) {
                continue;
            }

            if (! filled($mapped['birth_date'] ?? null)) {
                continue;
            }

            $key = self::mergeKey($mapped);

            if (isset($lookup[$key]) && $lookup[$key] !== $mapped['birth_date']) {
                $duplicateKeys[$key] = true;
            }

            $lookup[$key] = $mapped['birth_date'];
        }

        if ($duplicateKeys !== []) {
            throw ValidationException::withMessages([
                'data.birth_date_file_path' => 'Birth date file contains conflicting birth dates for the same vehicle, ticket date, and driver.',
            ]);
        }

        return $lookup;
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<string, string>  $lookup
     * @return array<string, mixed>
     */
    public static function applyBirthDate(array $mapped, array $lookup): array
    {
        $hasMergeKeys = collect(self::MERGE_KEY_COLUMNS)->every(
            fn (string $column): bool => filled($mapped[$column] ?? null),
        );

        if (! $hasMergeKeys) {
            return $mapped;
        }

        $key = self::mergeKey($mapped);

        if (isset($lookup[$key])) {
            $mapped['birth_date'] = $lookup[$key];
        }

        return $mapped;
    }

    private static function normalizeKeyPart(mixed $value): string
    {
        $normalized = trim((string) $value);
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return strtolower($normalized);
    }
}
