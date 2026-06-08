<?php

namespace App\Filament\Resources\ImportResource\Pages;

use App\Filament\Resources\ImportResource;
use App\Models\Violation;
use App\Support\ViolationBirthDateMerger;
use App\Support\ViolationImportMapping;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class CreateImport extends CreateRecord
{
    protected static string $resource = ImportResource::class;

    /**
     * @var array<int, array{row_number: int, row_data: array<string, mixed>}>
     */
    private array $rowsToImport = [];

    private int $missingBirthDateCount = 0;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        $primaryExtracted = $this->extractRowsFromExcel($data['file_path']);
        $this->validateRequiredImportColumns($primaryExtracted['headers'], $primaryExtracted['rows'], 'data.file_path');

        $birthDateExtracted = $this->extractRowsFromExcel($data['birth_date_file_path']);
        $this->validateBirthDateFileColumns($birthDateExtracted['headers'], $birthDateExtracted['rows']);

        $birthDateLookup = ViolationBirthDateMerger::buildBirthDateLookup($birthDateExtracted['rows']);
        $this->rowsToImport = $this->mergeBirthDatesIntoRows($primaryExtracted['rows'], $birthDateLookup);

        $data['imported_rows'] = count($this->rowsToImport);

        return $data;
    }

    protected function afterCreate(): void
    {
        foreach ($this->rowsToImport as $row) {
            $mapped = ViolationImportMapping::mapRowToAttributes($row['row_data']);
            Violation::query()->create(array_merge($mapped, [
                'import_id' => $this->record->id,
                'user_id' => auth()->id(),
                'row_number' => $row['row_number'],
                'row_data' => [],
            ]));
        }

        Storage::disk('local')->delete([
            $this->record->file_path,
            $this->record->birth_date_file_path,
        ]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        $body = "{$this->record->imported_rows} rows imported into violations.";

        if ($this->missingBirthDateCount > 0) {
            $body .= " {$this->missingBirthDateCount} rows without birth date (shown in red in the violations list).";
        }

        return Notification::make()
            ->success()
            ->title('Import created')
            ->body($body);
    }

    /**
     * @return array{
     *     headers: array<int, string>,
     *     rows: array<int, array{row_number: int, row_data: array<string, mixed>}>
     * }
     */
    private function extractRowsFromExcel(string $filePath): array
    {
        $absolutePath = Storage::disk('local')->path($filePath);
        $sheets = Excel::toArray([], $absolutePath);

        $rows = [];
        $headers = [];

        foreach ($sheets as $sheet) {
            if (count($sheet) < 2) {
                continue;
            }

            $sheetHeaders = collect($sheet[0])
                ->map(fn ($header, $index) => filled($header) ? (string) $header : "column_{$index}")
                ->values()
                ->all();

            if ($headers === []) {
                $headers = $sheetHeaders;
            }

            foreach (array_slice($sheet, 1) as $index => $row) {
                $rowData = collect($sheetHeaders)
                    ->mapWithKeys(fn ($header, $columnIndex) => [$header => $row[$columnIndex] ?? null])
                    ->all();

                if (collect($rowData)->filter(fn ($value) => filled($value))->isNotEmpty()) {
                    $rows[] = [
                        'row_number' => $index + 2,
                        'row_data' => $rowData,
                    ];
                }
            }
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array{row_number: int, row_data: array<string, mixed>}>  $rows
     */
    private function validateRequiredImportColumns(array $headers, array $rows, string $errorField): void
    {
        $mappedHeaders = ViolationImportMapping::mapHeadersToColumns($headers);
        $missingColumns = array_values(array_filter(
            ViolationImportMapping::REQUIRED_COLUMNS,
            fn (string $column): bool => ! isset($mappedHeaders[$column])
        ));

        if ($missingColumns !== []) {
            throw ValidationException::withMessages([
                $errorField => 'Missing required Excel columns: '.implode(', ', $missingColumns),
            ]);
        }

        $emptyCounters = array_fill_keys(ViolationImportMapping::REQUIRED_COLUMNS, 0);

        foreach ($rows as $row) {
            $mapped = ViolationImportMapping::mapRowToAttributes($row['row_data']);

            foreach (ViolationImportMapping::REQUIRED_COLUMNS as $column) {
                if (! filled($mapped[$column] ?? null)) {
                    $emptyCounters[$column]++;
                }
            }
        }

        $emptyColumns = array_keys(array_filter($emptyCounters, fn (int $count): bool => $count > 0));
        if ($emptyColumns !== []) {
            $details = collect($emptyColumns)
                ->map(fn (string $column): string => "{$column} ({$emptyCounters[$column]} empty)")
                ->implode(', ');

            throw ValidationException::withMessages([
                $errorField => "Required mapped fields contain empty values: {$details}.",
            ]);
        }
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array{row_number: int, row_data: array<string, mixed>}>  $rows
     */
    private function validateBirthDateFileColumns(array $headers, array $rows): void
    {
        $mappedHeaders = ViolationImportMapping::mapHeadersToColumns($headers);
        $requiredColumns = array_merge(
            ViolationBirthDateMerger::MERGE_KEY_COLUMNS,
            ['birth_date'],
        );
        $missingColumns = array_values(array_filter(
            $requiredColumns,
            fn (string $column): bool => ! isset($mappedHeaders[$column])
        ));

        if ($missingColumns !== []) {
            throw ValidationException::withMessages([
                'data.birth_date_file_path' => 'Missing required Excel columns: '.implode(', ', $missingColumns),
            ]);
        }

        if ($rows === []) {
            throw ValidationException::withMessages([
                'data.birth_date_file_path' => 'Birth date file contains no data rows.',
            ]);
        }
    }

    /**
     * @param  array<int, array{row_number: int, row_data: array<string, mixed>}>  $rows
     * @param  array<string, string>  $birthDateLookup
     * @return array<int, array{row_number: int, row_data: array<string, mixed>}>
     */
    private function mergeBirthDatesIntoRows(array $rows, array $birthDateLookup): array
    {
        $mergedRows = [];

        foreach ($rows as $row) {
            $mapped = ViolationImportMapping::mapRowToAttributes($row['row_data']);
            $mapped = ViolationBirthDateMerger::applyBirthDate($mapped, $birthDateLookup);

            $rowData = $row['row_data'];

            if (filled($mapped['birth_date'] ?? null)) {
                $birthDateHeader = $this->resolveHeaderForColumn($rowData, 'birth_date');

                if ($birthDateHeader !== null) {
                    $rowData[$birthDateHeader] = $mapped['birth_date'];
                } else {
                    $rowData['Birth date'] = $mapped['birth_date'];
                }
            } else {
                $this->missingBirthDateCount++;
            }

            $mergedRows[] = [
                'row_number' => $row['row_number'],
                'row_data' => $rowData,
            ];
        }

        return $mergedRows;
    }

    /**
     * @param  array<string, mixed>  $rowData
     */
    private function resolveHeaderForColumn(array $rowData, string $column): ?string
    {
        foreach (array_keys($rowData) as $header) {
            if (ViolationImportMapping::resolveColumn((string) $header) === $column) {
                return (string) $header;
            }
        }

        return null;
    }
}
