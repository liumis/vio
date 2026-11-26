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

    protected static ?string $navigationLabel = '2. To Wheelsys';

    protected static ?string $navigationGroup = 'Fines';

    protected static ?string $title = 'To Wheelsys';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.wheels-excel-export';

    /** @var list<int|string> */
    public array $selectedIds = [];

    /**
     * Filter by import file batch keys (empty = all).
     *
     * @var list<string>
     */
    public array $fileFilters = [];

    /**
     * ready | prepared | missing (empty = any within the active tab).
     *
     * @var list<string>
     */
    public array $statusFilters = [];

    /**
     * Main list mode: awaiting (default) | imported.
     */
    public string $listTab = 'awaiting';

    public bool $showImportPicker = false;

    /** Selected batch inside the Start import picker. */
    public string $importPickerBatch = '';

    /** When false (default), Start import lists only files with fines still not In Wheels. */
    public bool $importPickerShowImported = false;

    /** Search text inside Start import file picker. */
    public string $importPickerQuery = '';

    /**
     * Expanded accordion keys (`__new__` or wheels_export_batch).
     *
     * @var list<string>
     */
    public array $openBatches = ['__new__'];

    public function mount(): void
    {
        $this->selectedIds = [];
        $this->fileFilters = [];
        $this->statusFilters = [];
        $this->listTab = 'awaiting';
        $this->showImportPicker = false;
        $this->importPickerBatch = '';
        $this->importPickerShowImported = false;
        $this->importPickerQuery = '';
        $this->openBatches = ['__new__'];
    }

    public function updatedFileFilters(): void
    {
        $this->selectedIds = [];
        $this->fileFilters = array_values(array_filter(array_map('strval', $this->fileFilters)));
        if ($this->fileFilters !== []) {
            $this->openBatches = $this->fileFilters;
        } elseif ($this->listTab === 'awaiting' && ! in_array('__new__', $this->openBatches, true)) {
            $this->openBatches = ['__new__'];
        }
    }

    public function updatedStatusFilters(): void
    {
        $this->selectedIds = [];
        $this->statusFilters = array_values(array_filter(
            array_map('strval', $this->statusFilters),
            static fn (string $s): bool => in_array($s, ['ready', 'prepared', 'missing'], true)
        ));
    }

    public function setListTab(string $tab): void
    {
        $tab = $tab === 'imported' ? 'imported' : 'awaiting';
        if ($this->listTab === $tab) {
            return;
        }

        $this->listTab = $tab;
        $this->selectedIds = [];
        $this->statusFilters = [];

        if ($tab === 'awaiting') {
            $this->openBatches = $this->fileFilters !== [] ? $this->fileFilters : ['__new__'];
        } elseif ($this->fileFilters !== []) {
            $this->openBatches = $this->fileFilters;
        } else {
            // Open newest import day with all files expanded.
            $firstDay = $this->logDayGroups[0] ?? null;
            if ($firstDay === null) {
                $this->openBatches = [];
            } else {
                $keys = [(string) $firstDay['key']];
                foreach ($firstDay['files'] as $file) {
                    $fileKey = (string) ($file['key'] ?? '');
                    if ($fileKey !== '') {
                        $keys[] = $fileKey;
                    }
                }
                $this->openBatches = $keys;
            }
        }
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
        $this->fileFilters = [];
        $this->statusFilters = [];
        // Keep listTab as the user left it — only clear file/status picks.
        $this->selectedIds = [];
        $this->openBatches = $this->listTab === 'awaiting' ? ['__new__'] : [];
    }

    /**
     * Options for the Import file multi-select (no "All" row — empty selection = all).
     *
     * @return array<string, string>
     */
    public function getFileMultiSelectOptionsProperty(): array
    {
        $options = $this->fileFilterOptions;
        unset($options['']);

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public function getStatusMultiSelectOptionsProperty(): array
    {
        // Status chips only refine the Awaiting tab (Imported tab is already filtered).
        return [
            'ready' => 'Ready',
            'prepared' => 'In import file',
            'missing' => 'Missing in Wheels',
        ];
    }

    /**
     * Badge counts for the Awaiting / Imported tabs (respects Import file filter).
     *
     * @return array{awaiting: int, imported: int}
     */
    public function getTabCountsProperty(): array
    {
        $base = FineProcessLog::query()
            ->whereNotNull('vehicle')
            ->where('vehicle', '!=', '')
            ->whereNotNull('driver_name')
            ->where('driver_name', '!=', '')
            ->where(function ($q): void {
                $q->whereNull('wheels_export_status')
                    ->orWhereNotIn('wheels_export_status', [
                        FineProcessLog::WHEELS_EXPORT_SKIPPED,
                        FineProcessLog::WHEELS_EXPORT_NEEDS_REVIEW,
                    ]);
            });

        if ($this->fileFilters !== []) {
            $base->whereIn('wheels_export_batch', $this->fileFilters);
        }

        $imported = (clone $base)
            ->where('wheels_import_status', FineProcessLog::WHEELS_IMPORT_IMPORTED)
            ->count();

        $awaiting = (clone $base)
            ->where(function ($q): void {
                $q->whereNull('wheels_import_status')
                    ->orWhere('wheels_import_status', '!=', FineProcessLog::WHEELS_IMPORT_IMPORTED);
            })
            ->count();

        return [
            'awaiting' => $awaiting,
            'imported' => $imported,
        ];
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
                    ->orWhereNotIn('wheels_export_status', [
                        FineProcessLog::WHEELS_EXPORT_SKIPPED,
                        FineProcessLog::WHEELS_EXPORT_NEEDS_REVIEW,
                    ]);
            });

        if ($this->fileFilters !== []) {
            $query->whereIn('wheels_export_batch', $this->fileFilters);
        }

        if ($this->listTab === 'imported') {
            $query->where('wheels_import_status', FineProcessLog::WHEELS_IMPORT_IMPORTED);
        } else {
            $query->where(function ($q): void {
                $q->whereNull('wheels_import_status')
                    ->orWhere('wheels_import_status', '!=', FineProcessLog::WHEELS_IMPORT_IMPORTED);
            });

            if ($this->statusFilters !== []) {
                $query->where(function ($outer) {
                    foreach ($this->statusFilters as $status) {
                        $outer->orWhere(function ($q) use ($status): void {
                            if ($status === 'ready') {
                                $q->where(function ($inner): void {
                                    $inner->whereNull('wheels_export_status')
                                        ->orWhere('wheels_export_status', FineProcessLog::WHEELS_EXPORT_PENDING);
                                });
                            } elseif ($status === 'prepared') {
                                $q->where('wheels_export_status', FineProcessLog::WHEELS_EXPORT_EXPORTED)
                                    ->where(function ($inner): void {
                                        $inner->whereNull('wheels_import_status')
                                            ->orWhere('wheels_import_status', FineProcessLog::WHEELS_IMPORT_AWAITING);
                                    });
                            } elseif ($status === 'missing') {
                                $q->where('wheels_import_status', FineProcessLog::WHEELS_IMPORT_MISSING);
                            }
                        });
                    }
                });
            }
        }

        return $query
            ->with('authority')
            ->orderByDesc('id')
            ->limit(400)
            ->get()
            ->all();
    }

    /**
     * Accordion groups: new (not in a file) first, then each import file (newest first).
     * Used by the Awaiting tab (flat file list).
     *
     * @return list<array{
     *     key: string,
     *     title: string,
     *     subtitle: string|null,
     *     sharepoint_url: string|null,
     *     is_new: bool,
     *     import_status: 'new'|'imported'|'not_imported'|'partial',
     *     import_label: string,
     *     imported_at_label: string|null,
     *     logs: list<FineProcessLog>
     * }>
     */
    public function getLogGroupsProperty(): array
    {
        $built = $this->buildFileGroupsFromLogs($this->logs);
        $groups = [];

        // "New" only on Awaiting — Imported fines are always tied to an import file.
        if ($this->listTab === 'awaiting' && $this->fileFilters === []) {
            $groups[] = [
                'key' => '__new__',
                'title' => 'New · not in a file yet',
                'subtitle' => $built['newLogs'] === []
                    ? 'None waiting'
                    : count($built['newLogs']).' ready for Start import',
                'sharepoint_url' => null,
                'is_new' => true,
                'import_status' => 'new',
                'import_label' => 'Not in file',
                'imported_at_label' => null,
                'logs' => $built['newLogs'],
            ];
        }

        foreach ($built['files'] as $batch => $file) {
            $groups[] = $this->makeFileGroupPayload($batch, $file);
        }

        return $groups;
    }

    /**
     * Imported tab: accordion by import day → nested accordion by import file.
     *
     * @return list<array{
     *     key: string,
     *     title: string,
     *     subtitle: string,
     *     imported_at_label: string|null,
     *     fine_count: int,
     *     file_count: int,
     *     files: list<array<string, mixed>>
     * }>
     */
    public function getLogDayGroupsProperty(): array
    {
        $built = $this->buildFileGroupsFromLogs($this->logs);
        /** @var array<string, array{files: list<array<string, mixed>>, fine_count: int, sort: string}> $days */
        $days = [];

        foreach ($built['files'] as $batch => $file) {
            $fileGroup = $this->makeFileGroupPayload($batch, $file);
            $dayKey = $fileGroup['imported_at_label'] !== null
                ? substr($fileGroup['imported_at_label'], 0, 10)
                : 'unknown';

            if (! isset($days[$dayKey])) {
                $days[$dayKey] = [
                    'files' => [],
                    'fine_count' => 0,
                    'sort' => $dayKey === 'unknown' ? '0000-00-00' : $dayKey,
                ];
            }
            $days[$dayKey]['files'][] = $fileGroup;
            $days[$dayKey]['fine_count'] += count($fileGroup['logs']);
        }

        uasort($days, static fn (array $a, array $b): int => strcmp($b['sort'], $a['sort']));

        $out = [];
        foreach ($days as $dayKey => $day) {
            $fileCount = count($day['files']);
            $out[] = [
                'key' => 'day:'.$dayKey,
                'title' => $dayKey === 'unknown' ? 'No import date' : $dayKey,
                'subtitle' => $fileCount.' file(s) · '.$day['fine_count'].' fine(s)',
                'imported_at_label' => $dayKey === 'unknown' ? null : $dayKey,
                'fine_count' => $day['fine_count'],
                'file_count' => $fileCount,
                'files' => $day['files'],
            ];
        }

        return $out;
    }

    /**
     * @param  list<FineProcessLog>  $logs
     * @return array{
     *     newLogs: list<FineProcessLog>,
     *     files: array<string, array{title:string,subtitle:?string,sharepoint_url:?string,exported_at:?string,logs:list<FineProcessLog>}>
     * }
     */
    private function buildFileGroupsFromLogs(array $logs): array
    {
        $newLogs = [];
        /** @var array<string, array{title:string,subtitle:?string,sharepoint_url:?string,exported_at:?string,logs:list<FineProcessLog>}> $files */
        $files = [];

        foreach ($logs as $log) {
            $inFile = $log->wheels_export_status === FineProcessLog::WHEELS_EXPORT_EXPORTED
                && filled($log->wheels_export_batch);

            if (! $inFile) {
                $newLogs[] = $log;

                continue;
            }

            $batch = (string) $log->wheels_export_batch;
            if (! isset($files[$batch])) {
                $fileName = (string) ($log->wheels_export_file_name ?: ('wheels-export-'.$batch.'.xlsx'));
                $when = optional($log->wheels_exported_at)->timezone(config('app.timezone'))?->format('Y-m-d H:i');
                $files[$batch] = [
                    'title' => $fileName,
                    'subtitle' => $when,
                    'sharepoint_url' => $log->wheels_export_sharepoint_url ?: null,
                    'exported_at' => $log->wheels_exported_at?->toIso8601String(),
                    'logs' => [],
                ];
            }
            $files[$batch]['logs'][] = $log;
            if (! $files[$batch]['sharepoint_url'] && filled($log->wheels_export_sharepoint_url)) {
                $files[$batch]['sharepoint_url'] = $log->wheels_export_sharepoint_url;
            }
        }

        uasort($files, static function (array $a, array $b): int {
            return strcmp((string) ($b['exported_at'] ?? ''), (string) ($a['exported_at'] ?? ''));
        });

        return ['newLogs' => $newLogs, 'files' => $files];
    }

    /**
     * @param  array{title:string,subtitle:?string,sharepoint_url:?string,exported_at:?string,logs:list<FineProcessLog>}  $file
     * @return array{
     *     key: string,
     *     title: string,
     *     subtitle: string,
     *     imported_at_label: string|null,
     *     sharepoint_url: string|null,
     *     is_new: bool,
     *     import_status: 'imported'|'not_imported'|'partial',
     *     import_label: string,
     *     logs: list<FineProcessLog>
     * }
     */
    private function makeFileGroupPayload(string $batch, array $file): array
    {
        $importMeta = $this->fileImportStatus($file['logs']);
        $importedAtLabel = $importMeta['imported_at_label'];
        $subtitleParts = [];
        if ($file['subtitle']) {
            $subtitleParts[] = 'Prepared '.$file['subtitle'];
        }
        if ($importedAtLabel !== null) {
            $subtitleParts[] = 'Imported '.$importedAtLabel;
        }
        $subtitleParts[] = count($file['logs']).' fine(s)';

        return [
            'key' => $batch,
            'title' => $file['title'],
            'subtitle' => implode(' · ', $subtitleParts),
            'imported_at_label' => $importedAtLabel,
            'sharepoint_url' => $file['sharepoint_url'],
            'is_new' => false,
            'import_status' => $importMeta['status'],
            'import_label' => $importMeta['label'],
            'logs' => $file['logs'],
        ];
    }

    /**
     * @param  list<FineProcessLog>  $logs
     * @return array{status: 'imported'|'not_imported'|'partial', label: string, imported_at_label: string|null}
     */
    private function fileImportStatus(array $logs): array
    {
        $total = count($logs);
        if ($total === 0) {
            return ['status' => 'not_imported', 'label' => 'Not imported', 'imported_at_label' => null];
        }

        $imported = 0;
        $latestImportedAt = null;
        foreach ($logs as $log) {
            if ($log->wheels_import_status !== FineProcessLog::WHEELS_IMPORT_IMPORTED) {
                continue;
            }
            $imported++;
            $at = $log->wheels_imported_at;
            if ($at !== null && ($latestImportedAt === null || $at->gt($latestImportedAt))) {
                $latestImportedAt = $at;
            }
        }

        $importedAtLabel = $latestImportedAt
            ? $latestImportedAt->timezone(config('app.timezone'))->format('Y-m-d H:i')
            : null;

        if ($imported === $total) {
            return [
                'status' => 'imported',
                'label' => $importedAtLabel !== null ? 'Imported '.$importedAtLabel : 'Imported',
                'imported_at_label' => $importedAtLabel,
            ];
        }

        if ($imported === 0) {
            return ['status' => 'not_imported', 'label' => 'Not imported', 'imported_at_label' => null];
        }

        return [
            'status' => 'partial',
            'label' => $importedAtLabel !== null
                ? 'Partial · '.$imported.'/'.$total.' · '.$importedAtLabel
                : 'Partial · '.$imported.'/'.$total.' imported',
            'imported_at_label' => $importedAtLabel,
        ];
    }

    public function toggleBatch(string $key): void
    {
        if (in_array($key, $this->openBatches, true)) {
            $this->openBatches = array_values(array_filter(
                $this->openBatches,
                static fn (string $open): bool => $open !== $key
            ));

            return;
        }

        $this->openBatches[] = $key;

        // Expanding an import day → expand all files inside it.
        if (str_starts_with($key, 'day:')) {
            foreach ($this->logDayGroups as $day) {
                if ((string) ($day['key'] ?? '') !== $key) {
                    continue;
                }
                foreach ($day['files'] as $file) {
                    $fileKey = (string) ($file['key'] ?? '');
                    if ($fileKey !== '' && ! in_array($fileKey, $this->openBatches, true)) {
                        $this->openBatches[] = $fileKey;
                    }
                }
                break;
            }
        }
    }

    public function isBatchOpen(string $key): bool
    {
        return in_array($key, $this->openBatches, true);
    }

    public function selectGroup(string $key): void
    {
        $ids = [];
        foreach ($this->logGroups as $group) {
            if ((string) ($group['key'] ?? '') !== $key) {
                continue;
            }
            foreach ($group['logs'] as $log) {
                if ($this->rowIsSelectable($log)) {
                    // Strings so wire:model checkbox values match.
                    $ids[] = (string) $log->id;
                }
            }
        }

        if ($ids === []) {
            Notification::make()->warning()->title('No selectable fines in this group')->send();

            return;
        }

        $this->selectedIds = array_values(array_unique(array_merge(
            array_map('strval', $this->selectedIds),
            $ids
        )));
        if (! $this->isBatchOpen($key)) {
            $this->openBatches[] = $key;
        }

        Notification::make()
            ->success()
            ->title('Selected '.count($ids).' ready fine(s)')
            ->send();
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
                $all[] = (string) $log->id;
            }
        }
        $selected = array_values(array_map('strval', $this->selectedIds));
        sort($selected);
        $sortedAll = $all;
        sort($sortedAll);

        $this->selectedIds = ($all !== [] && $selected === $sortedAll) ? [] : $all;
    }

    /**
     * One-shot: selected ready fines → build Excel → download + open Wheelsys Import.
     * No selection → picker for an already prepared file.
     */
    public function startImport()
    {
        $this->importPickerShowImported = false;

        $ids = $this->selectedReadyIds();
        if ($ids !== []) {
            $result = $this->buildImportFileForIds($ids);
            if ($result === null) {
                return null;
            }

            $this->importPickerBatch = (string) $result['batch'];
            $this->fileFilters = [$this->importPickerBatch];
            $this->statusFilters = ['prepared'];
            $this->openBatches = $this->fileFilters;
            $this->selectedIds = [];

            return $this->downloadPreparedImportAndOpenWheelsys(
                fileName: (string) ($result['file_name'] ?? ''),
                count: (int) ($result['count'] ?? 0),
                alreadyImportedWarning: false,
            );
        }

        $anyPrepared = FineProcessLog::query()
            ->where('wheels_export_status', FineProcessLog::WHEELS_EXPORT_EXPORTED)
            ->whereNotNull('wheels_export_batch')
            ->where('wheels_export_batch', '!=', '')
            ->exists();
        if (! $anyPrepared) {
            Notification::make()
                ->warning()
                ->title('Select ready fines first')
                ->body('Mark fines under New, then Start import — or prepare a file earlier.')
                ->send();

            return null;
        }

        $preselect = $this->fileFilters[0] ?? '';
        if ($preselect === '') {
            $selected = array_values(array_filter(array_map('intval', $this->selectedIds)));
            if ($selected !== []) {
                $preselect = (string) (FineProcessLog::query()
                    ->whereIn('id', $selected)
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

        return null;
    }

    /**
     * @param  list<int>  $ids
     * @return array{path: string, batch: string, count: int, file_name?: string, sharepoint_url?: string}|null
     */
    private function buildImportFileForIds(array $ids): ?array
    {
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

        foreach ($logs as $log) {
            if ($log->wheels_export_status !== FineProcessLog::WHEELS_EXPORT_PENDING) {
                $log->fill(['wheels_export_status' => FineProcessLog::WHEELS_EXPORT_PENDING])->save();
            }
        }

        try {
            $result = app(WheelsysTrafficViolationExcelExporter::class)
                ->exportUploadAndMark($logs, Auth::id());
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Import file failed')->body($e->getMessage())->send();

            return null;
        }

        @unlink($result['path']);

        return $result;
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

        return $this->runPreparedImport(
            batch: (string) ($file['batch'] ?? $this->importPickerBatch),
            fileName: (string) ($file['file_name'] ?? ''),
            count: (int) ($file['count'] ?? 0),
            alreadyImportedWarning: (bool) ($this->importPickerStats['already_imported'] ?? false),
            alreadyImportedDetail: (($this->importPickerStats['imported'] ?? 0).' of '.($this->importPickerStats['total'] ?? 0)
                .' fine(s) are already In Wheels. Re-import may create duplicates — continue carefully.'),
        );
    }

    /**
     * One-click from a prepared file accordion: download Excel + open Wheels Import.
     */
    public function importPreparedBatch(string $batch)
    {
        $batch = trim($batch);
        if ($batch === '') {
            Notification::make()->warning()->title('Import file missing')->send();

            return null;
        }

        $this->importPickerShowImported = true;
        $choices = $this->importFileChoices;
        $file = $choices[$batch] ?? null;
        if ($file === null) {
            Notification::make()
                ->warning()
                ->title('Prepared file not found')
                ->body('Batch '.$batch.' has no Excel file on record.')
                ->send();

            return null;
        }

        $imported = (int) ($file['imported_count'] ?? 0);
        $total = (int) ($file['count'] ?? 0);

        return $this->runPreparedImport(
            batch: $batch,
            fileName: (string) ($file['file_name'] ?? ''),
            count: $total,
            alreadyImportedWarning: $imported > 0,
            alreadyImportedDetail: $imported.' of '.$total
                .' fine(s) are already In Wheels. Re-import may create duplicates — continue carefully.',
        );
    }

    private function runPreparedImport(
        string $batch,
        string $fileName,
        int $count,
        bool $alreadyImportedWarning = false,
        string $alreadyImportedDetail = '',
    ) {
        if ($fileName === '') {
            Notification::make()->warning()->title('Import file name missing')->send();

            return null;
        }

        $this->showImportPicker = false;
        $this->importPickerBatch = $batch;
        $this->fileFilters = $batch !== '' ? [$batch] : [];
        $this->openBatches = $this->fileFilters !== [] ? $this->fileFilters : ['__new__'];

        return $this->downloadPreparedImportAndOpenWheelsys(
            fileName: $fileName,
            count: $count,
            alreadyImportedWarning: $alreadyImportedWarning,
            alreadyImportedDetail: $alreadyImportedDetail,
        );
    }

    private function downloadPreparedImportAndOpenWheelsys(
        string $fileName,
        int $count,
        bool $alreadyImportedWarning = false,
        string $alreadyImportedDetail = '',
    ) {
        if ($fileName === '') {
            Notification::make()->warning()->title('Import file name missing')->send();

            return null;
        }

        if ($alreadyImportedWarning) {
            Notification::make()
                ->warning()
                ->title('File was already imported')
                ->body($alreadyImportedDetail !== '' ? $alreadyImportedDetail : 'Re-import may create duplicates.')
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

        $autoUser = EmailSetting::wheelsysConfig()['username'];
        Notification::make()
            ->success()
            ->title('Downloading · Wheels Import opening')
            ->body(
                $fileName.' · '.$count.' fine(s) · SSO as '
                .($autoUser !== '' ? $autoUser : 'VIO automation')
                .' · then Traffic violation → Select import.'
            )
            ->send();

        // Popup may be blocked if not from a direct click; still try after generate path.
        $this->js('window.open('.Js::from($this->wheelsysImportSsoUrl()).', "_blank", "noopener,noreferrer")');

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

    /**
     * Confirm this prepared file exists in Wheelsys (first+last bookends).
     */
    public function updateWheelsysStatus(string $batch): void
    {
        $batch = trim($batch);
        if ($batch === '') {
            Notification::make()->warning()->title('Import file missing')->send();

            return;
        }

        $logs = FineProcessLog::query()
            ->where('wheels_export_batch', $batch)
            ->where('wheels_export_status', FineProcessLog::WHEELS_EXPORT_EXPORTED)
            ->where(function ($q): void {
                $q->whereNull('wheels_import_status')
                    ->orWhere('wheels_import_status', FineProcessLog::WHEELS_IMPORT_AWAITING)
                    ->orWhere('wheels_import_status', FineProcessLog::WHEELS_IMPORT_MISSING);
            })
            ->orderBy('id')
            ->limit(200)
            ->get();

        if ($logs->isEmpty()) {
            Notification::make()
                ->warning()
                ->title('Nothing to update')
                ->body('This file is already marked Imported, or has no awaiting fines.')
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
            'batches' => $result['batches'] ?? 0,
            'checked' => $logs->count(),
            'batch' => $batch,
            'strategy' => 'bookends',
            'source' => 'to_wheelsys_file',
        ]);

        $body = $result['verified'].' in Wheels · '.$result['missing'].' missing · '.$result['skipped'].' skipped';
        $notification = Notification::make()->title('Wheelsys status updated')->body($body);
        if ($result['verified'] > 0 && $result['missing'] === 0) {
            $notification->success();
            $this->listTab = 'imported';
            $this->fileFilters = [$batch];
            $this->openBatches = ['day:'.now(config('app.timezone'))->format('Y-m-d'), $batch];
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
