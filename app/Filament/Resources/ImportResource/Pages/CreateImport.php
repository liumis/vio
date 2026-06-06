<?php

namespace App\Filament\Resources\ImportResource\Pages;

use App\Filament\Resources\ImportResource;
use App\Models\Violation;
use App\Support\ViolationImportMapping;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class CreateImport extends CreateRecord
{
    protected static string $resource = ImportResource::class;

    /**
     * @var array<int, array{row_number: int, row_data: array<string, mixed>}>
     */
    private array $rowsToImport = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $extracted = $this->extractRowsFromExcel($data['file_path']);
        $this->validateRequiredImportColumns($extracted['headers'], $extracted['rows']);
        $this->rowsToImport = $extracted['rows'];
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

        // Keep DB metadata only; remove uploaded source file from server.
        Storage::disk('local')->delete($this->record->file_path);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Import created')
            ->body("{$this->record->imported_rows} rows imported into violations.");
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
     * @param array<int, string> $headers
     * @param array<int, array{row_number: int, row_data: array<string, mixed>}> $rows
     */
    private function validateRequiredImportColumns(array $headers, array $rows): void
    {
        $mappedHeaders = ViolationImportMapping::mapHeadersToColumns($headers);
        $missingColumns = array_values(array_filter(
            ViolationImportMapping::REQUIRED_COLUMNS,
            fn (string $column): bool => ! isset($mappedHeaders[$column])
        ));

        if ($missingColumns !== []) {
            throw ValidationException::withMessages([
                'data.file_path' => 'Missing required Excel columns: '.implode(', ', $missingColumns),
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
                'data.file_path' => "Required mapped fields contain empty values: {$details}.",
            ]);
        }
    }
}
