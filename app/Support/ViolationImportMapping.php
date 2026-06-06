<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Maps Excel header cells (flexible matching) to violation table attributes.
 */
final class ViolationImportMapping
{
    /**
     * @var list<string>
     */
    private const DATE_COLUMNS = [
        'ticket_date',
        'birth_date',
        'licence_issue_date',
    ];

    /**
     * Normalized header (lowercase, single spaces) => database column name.
     *
     * @var array<string, string>
     */
    private const NORMALIZED_HEADER_TO_COLUMN = [
        'reference no' => 'ticket_number',
        'driver name' => 'driver',
        'ticket date' => 'ticket_date',
        'licence number' => 'driver_license',
        'vehicle' => 'vehicle',
        'birth date' => 'birth_date',
        'driver e-mail' => 'customer_email',
        'driver email' => 'customer_email',
        'driver address' => 'driver_address',
        'driver telephone' => 'driver_telephone',
        'licence issue date' => 'licence_issue_date',
        'driver country' => 'driver_country',
        'driver city' => 'driver_city',
        'rental agreement' => 'agr_no',
        'customer paid' => 'customer_paid',
        'licence issue place' => 'licence_issue_place',
        'agent' => 'agent',
        'authority email' => 'authority_email',
    ];

    /**
     * These columns must exist in the Excel file and must not be empty on any imported row.
     *
     * @var list<string>
     */
    public const REQUIRED_COLUMNS = [
        'ticket_number',
        'ticket_date',
        'driver',
        'agr_no',
    ];

    /**
     * Shown by default in the violations table; other mapped columns are toggleable.
     *
     * @var list<string>
     */
    public const DEFAULT_TABLE_VISIBLE_COLUMNS = [
        'ticket_number',
        'ticket_date',
        'driver_license',
        'vehicle',
        'agr_no',
        'driver',
        'birth_date',
        'customer_email',
        'driver_address',
        'driver_city',
        'driver_country',
        'driver_telephone',
        'licence_issue_date',
        'licence_issue_place',
        'customer_paid',
        'agent',
        'authority_email',
    ];

    public const COLUMN_NAMES = [
        'ticket_number',
        'ticket_date',
        'driver_license',
        'vehicle',
        'agr_no',
        'driver',
        'birth_date',
        'customer_email',
        'driver_address',
        'driver_city',
        'driver_country',
        'driver_telephone',
        'licence_issue_date',
        'licence_issue_place',
        'customer_paid',
        'agent',
        'authority_email',
    ];

    /**
     * Human labels for Filament (attribute => label).
     *
     * @return array<string, string>
     */
    public static function columnLabels(): array
    {
        return [
            'ticket_number' => 'Reference no',
            'driver' => 'Driver',
            'ticket_date' => 'Ticket date',
            'driver_license' => 'Licence Number',
            'vehicle' => 'Vehicle',
            'birth_date' => 'Birth date',
            'customer_email' => 'Driver E-mail',
            'driver_address' => 'Driver Address',
            'driver_telephone' => 'Driver Telephone',
            'licence_issue_date' => 'Licence issue date',
            'licence_issue_place' => 'Licence issue place',
            'customer_paid' => 'Customer Paid',
            'agent' => 'Agent',
            'authority_email' => 'Authority Email',
            'driver_city' => 'Driver City',
            'driver_country' => 'Driver Country',
            'agr_no' => 'Rental agreement',
        ];
    }

    public static function normalizeHeader(string $header): string
    {
        $h = strtolower(trim((string) $header));
        $h = str_replace(['_', '.'], ' ', $h);
        $h = preg_replace('/\s+/u', ' ', $h) ?? $h;

        return trim($h);
    }

    public static function resolveColumn(string $header): ?string
    {
        $normalized = self::normalizeHeader($header);

        if (isset(self::NORMALIZED_HEADER_TO_COLUMN[$normalized])) {
            return self::NORMALIZED_HEADER_TO_COLUMN[$normalized];
        }

        $compact = preg_replace('/[^a-z0-9]/', '', $normalized);

        foreach (self::NORMALIZED_HEADER_TO_COLUMN as $key => $column) {
            if (preg_replace('/[^a-z0-9]/', '', $key) === $compact) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $rowData
     * @return array<string, mixed>
     */
    public static function mapRowToAttributes(array $rowData): array
    {
        $out = [];

        foreach ($rowData as $header => $value) {
            $column = self::resolveColumn((string) $header);
            if ($column === null) {
                continue;
            }

            $out[$column] = self::normalizeImportedValue($column, $value);
        }

        return $out;
    }

    /**
     * @param array<int, string> $headers
     * @return array<string, string> column => header
     */
    public static function mapHeadersToColumns(array $headers): array
    {
        $mapped = [];

        foreach ($headers as $header) {
            $column = self::resolveColumn((string) $header);
            if ($column === null) {
                continue;
            }

            $mapped[$column] = (string) $header;
        }

        return $mapped;
    }

    private static function stringifyExcelValue(int|float $value): string
    {
        if (is_int($value) || $value == floor($value)) {
            return (string) (int) $value;
        }

        return rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
    }

    private static function normalizeImportedValue(string $column, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '') {
                return null;
            }

            if (in_array($column, self::DATE_COLUMNS, true) && is_numeric($trimmed)) {
                try {
                    return ExcelDate::excelToDateTimeObject((float) $trimmed)->format('Y-m-d');
                } catch (\Throwable) {
                    return $trimmed;
                }
            }

            return $trimmed;
        }

        if (is_float($value) || is_int($value)) {
            if (in_array($column, self::DATE_COLUMNS, true)) {
                try {
                    return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
                } catch (\Throwable) {
                    return self::stringifyExcelValue($value);
                }
            }

            return self::stringifyExcelValue($value);
        }

        return trim((string) $value) ?: null;
    }
}
