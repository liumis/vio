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
use App\Support\WheelsysRentalDocumentArchiver;
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
    }

    public function setBrowseMode(string $mode): void
    {
        $this->browseMode = $mode === 'table' ? 'table' : 'days';
        $this->selectedReplyIds = [];
    }

    public function updatedBrowseMode(mixed $value): void
    {
        $mode = is_string($value) && $value === 'table' ? 'table' : 'days';
        $this->browseMode = $mode;
        $this->selectedReplyIds = [];
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
            // Prefer not-imported rows in the window, then newest import day.
            ->orderByRaw(
                'CASE WHEN wheels_import_status = ? THEN 1 ELSE 0 END ASC',
                [FineProcessLog::WHEELS_IMPORT_IMPORTED]
            )
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

        /** @var array<string, array{files: list<array<string, mixed>>, fine_count: int, not_imported_count: int, sort: string}> $days */
        $days = [];

        foreach ($files as $batch => $file) {
            // Not imported first, then imported (newest id within each bucket).
            usort($file['logs'], static function (FineProcessLog $a, FineProcessLog $b): int {
                $aDone = $a->wheels_import_status === FineProcessLog::WHEELS_IMPORT_IMPORTED;
                $bDone = $b->wheels_import_status === FineProcessLog::WHEELS_IMPORT_IMPORTED;
                if ($aDone !== $bDone) {
                    return $aDone ? 1 : -1;
                }

                return $b->id <=> $a->id;
            });

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
            $notImported = 0;
            foreach ($file['logs'] as $log) {
                if ($log->wheels_import_status !== FineProcessLog::WHEELS_IMPORT_IMPORTED) {
                    $notImported++;
                }
                $reply = FineReplyReadiness::forLog($log);
                if (($reply['channel'] ?? '') === 'epaslaugos') {
                    continue;
                }
                if (! filled($log->email_sent_at)) {
                    $toSend++;
                }
            }
            $subtitleParts[] = count($file['logs']).' fine(s)';
            if ($notImported > 0) {
                $subtitleParts[] = $notImported.' not imported';
            }
            if ($toSend > 0) {
                $subtitleParts[] = $toSend.' not sent';
            }

            $filePayload = [
                'key' => 'file:'.$batch,
                'title' => $file['title'],
                'subtitle' => implode(' · ', $subtitleParts),
                'imported_at_label' => $importedAtLabel,
                'prepared_at' => (string) ($file['subtitle'] ?? ''),
                'to_send_count' => $toSend,
                'not_imported_count' => $notImported,
                'logs' => $file['logs'],
            ];

            if (! isset($days[$dayKey])) {
                $days[$dayKey] = [
                    'files' => [],
                    'fine_count' => 0,
                    'to_send_count' => 0,
                    'not_imported_count' => 0,
                    'sort' => $dayKey === 'unknown' ? '' : $dayKey,
                    'is_unknown' => $dayKey === 'unknown',
                ];
            }
            $days[$dayKey]['files'][] = $filePayload;
            $days[$dayKey]['fine_count'] += count($file['logs']);
            $days[$dayKey]['to_send_count'] += $toSend;
            $days[$dayKey]['not_imported_count'] += $notImported;
        }

        // Explicit bucket: 0 = No import date, 1 = has not-imported, 2 = fully imported.
        // Then newest date → oldest inside the bucket.
        uasort($days, static function (array $a, array $b): int {
            $bucket = static function (array $day): int {
                if (! empty($day['is_unknown'])) {
                    return 0;
                }

                return ((int) ($day['not_imported_count'] ?? 0)) > 0 ? 1 : 2;
            };

            $byBucket = $bucket($a) <=> $bucket($b);
            if ($byBucket !== 0) {
                return $byBucket;
            }

            return strcmp((string) ($b['sort'] ?? ''), (string) ($a['sort'] ?? ''));
        });

        $out = [];
        foreach ($days as $dayKey => $day) {
            // Files: not-imported first, then newest import/prepare time.
            usort($day['files'], static function (array $a, array $b): int {
                $aPending = ((int) ($a['not_imported_count'] ?? 0)) > 0 ? 1 : 0;
                $bPending = ((int) ($b['not_imported_count'] ?? 0)) > 0 ? 1 : 0;
                if ($aPending !== $bPending) {
                    return $bPending <=> $aPending;
                }

                $aCount = (int) ($a['not_imported_count'] ?? 0);
                $bCount = (int) ($b['not_imported_count'] ?? 0);
                if ($aCount !== $bCount) {
                    return $bCount <=> $aCount;
                }

                $aSort = (string) (($a['imported_at_label'] ?? '') ?: ($a['prepared_at'] ?? ''));
                $bSort = (string) (($b['imported_at_label'] ?? '') ?: ($b['prepared_at'] ?? ''));

                return strcmp($bSort, $aSort);
            });

            $fileCount = count($day['files']);
            $toSend = (int) ($day['to_send_count'] ?? 0);
            $notImported = (int) ($day['not_imported_count'] ?? 0);
            $subtitle = $fileCount.' file(s) · '.$day['fine_count'].' fine(s)';
            if ($notImported > 0) {
                $subtitle .= ' · '.$notImported.' not imported';
            }
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
                'not_imported_count' => $notImported,
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
                    ->orWhere('wheels_import_status', FineProcessLog::WHEELS_IMPORT_MISSING)
                    // Imported middle bookend rows often lack wheels_violation_id (no Wheels link).
                    ->orWhere(function ($inner): void {
                        $inner->where('wheels_import_status', FineProcessLog::WHEELS_IMPORT_IMPORTED)
                            ->where(function ($idQ): void {
                                $idQ->whereNull('wheels_violation_id')
                                    ->orWhere('wheels_violation_id', '');
                            });
                    });
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

    public function fetchRntDocs(int $logId): void
    {
        $log = FineProcessLog::query()->find($logId);
        if (! $log) {
            Notification::make()->danger()->title('Fine not found')->send();

            return;
        }

        try {
            $result = app(WheelsysRentalDocumentArchiver::class)->archiveForLog($log, force: true);
            $fresh = $log->fresh();
            $pdfCount = $fresh ? count($fresh->rntContractPdfSharePointUrls()) : 0;
            Notification::make()
                ->success()
                ->title('RNT docs saved')
                ->body($result['count'].' file(s) → '.$result['folder']
                    .($pdfCount > 0 ? ' · '.$pdfCount.' contract PDF link(s) on the row' : ''))
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->danger()
                ->title('RNT fetch failed')
                ->body($e->getMessage())
                ->persistent()
                ->send();
        }
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

        $informuotiRentalId = null;
        if ($this->informuotiLogId) {
            $informuotiRentalId = FineProcessLog::query()->whereKey($this->informuotiLogId)->value('rental_id');
        }

        return view('filament.resources.violation-resource.informuoti-modal', [
            'showInformuoti' => $this->showInformuoti,
            'informuotiLogId' => $this->informuotiLogId,
            'informuotiRentalId' => $informuotiRentalId,
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
            'sendAttachmentPreview' => $this->sendAttachmentPreview,
            'sendAttachmentSelected' => $this->sendAttachmentSelected,
        ]);
    }
}
