<?php

namespace App\Filament\Pages;

use App\Models\EmailSetting;
use App\Models\FineProcessLog;
use App\Support\ActivityLogger;
use App\Support\WheelsysImportVerifier;
use App\Support\WheelsysLoginBridge;
use App\Support\WheelsysTrafficViolationExcelExporter;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Js;
use Throwable;

class WheelsExcelExport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationLabel = 'Wheelsys import';

    protected static ?string $navigationGroup = 'Fines';

    protected static ?string $title = 'Wheelsys import';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.wheels-excel-export';

    /** @var list<int|string> */
    public array $selectedIds = [];

    /** Filter by import file batch (empty = all). */
    public string $fileFilter = '';

    /** Client-side-ish search for the import-file dropdown. */
    public string $fileFilterQuery = '';

    /** ready | prepared | imported | missing | '' */
    public string $statusFilter = '';

    /** When false (default), hide fines already In Wheels. */
    public bool $showImported = false;

    public bool $showImportPicker = false;

    /** Selected batch inside the Start import picker. */
    public string $importPickerBatch = '';

    /** When false (default), Start import lists only files with fines still not In Wheels. */
    public bool $importPickerShowImported = false;

    /** Search text inside Start import file picker. */
    public string $importPickerQuery = '';

    public function mount(): void
    {
        $this->selectedIds = [];
        $this->fileFilter = '';
        $this->fileFilterQuery = '';
        $this->statusFilter = '';
        $this->showImported = false;
        $this->showImportPicker = false;
        $this->importPickerBatch = '';
        $this->importPickerShowImported = false;
        $this->importPickerQuery = '';
    }

    public function updatedFileFilter(): void
    {
        $this->selectedIds = [];
    }

    public function updatedStatusFilter(): void
    {
        $this->selectedIds = [];
    }

    public function updatedShowImported(): void
    {
        $this->selectedIds = [];
        if (! $this->showImported && $this->statusFilter === 'imported') {
            $this->statusFilter = '';
        }
    }

    public function showAllFines(): void
    {
        $this->showImported = true;
        $this->selectedIds = [];
    }

    public function showUnimportedOnly(): void
    {
        $this->showImported = false;
        if ($this->statusFilter === 'imported') {
            $this->statusFilter = '';
        }
        $this->selectedIds = [];
    }

    public function updatedImportPickerShowImported(): void
    {
        $this->syncImportPickerBatch();
    }

    public function enableImportedFiles(): void
    {
        $this->importPickerShowImported = true;
        $this->syncImportPickerBatch();
    }

    public function updatedImportPickerQuery(): void
    {
        $this->syncImportPickerBatch();
    }

    private function syncImportPickerBatch(): void
    {
        $choices = $this->filteredImportFileChoices;
        if ($this->importPickerBatch !== '' && ! isset($choices[$this->importPickerBatch])) {
            $this->importPickerBatch = $choices === [] ? '' : (string) array_key_first($choices);
        }
    }

    public function clearFilters(): void
    {
        $this->fileFilter = '';
        $this->fileFilterQuery = '';
        $this->statusFilter = '';
        // Keep showImported as the user left it — only clear file/status picks.
        $this->selectedIds = [];
    }

    /**
     * @return array<string, string>
     */
    public function getFilteredFileFilterOptionsProperty(): array
    {
        $options = $this->fileFilterOptions;
        $q = trim(mb_strtolower($this->fileFilterQuery));
        if ($q === '') {
            return $options;
        }

        $filtered = array_filter(
            $options,
            static function (string $label, string $value) use ($q): bool {
                if ($value === '') {
                    return true;
                }

                return str_contains(mb_strtolower($label), $q);
            },
            ARRAY_FILTER_USE_BOTH
        );

        return $filtered !== [] ? $filtered : ['' => 'All import files'];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getFilteredImportFileChoicesProperty(): array
    {
        $choices = $this->importFileChoices;
        $q = trim(mb_strtolower($this->importPickerQuery));
        if ($q === '') {
            return $choices;
        }

        return array_filter(
            $choices,
            static function (array $choice) use ($q): bool {
                $hay = mb_strtolower(($choice['label'] ?? '').' '.($choice['file_name'] ?? ''));

                return str_contains($hay, $q);
            }
        );
    }

    /**
     * All fines with plate + driver (from New fines Find driver).
     *
     * @return list<FineProcessLog>
     */
    public function getLogsProperty(): array
    {
        $query = FineProcessLog::query()
            ->whereNotNull('vehicle')
            ->where('vehicle', '!=', '')
            ->whereNotNull('driver_name')
            ->where('driver_name', '!=', '')
            ->where(function ($q): void {
                $q->whereNull('wheels_export_status')
                    ->orWhere('wheels_export_status', '!=', FineProcessLog::WHEELS_EXPORT_SKIPPED);
            });

        if ($this->fileFilter !== '') {
            $query->where('wheels_export_batch', $this->fileFilter);
        }

        if ($this->statusFilter === 'ready') {
            $query->where(function ($q): void {
                $q->whereNull('wheels_export_status')
                    ->orWhere('wheels_export_status', FineProcessLog::WHEELS_EXPORT_PENDING);
            });
        } elseif ($this->statusFilter === 'prepared') {
            $query->where('wheels_export_status', FineProcessLog::WHEELS_EXPORT_EXPORTED)
                ->where(function ($q): void {
                    $q->whereNull('wheels_import_status')
                        ->orWhere('wheels_import_status', FineProcessLog::WHEELS_IMPORT_AWAITING);
                });
        } elseif ($this->statusFilter === 'missing') {
            $query->where('wheels_import_status', FineProcessLog::WHEELS_IMPORT_MISSING);
        } elseif ($this->statusFilter === 'imported') {
            $query->where('wheels_import_status', FineProcessLog::WHEELS_IMPORT_IMPORTED);
        } elseif (! $this->showImported) {
            $query->where(function ($q): void {
                $q->whereNull('wheels_import_status')
                    ->orWhere('wheels_import_status', '!=', FineProcessLog::WHEELS_IMPORT_IMPORTED);
            });
        }

        return $query
            ->with('authority')
            ->orderByDesc('id')
            ->limit(400)
            ->get()
            ->all();
    }

    /**
     * Distinct import files for the filter dropdown.
     *
     * @return array<string, string> batch => label
     */
    public function getFileFilterOptionsProperty(): array
    {
        $rows = FineProcessLog::query()
            ->whereNotNull('wheels_export_batch')
            ->where('wheels_export_batch', '!=', '')
            ->orderByDesc('wheels_exported_at')
            ->get(['wheels_export_batch', 'wheels_export_file_name', 'wheels_exported_at'])
            ->unique('wheels_export_batch')
            ->values();

        $options = ['' => 'All import files'];
        foreach ($rows as $row) {
            $batch = (string) $row->wheels_export_batch;
            $name = (string) ($row->wheels_export_file_name ?: ('wheels-export-'.$batch.'.xlsx'));
            $when = optional($row->wheels_exported_at)->timezone(config('app.timezone'))?->format('Y-m-d H:i');
            $options[$batch] = $when ? $name.' · '.$when : $name;
        }

        return $options;
    }

    public function rowIsSelectable(FineProcessLog $log): bool
    {
        return $log->wheels_export_status === FineProcessLog::WHEELS_EXPORT_PENDING
            || $log->wheels_export_status === null
            || $log->wheels_export_status === '';
    }

    public function rowStatusLabel(FineProcessLog $log): string
    {
        if ($log->wheels_import_status === FineProcessLog::WHEELS_IMPORT_IMPORTED) {
            return 'In Wheels';
        }
        if ($log->wheels_import_status === FineProcessLog::WHEELS_IMPORT_MISSING) {
            return 'Missing in Wheels';
        }
        if ($log->wheels_export_status === FineProcessLog::WHEELS_EXPORT_EXPORTED) {
            return 'In import file';
        }

        return 'Ready';
    }

    public function toggleSelectAll(): void
    {
        $all = [];
        foreach ($this->logs as $log) {
            if ($this->rowIsSelectable($log)) {
                $all[] = (int) $log->id;
            }
        }
        $selected = array_values(array_map('intval', $this->selectedIds));
        sort($selected);
        $sortedAll = $all;
        sort($sortedAll);

        $this->selectedIds = ($all !== [] && $selected === $sortedAll) ? [] : $all;
    }

    public function generateImportFile()
    {
        $ids = $this->selectedReadyIds();
        if ($ids === []) {
            Notification::make()->warning()->title('Select ready fines first')->send();

            return null;
        }

        $logs = FineProcessLog::query()
            ->whereIn('id', $ids)
            ->where(function ($q): void {
                $q->whereNull('wheels_export_status')
                    ->orWhere('wheels_export_status', FineProcessLog::WHEELS_EXPORT_PENDING);
            })
            ->whereNotNull('vehicle')
            ->where('vehicle', '!=', '')
            ->whereNotNull('driver_name')
            ->where('driver_name', '!=', '')
            ->orderBy('id')
            ->get();

        if ($logs->isEmpty()) {
            Notification::make()->warning()->title('No ready fines left to include')->send();

            return null;
        }

        // Ensure pending status before export.
        foreach ($logs as $log) {
            if ($log->wheels_export_status !== FineProcessLog::WHEELS_EXPORT_PENDING) {
                $log->fill(['wheels_export_status' => FineProcessLog::WHEELS_EXPORT_PENDING])->save();
            }
        }

        try {
            $result = app(WheelsysTrafficViolationExcelExporter::class)
                ->exportUploadAndMark($logs, Auth::id());
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Generate import file failed')->body($e->getMessage())->send();

            return null;
        }

        @unlink($result['path']);

        $this->selectedIds = [];
        $this->fileFilter = (string) $result['batch'];
        $this->statusFilter = 'prepared';

        Notification::make()
            ->success()
            ->title('Import file ready')
            ->body(($result['file_name'] ?? $result['batch']).' · '.$result['count'].' fine(s) · Start import when ready')
            ->send();

        if (filled($result['sharepoint_url'] ?? null)) {
            $this->js('window.open('.Js::from($result['sharepoint_url']).', "_blank")');
        }

        return null;
    }

    /**
     * Open intermediate picker: choose import file, preview fines, then Import.
     */
    public function startImport(): void
    {
        $this->importPickerShowImported = false;

        $anyPrepared = FineProcessLog::query()
            ->where('wheels_export_status', FineProcessLog::WHEELS_EXPORT_EXPORTED)
            ->whereNotNull('wheels_export_batch')
            ->where('wheels_export_batch', '!=', '')
            ->exists();
        if (! $anyPrepared) {
            Notification::make()
                ->warning()
                ->title('No import files yet')
                ->body('Generate an import file first.')
                ->send();

            return;
        }

        $preselect = $this->fileFilter;
        if ($preselect === '') {
            $ids = array_values(array_filter(array_map('intval', $this->selectedIds)));
            if ($ids !== []) {
                $preselect = (string) (FineProcessLog::query()
                    ->whereIn('id', $ids)
                    ->whereNotNull('wheels_export_batch')
                    ->orderByDesc('wheels_exported_at')
                    ->value('wheels_export_batch') ?? '');
            }
        }

        $visible = $this->importFileChoices;
        if ($preselect === '' || ! isset($visible[$preselect])) {
            $preselect = $visible === [] ? '' : (string) array_key_first($visible);
        }

        $this->importPickerBatch = $preselect;
        $this->showImportPicker = true;
    }

    public function closeImportPicker(): void
    {
        $this->showImportPicker = false;
    }

    /**
     * @return array<string, array{
     *     batch: string,
     *     file_name: string,
     *     sharepoint_url: ?string,
     *     prepared_at: ?string,
     *     count: int,
     *     imported_count: int,
     *     fully_imported: bool,
     *     label: string
     * }>
     */
    public function getImportFileChoicesProperty(): array
    {
        $rows = FineProcessLog::query()
            ->where('wheels_export_status', FineProcessLog::WHEELS_EXPORT_EXPORTED)
            ->whereNotNull('wheels_export_batch')
            ->where('wheels_export_batch', '!=', '')
            ->orderByDesc('wheels_exported_at')
            ->get([
                'wheels_export_batch',
                'wheels_export_file_name',
                'wheels_export_sharepoint_url',
                'wheels_exported_at',
            ]);

        $choices = [];
        foreach ($rows as $row) {
            $batch = (string) $row->wheels_export_batch;
            if (isset($choices[$batch])) {
                $choices[$batch]['count']++;

                continue;
            }

            $fileName = (string) ($row->wheels_export_file_name ?: ('wheels-export-'.$batch.'.xlsx'));
            $preparedAt = optional($row->wheels_exported_at)->timezone(config('app.timezone'))?->format('Y-m-d H:i');

            $choices[$batch] = [
                'batch' => $batch,
                'file_name' => $fileName,
                'sharepoint_url' => $row->wheels_export_sharepoint_url ?: null,
                'prepared_at' => $preparedAt,
                'count' => 1,
                'imported_count' => 0,
                'fully_imported' => false,
                'label' => $fileName,
            ];
        }

        foreach ($choices as $batch => &$choice) {
            $imported = FineProcessLog::query()
                ->where('wheels_export_batch', $batch)
                ->where('wheels_import_status', FineProcessLog::WHEELS_IMPORT_IMPORTED)
                ->count();
            $choice['imported_count'] = $imported;
            $choice['fully_imported'] = $choice['count'] > 0 && $imported >= $choice['count'];
            $bits = [$choice['file_name']];
            if ($choice['prepared_at']) {
                $bits[] = $choice['prepared_at'];
            }
            $bits[] = $choice['count'].' fine(s)';
            if ($choice['fully_imported']) {
                $bits[] = 'imported';
            } elseif ($imported > 0) {
                $bits[] = $imported.'/'.$choice['count'].' in Wheels';
            }
            $choice['label'] = implode(' · ', $bits);
        }
        unset($choice);

        if (! $this->importPickerShowImported) {
            $choices = array_filter(
                $choices,
                static fn (array $choice): bool => ! $choice['fully_imported']
            );
        }

        return $choices;
    }

    /**
     * @return array{total: int, imported: int, already_imported: bool}
     */
    public function getImportPickerStatsProperty(): array
    {
        $logs = $this->importPreviewLogs;
        $imported = 0;
        foreach ($logs as $log) {
            if ($log->wheels_import_status === FineProcessLog::WHEELS_IMPORT_IMPORTED) {
                $imported++;
            }
        }
        $total = count($logs);

        return [
            'total' => $total,
            'imported' => $imported,
            'already_imported' => $total > 0 && $imported > 0,
        ];
    }

    /**
     * Fines inside the currently selected picker file.
     *
     * @return list<FineProcessLog>
     */
    public function getImportPreviewLogsProperty(): array
    {
        if ($this->importPickerBatch === '') {
            return [];
        }

        return FineProcessLog::query()
            ->where('wheels_export_batch', $this->importPickerBatch)
            ->orderBy('id')
            ->limit(300)
            ->get()
            ->all();
    }

    public function getImportPickerSelectedFileProperty(): ?array
    {
        return $this->importFileChoices[$this->importPickerBatch] ?? null;
    }

    /**
     * Confirm from picker: download import file + open Wheels Import.
     */
    public function confirmImport()
    {
        $file = $this->importPickerSelectedFile;
        if ($file === null) {
            Notification::make()->warning()->title('Choose an import file')->send();

            return null;
        }

        $fileName = (string) ($file['file_name'] ?? '');
        if ($fileName === '') {
            Notification::make()->warning()->title('Import file name missing')->send();

            return null;
        }

        $stats = $this->importPickerStats;
        if ($stats['already_imported']) {
            Notification::make()
                ->warning()
                ->title('File was already imported')
                ->body($stats['imported'].' of '.$stats['total'].' fine(s) are already In Wheels. Re-import may create duplicates — continue carefully.')
                ->persistent()
                ->send();
        }

        try {
            $contents = app(WheelsysTrafficViolationExcelExporter::class)
                ->downloadImportFile($fileName);
        } catch (Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Download failed')
                ->body($e->getMessage())
                ->send();

            return null;
        }

        // Wheelsys tab is opened synchronously from the Import button click (avoids popup blockers).
        // This action only prepares the Excel download.

        $this->showImportPicker = false;
        $this->fileFilter = $this->importPickerBatch;

        $autoUser = EmailSetting::wheelsysConfig()['username'];
        Notification::make()
            ->success()
            ->title('Downloading · Wheels Import opening')
            ->body(
                $fileName.' · '.$file['count'].' fine(s) · SSO as '
                .($autoUser !== '' ? $autoUser : 'VIO automation')
                .' · then Traffic violation → Select import.'
            )
            ->send();

        $tempPath = storage_path('app/private/'.uniqid('wheels-import-', true).'.xlsx');
        if (! is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0775, true);
        }
        file_put_contents($tempPath, $contents);

        return response()->download($tempPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function wheelsysImportSsoUrl(): string
    {
        $importPath = WheelsysLoginBridge::sanitizeReturnUrl((string) config(
            'services.wheelsys.import_path',
            WheelsysLoginBridge::IMPORT_DATA_PATH
        ));

        // Path-only — stay on the browser's current host/port (APP_URL may differ).
        return '/wheelsys/open?'.http_build_query(['return' => $importPath]);
    }

    public function verifyInWheelsys(): void
    {
        $ids = array_values(array_filter(array_map('intval', $this->selectedIds)));

        $query = FineProcessLog::query()
            ->where('wheels_export_status', FineProcessLog::WHEELS_EXPORT_EXPORTED)
            ->where(function ($q): void {
                $q->whereNull('wheels_import_status')
                    ->orWhere('wheels_import_status', FineProcessLog::WHEELS_IMPORT_AWAITING)
                    ->orWhere('wheels_import_status', FineProcessLog::WHEELS_IMPORT_MISSING);
            });

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        } elseif ($this->fileFilter !== '') {
            $query->where('wheels_export_batch', $this->fileFilter);
        }

        $logs = $query->orderBy('id')->limit(100)->get();

        if ($logs->isEmpty()) {
            Notification::make()
                ->warning()
                ->title('Nothing to update')
                ->body('Select prepared fines, or filter by import file.')
                ->send();

            return;
        }

        try {
            $result = app(WheelsysImportVerifier::class)->verifyMany($logs, Auth::id());
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Update Wheelsys status failed')->body($e->getMessage())->send();

            return;
        }

        ActivityLogger::log('Wheels: verify imports', Auth::user(), null, [
            'verified' => $result['verified'],
            'missing' => $result['missing'],
            'skipped' => $result['skipped'],
            'checked' => $logs->count(),
        ]);

        $this->selectedIds = [];

        $body = $result['verified'].' in Wheels · '.$result['missing'].' missing · '.$result['skipped'].' skipped';
        $notification = Notification::make()->title('Wheelsys status updated')->body($body);
        if ($result['verified'] > 0 && $result['missing'] === 0) {
            $notification->success();
        } else {
            $notification->warning();
        }
        $notification->send();
    }

    /**
     * @return list<int>
     */
    private function selectedReadyIds(): array
    {
        $ready = [];
        foreach ($this->logs as $log) {
            if ($this->rowIsSelectable($log)) {
                $ready[(int) $log->id] = true;
            }
        }

        return array_values(array_filter(
            array_map('intval', $this->selectedIds),
            static fn (int $id): bool => isset($ready[$id])
        ));
    }
}
