<?php

namespace App\Filament\Resources\ViolationResource\Pages;

use App\Filament\Concerns\InteractsWithFineInformavimas;
use App\Filament\Pages\WheelsExcelExport;
use App\Filament\Resources\ViolationResource;
use App\Models\FineProcessLog;
use App\Support\ActivityLogger;
use App\Support\FineReplyReadiness;
use App\Support\IncomingFineReplyBuilder;
use App\Support\IncomingFineReplySender;
use App\Support\WheelsysImportVerifier;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ListViolations extends ListRecords
{
    use InteractsWithFineInformavimas;

    protected static string $resource = ViolationResource::class;

    protected static string $view = 'filament.resources.violation-resource.pages.list-violations';

    /** days = nested accordion (default) | table = full Filament table */
    public string $browseMode = 'days';

    /**
     * Expanded day / file accordion keys on the grouped browse view.
     *
     * @var list<string>
     */
    public array $openViolationGroups = [];

    /**
     * Selected fine ids for bulk authority reply (days browse mode).
     *
     * @var list<string>
     */
    public array $selectedReplyIds = [];

    public function mount(): void
    {
        parent::mount();

        $this->browseMode = 'days';
        $this->openViolationGroups = [];
        $this->selectedReplyIds = [];
        $firstDay = $this->violationDayGroups[0] ?? null;
        if ($firstDay !== null) {
            $this->openDayWithFiles($firstDay);
        }
    }

    public function setBrowseMode(string $mode): void
    {
        $this->browseMode = $mode === 'table' ? 'table' : 'days';
        $this->selectedReplyIds = [];
        if ($this->browseMode === 'days' && $this->openViolationGroups === []) {
            $firstDay = $this->violationDayGroups[0] ?? null;
            if ($firstDay !== null) {
                $this->openDayWithFiles($firstDay);
            }
        }
    }

    public function toggleViolationGroup(string $key): void
    {
        if (in_array($key, $this->openViolationGroups, true)) {
            $this->openViolationGroups = array_values(array_filter(
                $this->openViolationGroups,
                static fn (string $open): bool => $open !== $key
            ));

            return;
        }

        $this->openViolationGroups[] = $key;

        // Expanding a day → expand all import files inside it.
        if (str_starts_with($key, 'day:')) {
            foreach ($this->violationDayGroups as $day) {
                if ((string) ($day['key'] ?? '') !== $key) {
                    continue;
                }
                foreach ($day['files'] as $file) {
                    $fileKey = (string) ($file['key'] ?? '');
                    if ($fileKey !== '' && ! in_array($fileKey, $this->openViolationGroups, true)) {
                        $this->openViolationGroups[] = $fileKey;
                    }
                }
                break;
            }
        }
    }

    /**
     * @param  array{key: string, files: list<array{key: string}>}  $day
     */
    private function openDayWithFiles(array $day): void
    {
        $keys = [(string) $day['key']];
        foreach ($day['files'] as $file) {
            $fileKey = (string) ($file['key'] ?? '');
            if ($fileKey !== '') {
                $keys[] = $fileKey;
            }
        }
        $this->openViolationGroups = array_values(array_unique(array_merge(
            $this->openViolationGroups,
            $keys
        )));
    }

    public function isViolationGroupOpen(string $key): bool
    {
        return in_array($key, $this->openViolationGroups, true);
    }

    /**
     * Nested: import day → import file → fines.
     *
     * @return list<array{
     *     key: string,
     *     title: string,
     *     subtitle: string,
     *     imported_at_label: string|null,
     *     fine_count: int,
     *     files: list<array{
     *         key: string,
     *         title: string,
     *         subtitle: string,
     *         imported_at_label: string|null,
     *         logs: list<FineProcessLog>
     *     }>
     * }>
     */
    public function getViolationDayGroupsProperty(): array
    {
        $logs = ViolationResource::getEloquentQuery()
            ->with(['authority'])
            ->orderByDesc('wheels_imported_at')
            ->orderByDesc('id')
            ->limit(800)
            ->get();

        /** @var array<string, array{title:string,subtitle:?string,imported_at:?string,logs:list<FineProcessLog>}> $files */
        $files = [];

        foreach ($logs as $log) {
            $batch = filled($log->wheels_export_batch)
                ? (string) $log->wheels_export_batch
                : '__nofile__'.$log->id;

            if (! isset($files[$batch])) {
                $fileName = (string) ($log->wheels_export_file_name
                    ?: (filled($log->wheels_export_batch) ? 'wheels-export-'.$log->wheels_export_batch.'.xlsx' : 'No import file'));
                $prepared = optional($log->wheels_exported_at)->timezone(config('app.timezone'))?->format('Y-m-d H:i');
                $files[$batch] = [
                    'title' => $fileName,
                    'subtitle' => $prepared,
                    'imported_at' => null,
                    'logs' => [],
                ];
            }

            $files[$batch]['logs'][] = $log;

            if ($log->wheels_imported_at) {
                $iso = $log->wheels_imported_at->toIso8601String();
                if ($files[$batch]['imported_at'] === null || $iso > $files[$batch]['imported_at']) {
                    $files[$batch]['imported_at'] = $iso;
                }
            }
        }

        /** @var array<string, array{files: list<array<string, mixed>>, fine_count: int, sort: string}> $days */
        $days = [];

        foreach ($files as $batch => $file) {
            $latest = null;
            foreach ($file['logs'] as $log) {
                if ($log->wheels_imported_at && ($latest === null || $log->wheels_imported_at->gt($latest))) {
                    $latest = $log->wheels_imported_at;
                }
            }
            $importedAtLabel = $latest
                ? $latest->timezone(config('app.timezone'))->format('Y-m-d H:i')
                : null;
            $dayKey = $importedAtLabel !== null ? substr($importedAtLabel, 0, 10) : 'unknown';

            $subtitleParts = [];
            if ($file['subtitle']) {
                $subtitleParts[] = 'Prepared '.$file['subtitle'];
            }
            if ($importedAtLabel !== null) {
                $subtitleParts[] = 'Imported '.$importedAtLabel;
            }
            $toSend = 0;
            foreach ($file['logs'] as $log) {
                $reply = FineReplyReadiness::forLog($log);
                if (($reply['channel'] ?? '') === 'epaslaugos') {
                    continue;
                }
                if (! filled($log->email_sent_at)) {
                    $toSend++;
                }
            }
            $subtitleParts[] = count($file['logs']).' fine(s)';
            if ($toSend > 0) {
                $subtitleParts[] = $toSend.' not sent';
            }

            $filePayload = [
                'key' => 'file:'.$batch,
                'title' => $file['title'],
                'subtitle' => implode(' · ', $subtitleParts),
                'imported_at_label' => $importedAtLabel,
                'to_send_count' => $toSend,
                'logs' => $file['logs'],
            ];

            if (! isset($days[$dayKey])) {
                $days[$dayKey] = [
                    'files' => [],
                    'fine_count' => 0,
                    'to_send_count' => 0,
                    'sort' => $dayKey === 'unknown' ? '0000-00-00' : $dayKey,
                ];
            }
            $days[$dayKey]['files'][] = $filePayload;
            $days[$dayKey]['fine_count'] += count($file['logs']);
            $days[$dayKey]['to_send_count'] += $toSend;
        }

        uasort($days, static fn (array $a, array $b): int => strcmp($b['sort'], $a['sort']));

        $out = [];
        foreach ($days as $dayKey => $day) {
            $fileCount = count($day['files']);
            $toSend = (int) ($day['to_send_count'] ?? 0);
            $subtitle = $fileCount.' file(s) · '.$day['fine_count'].' fine(s)';
            if ($toSend > 0) {
                $subtitle .= ' · '.$toSend.' not sent';
            }
            $out[] = [
                'key' => 'day:'.$dayKey,
                'title' => $dayKey === 'unknown' ? 'No import date' : $dayKey,
                'subtitle' => $subtitle,
                'imported_at_label' => $dayKey === 'unknown' ? null : $dayKey,
                'fine_count' => $day['fine_count'],
                'to_send_count' => $toSend,
                'files' => $day['files'],
            ];
        }

        return $out;
    }

    public function rowIsBulkSendable(FineProcessLog $log): bool
    {
        if (filled($log->email_sent_at)) {
            return false;
        }

        $info = FineReplyReadiness::forLog($log);

        return $info['ready'] && ! $info['sent'] && ($info['channel'] ?? '') !== 'epaslaugos';
    }

    public function selectReadyInFile(string $fileKey): void
    {
        $ids = [];
        foreach ($this->violationDayGroups as $day) {
            foreach ($day['files'] as $file) {
                if ((string) ($file['key'] ?? '') !== $fileKey) {
                    continue;
                }
                foreach ($file['logs'] as $log) {
                    if ($this->rowIsBulkSendable($log)) {
                        $ids[] = (string) $log->id;
                    }
                }
            }
        }

        if ($ids === []) {
            Notification::make()->warning()->title('No ready replies in this file')->send();

            return;
        }

        $this->selectedReplyIds = array_values(array_unique(array_merge(
            array_map('strval', $this->selectedReplyIds),
            $ids
        )));

        Notification::make()
            ->success()
            ->title('Selected '.count($ids).' ready reply(ies)')
            ->send();
    }

    public function clearReplySelection(): void
    {
        $this->selectedReplyIds = [];
    }

    /**
     * Confirm selected (or all awaiting) fines exist in Wheelsys — first+last bookends per file.
     */
    public function updateWheelsysStatus(?string $fileKey = null): void
    {
        $ids = array_values(array_filter(array_map('intval', $this->selectedReplyIds)));

        $query = FineProcessLog::query()
            ->where('wheels_export_status', FineProcessLog::WHEELS_EXPORT_EXPORTED)
            ->where(function ($q): void {
                $q->whereNull('wheels_import_status')
                    ->orWhere('wheels_import_status', FineProcessLog::WHEELS_IMPORT_AWAITING)
                    ->orWhere('wheels_import_status', FineProcessLog::WHEELS_IMPORT_MISSING);
            });

        if ($fileKey !== null && str_starts_with($fileKey, 'file:')) {
            $batch = substr($fileKey, strlen('file:'));
            if (str_starts_with($batch, '__nofile__')) {
                $id = (int) substr($batch, strlen('__nofile__'));
                $query->whereKey($id);
            } else {
                $query->where('wheels_export_batch', $batch);
            }
        } elseif ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        $logs = $query->orderBy('id')->limit(200)->get();

        if ($logs->isEmpty()) {
            Notification::make()
                ->warning()
                ->title('Nothing to update')
                ->body($ids !== []
                    ? 'Selected rows are already Imported (or not in an import file).'
                    : 'No awaiting / missing fines to check in Wheelsys.')
                ->send();

            return;
        }

        try {
            $result = app(WheelsysImportVerifier::class)->verifyMany($logs, Auth::id());
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Update Wheelsys status failed')->body($e->getMessage())->send();

            return;
        }

        ActivityLogger::log('Wheels: verify imports (Imported fines)', Auth::user(), null, [
            'verified' => $result['verified'],
            'missing' => $result['missing'],
            'skipped' => $result['skipped'],
            'batches' => $result['batches'] ?? 0,
            'checked' => $logs->count(),
            'strategy' => 'bookends',
            'source' => 'violations',
        ]);

        $batchBit = ($result['batches'] ?? 0) > 0
            ? ' · '.$result['batches'].' file(s) via first+last'
            : '';
        $body = $result['verified'].' in Wheels · '.$result['missing'].' missing · '.$result['skipped'].' skipped'.$batchBit;
        $notification = Notification::make()->title('Wheelsys status updated')->body($body);
        if ($result['verified'] > 0 && $result['missing'] === 0) {
            $notification->success();
        } else {
            $notification->warning();
        }
        $notification->send();
    }

    public function sendSelectedReadyReplies(): void
    {
        $ids = array_values(array_filter(array_map('intval', $this->selectedReplyIds)));
        if ($ids === []) {
            Notification::make()->warning()->title('Nothing selected')->body('Select ready rows with checkboxes.')->send();

            return;
        }

        $logs = FineProcessLog::query()
            ->with('authority')
            ->whereIn('id', $ids)
            ->get();

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($logs as $record) {
            if (! $this->rowIsBulkSendable($record)) {
                $skipped++;

                continue;
            }

            try {
                $wheelsys = FineReplyReadiness::enrichAndStore($record);
                $info = FineReplyReadiness::forLog($record->fresh(), $wheelsys);
                if ($info['sent'] || ! $info['ready'] || $info['channel'] === 'epaslaugos') {
                    $skipped++;

                    continue;
                }

                $draft = app(IncomingFineReplyBuilder::class)
                    ->buildForProcessLog($record->fresh(), $wheelsys);
                if (($draft['reply_channel'] ?? '') === 'epaslaugos') {
                    $skipped++;

                    continue;
                }
                if (! empty($draft['missing_fields'])) {
                    $skipped++;

                    continue;
                }

                $log = app(IncomingFineReplySender::class)->send($draft, $record);
                $workflow = is_array($log->workflow) ? $log->workflow : [];
                $workflow['wheelsys'] = $wheelsys;
                $workflow['steps'] = array_values(array_merge(
                    is_array($workflow['steps'] ?? null) ? $workflow['steps'] : [],
                    [['key' => 'authority_reply', 'ok' => true, 'detail' => 'Bulk sent to '.$draft['to']]]
                ));
                $log->fill(['workflow' => $workflow])->save();
                $sent++;
            } catch (Throwable) {
                $failed++;
            }
        }

        $this->selectedReplyIds = [];

        $notification = Notification::make()
            ->title('Bulk send finished')
            ->body("Sent {$sent} · skipped {$skipped} · failed {$failed}");
        if ($sent > 0 && $failed === 0) {
            $notification->success();
        } elseif ($sent > 0) {
            $notification->warning();
        } else {
            $notification->danger();
        }
        $notification->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('wheelsysImport')
                ->label('To Wheelsys')
                ->icon('heroicon-o-arrow-up-tray')
                ->url(WheelsExcelExport::getUrl()),
        ];
    }

    public function getFooter(): ?View
    {
        // Footer views are not auto-injected with Livewire public props — pass explicitly.
        $wheelsAlreadyImported = false;
        if ($this->informuotiLogId) {
            $status = FineProcessLog::query()->whereKey($this->informuotiLogId)->value('wheels_import_status');
            $wheelsAlreadyImported = $status === FineProcessLog::WHEELS_IMPORT_IMPORTED;
        }

        return view('filament.resources.violation-resource.informuoti-modal', [
            'showInformuoti' => $this->showInformuoti,
            'previewDraft' => $this->previewDraft,
            'previewMessage' => $this->previewMessage,
            'wheelsysResults' => $this->wheelsysResults,
            'wheelsysSelected' => $this->wheelsysSelected,
            'wheelsysSelectedIndex' => $this->wheelsysSelectedIndex,
            'searchVehicle' => $this->searchVehicle,
            'searchTicketDate' => $this->searchTicketDate,
            'showTemplateEditor' => $this->showTemplateEditor,
            'sourceMailHtml' => $this->sourceMailHtml,
            'sourceExtractText' => $this->sourceExtractText,
            'sourceFiles' => $this->sourceFiles,
            'informuotiReadiness' => $this->informuotiReadiness,
            'epaslaugosAssist' => $this->epaslaugosAssist,
            'epaslaugosPersonType' => $this->epaslaugosPersonType,
            'wheelsAlreadyImported' => $wheelsAlreadyImported,
        ]);
    }
}
