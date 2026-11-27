<?php

namespace App\Filament\Pages;

use App\Models\Authority;
use App\Models\EmailSetting;
use App\Models\FineProcessLog;
use App\Support\EpaslaugosAssist;
use App\Support\InformavimoTerminas;
use App\Support\IncomingFineLookup;
use App\Support\IncomingFineMailboxFolders;
use App\Support\IncomingFineMailScanner;
use App\Support\IncomingFineReplyBuilder;
use App\Support\IncomingFineReplySender;
use App\Support\IncomingFineWorkflow;
use App\Support\LatviaAuthorityPackageService;
use App\Support\MicrosoftGraphInbox;
use App\Support\WheelsysClient;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

class IncomingFines extends Page implements HasForms
{
    use InteractsWithForms;
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-inbox';

    protected static ?string $navigationLabel = '1. Read emails';

    protected static ?string $navigationGroup = 'Fines';

    protected static ?string $title = 'Read emails';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.incoming-fines';

    public ?array $data = [];

    /**
     * @var list<array<string, mixed>>
     */
    public array $results = [];

    public ?string $mailbox = null;

    public ?string $lastError = null;

    public bool $showPreview = false;

    public bool $showTemplateEditor = false;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $previewMessage = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $previewDraft = null;

    /**
     * @var list<array<string, mixed>>
     */
    public array $wheelsysResults = [];

    public ?array $wheelsysSelected = null;

    public ?int $wheelsysSelectedIndex = null;

    public string $searchVehicle = '';

    public ?string $searchTicketDate = null;

    public ?int $processLogId = null;

    public string $epaslaugosPersonType = EpaslaugosAssist::PERSON_LT_NATURAL;

    /** @var TemporaryUploadedFile|string|null */
    public $latviaRntUpload = null;

    /**
     * @var list<array{key:string,ok:bool,detail:string}>
     */
    public array $workflowSteps = [];

    /**
     * Graph message ids selected in the inbox table.
     *
     * @var list<string>
     */
    public array $selectedIds = [];

    public ?int $previewIndex = null;

    /** Original inbound HTML (Graph) for manual review when OCR/extract fails. */
    public string $sourceMailHtml = '';

    public string $sourceMailText = '';

    /** Combined extract / OCR text from saved attachments. */
    public string $sourceExtractText = '';

    /**
     * Local / Graph attachments available in Details.
     *
     * @var list<array{key:string,name:string,source:string,path?:string,graph_id?:string,bytes:int,kind:string}>
     */
    public array $sourceFiles = [];

    public function mount(): void
    {
        $config = EmailSetting::graphConfig();
        $this->mailbox = $config['inbox_mailbox'] !== ''
            ? $config['inbox_mailbox']
            : ($config['from_email'] !== '' ? $config['from_email'] : null);

        $this->form->fill([
            'days' => 3,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('days')
                    ->label('Look back (days)')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(180)
                    ->required(),
            ])
            ->statePath('data');
    }

    /**
     * @return array<string, string>
     */
    public function getAuthorityFilterOptionsProperty(): array
    {
        $options = Authority::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        $mapped = [];
        foreach ($options as $id => $name) {
            $mapped[(string) $id] = (string) $name;
        }

        return ['unmatched' => 'Unmatched (—)'] + $mapped;
    }

    /**
     * Column sort: from|subject|authority|informavimas|plate|status (empty = default order).
     */
    public string $listSortColumn = '';

    /** '' | asc | desc */
    public string $listSortDir = '';

    /** @deprecated Use listSortColumn === 'status' */
    public string $statusSort = '';

    /**
     * Stable status key used by chips, filter, and sort (matches Status column labels).
     *
     * @param  array<string, mixed>  $row
     */
    public function rowStatusKey(array $row): string
    {
        $lookup = $row['lookup_status'] ?? null;

        if (! empty($row['excel_exported'])) {
            return 'already_have_prepared';
        }
        if (! empty($row['in_excel_queue'])) {
            return 'already_have_ready';
        }
        if (! empty($row['in_vio']) && $lookup === 'found') {
            return 'already_have_found';
        }
        if ($lookup === 'needs_review') {
            return 'needs_review';
        }
        if ($lookup === 'not_found') {
            return 'not_found';
        }
        if (! empty($row['in_vio'])) {
            return 'already_have';
        }
        if ($lookup === 'found') {
            return 'found';
        }

        return 'new';
    }

    /**
     * @return array<string, string>
     */
    public function getStatusFilterOptionsProperty(): array
    {
        return [
            'new' => 'New',
            'needs_review' => 'Needs review · multi driver',
            'not_found' => 'Not found · retry',
            'found' => 'Found',
            'already_have' => 'Already have',
            'already_have_found' => 'Already have · Found',
            'already_have_ready' => 'Already have · Ready',
            'already_have_prepared' => 'Already have · Prepared',
        ];
    }

    public function clearListFilters(): void
    {
        $this->data['authority_ids'] = [];
        $this->data['statuses'] = [];
        $this->listSortColumn = '';
        $this->listSortDir = '';
        $this->statusSort = '';
    }

    /** @deprecated Use clearListFilters() */
    public function clearAuthorityFilter(): void
    {
        $this->clearListFilters();
    }

    public function toggleListSort(string $column): void
    {
        $column = trim($column);
        $allowed = ['from', 'subject', 'authority', 'informavimas', 'plate', 'status'];
        if (! in_array($column, $allowed, true)) {
            return;
        }

        if ($this->listSortColumn !== $column) {
            $this->listSortColumn = $column;
            $this->listSortDir = 'asc';
        } else {
            $this->listSortDir = match ($this->listSortDir) {
                'asc' => 'desc',
                'desc' => '',
                default => 'asc',
            };
            if ($this->listSortDir === '') {
                $this->listSortColumn = '';
            }
        }

        $this->statusSort = $this->listSortColumn === 'status' ? $this->listSortDir : '';
    }

    /** @deprecated Use toggleListSort('status') */
    public function toggleStatusSort(): void
    {
        $this->toggleListSort('status');
    }

    public function listSortIcon(string $column): string
    {
        if ($this->listSortColumn !== $column || $this->listSortDir === '') {
            return '↕';
        }

        return $this->listSortDir === 'asc' ? '↑' : '↓';
    }

    public function listSortActive(string $column): bool
    {
        return $this->listSortColumn === $column && $this->listSortDir !== '';
    }

    /**
     * Fetched rows filtered by Authority + Status (preserves original indexes for Preview/Process).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFilteredResultsProperty(): array
    {
        $rows = $this->results;

        // Keep original keys so process() indexes stay valid.
        $authorityIds = array_values(array_filter(
            array_map('strval', (array) ($this->data['authority_ids'] ?? [])),
            static fn (string $v): bool => $v !== ''
        ));
        if ($authorityIds !== []) {
            $rows = array_filter(
                $rows,
                static function (array $row) use ($authorityIds): bool {
                    $rowId = $row['authority_id'] ?? null;
                    foreach ($authorityIds as $selected) {
                        if ($selected === 'unmatched' && empty($rowId)) {
                            return true;
                        }
                        if ($selected !== 'unmatched' && (string) (int) $rowId === (string) (int) $selected && filled($rowId)) {
                            return true;
                        }
                    }

                    return false;
                }
            );
        }

        $statuses = array_values(array_filter(
            array_map('strval', (array) ($this->data['statuses'] ?? [])),
            static fn (string $v): bool => $v !== ''
        ));
        if ($statuses !== []) {
            $rows = array_filter(
                $rows,
                fn (array $row): bool => in_array($this->rowStatusKey($row), $statuses, true)
            );
        }

        // Migrate legacy statusSort if still set from an old Livewire snapshot.
        if ($this->listSortColumn === '' && ($this->statusSort === 'asc' || $this->statusSort === 'desc')) {
            $this->listSortColumn = 'status';
            $this->listSortDir = $this->statusSort;
        }

        if ($this->listSortColumn !== '' && ($this->listSortDir === 'asc' || $this->listSortDir === 'desc')) {
            $dir = $this->listSortDir === 'asc' ? 1 : -1;
            $column = $this->listSortColumn;
            $statusOrder = [
                'new' => 1,
                'needs_review' => 2,
                'not_found' => 3,
                'found' => 4,
                'already_have' => 5,
                'already_have_found' => 6,
                'already_have_ready' => 7,
                'already_have_prepared' => 8,
            ];

            uasort(
                $rows,
                function (array $a, array $b) use ($column, $dir, $statusOrder): int {
                    $cmp = match ($column) {
                        'from' => strcasecmp(
                            (string) (($a['from_name'] ?? '') !== '' ? $a['from_name'] : ($a['from_email'] ?? '')),
                            (string) (($b['from_name'] ?? '') !== '' ? $b['from_name'] : ($b['from_email'] ?? ''))
                        ) ?: strcmp((string) ($a['received_at'] ?? ''), (string) ($b['received_at'] ?? '')),
                        'subject' => strcasecmp((string) ($a['subject'] ?? ''), (string) ($b['subject'] ?? '')),
                        'authority' => strcasecmp((string) ($a['authority_name'] ?? ''), (string) ($b['authority_name'] ?? '')),
                        'informavimas' => ($this->rowInformavimoSortKey($a) <=> $this->rowInformavimoSortKey($b)),
                        'plate' => strcasecmp(
                            (string) (($a['vehicle'] ?? '') !== '' ? $a['vehicle'] : ($a['driver_name'] ?? '')),
                            (string) (($b['vehicle'] ?? '') !== '' ? $b['vehicle'] : ($b['driver_name'] ?? ''))
                        ),
                        'status' => (($statusOrder[$this->rowStatusKey($a)] ?? 99) <=> ($statusOrder[$this->rowStatusKey($b)] ?? 99)),
                        default => 0,
                    };

                    return $cmp * $dir;
                }
            );
        }

        return $rows;
    }

    /**
     * Lower = more urgent (critical first when ascending). Done / none sort last.
     *
     * @param  array<string, mixed>  $row
     */
    private function rowInformavimoSortKey(array $row): int
    {
        $deadline = InformavimoTerminas::forAuthorityId(
            isset($row['authority_id']) ? (int) $row['authority_id'] : null,
            $row['received_at'] ?? ($row['received_at_local'] ?? null),
            ! empty($row['email_sent']) || ! empty($row['epaslaugos_done']),
        );
        $level = (string) ($deadline['level'] ?? 'none');

        $levelRank = match ($level) {
            'critical' => 1,
            'warn' => 2,
            'ok' => 3,
            'done' => 8,
            default => 9,
        };
        $days = $deadline['days_left'];
        $dayPart = is_int($days) ? max(0, min(500, $days)) : 500;

        return ($levelRank * 1000) + $dayPart;
    }

    public function fetch(): void
    {
        \App\Support\HttpTimeouts::extendPhpRuntime();

        $state = $this->form->getState();
        $days = max(1, min(180, (int) ($state['days'] ?? 3)));

        $this->lastError = null;
        $this->results = [];
        $this->selectedIds = [];
        $this->closePreview();

        try {
            $this->results = app(IncomingFineMailScanner::class)->scan($days);
            $this->enrichResultsFromDb();
            $config = EmailSetting::graphConfig();
            $this->mailbox = $config['inbox_mailbox'] !== '' ? $config['inbox_mailbox'] : $config['from_email'];

            $inVio = 0;
            $inQueue = 0;
            $excelDone = 0;
            foreach ($this->results as $row) {
                if (! empty($row['in_vio'])) {
                    $inVio++;
                }
                if (! empty($row['in_excel_queue'])) {
                    $inQueue++;
                }
                if (! empty($row['excel_exported'])) {
                    $excelDone++;
                }
            }

            // Drop selected ids whose checkboxes are locked (found / Wheels queue / exported).
            // "Not found" stays selectable so Find driver can be retried.
            $blocked = [];
            foreach ($this->results as $row) {
                if ($this->rowSelectionLocked($row)) {
                    $blocked[(string) ($row['id'] ?? '')] = true;
                }
            }
            $this->selectedIds = array_values(array_filter(
                $this->selectedIds,
                static fn (string $id): bool => $id !== '' && ! isset($blocked[$id])
            ));

            Notification::make()
                ->success()
                ->title('Inbox scanned')
                ->body(count($this->results)." message(s) · already have {$inVio}"
                    .($inQueue > 0 ? " · Wheelsys import {$inQueue}" : '')
                    .($excelDone > 0 ? " · Prepared {$excelDone}" : ''))
                ->send();
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();

            Notification::make()
                ->danger()
                ->title('Inbox scan failed')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function closePreview(): void
    {
        if ($this->showPreview && $this->previewIndex !== null) {
            $this->syncPreviewBackToResults();
        }

        $this->showPreview = false;
        $this->showTemplateEditor = false;
        $this->previewMessage = null;
        $this->previewDraft = null;
        $this->wheelsysResults = [];
        $this->wheelsysSelected = null;
        $this->wheelsysSelectedIndex = null;
        $this->searchVehicle = '';
        $this->searchTicketDate = null;
        $this->processLogId = null;
        $this->workflowSteps = [];
        $this->previewIndex = null;
        $this->sourceMailHtml = '';
        $this->sourceMailText = '';
        $this->sourceExtractText = '';
        $this->sourceFiles = [];
    }

    /**
     * Lock selection only when Find driver already succeeded or Wheelsys import is done.
     * lookup_missing / not_found stays selectable for retry (e.g. after .edoc fix).
     *
     * @param  array<string, mixed>  $row
     */
    private function rowSelectionLocked(array $row): bool
    {
        if (! empty($row['in_excel_queue']) || ! empty($row['excel_exported'])) {
            return true;
        }

        if (in_array($row['lookup_status'] ?? null, ['not_found', 'needs_review'], true)) {
            return false;
        }

        if (($row['lookup_status'] ?? null) === 'found') {
            return true;
        }

        // Legacy / partial logs: driver already resolved.
        return ! empty($row['in_vio']) && filled($row['driver_name'] ?? null);
    }

    /**
     * Selectable message ids in the current filtered inbox list (unlocked rows only).
     *
     * @return list<string>
     */
    public function selectableFilteredIds(): array
    {
        $ids = [];
        foreach ($this->filteredResults as $row) {
            if (! is_array($row)) {
                continue;
            }
            if ($this->rowSelectionLocked($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function toggleSelectAll(): void
    {
        $all = $this->selectableFilteredIds();
        $selected = array_map('strval', $this->selectedIds);
        sort($selected);
        $sortedAll = $all;
        sort($sortedAll);

        if ($all !== [] && $selected === $sortedAll) {
            $this->selectedIds = [];

            return;
        }

        $this->selectedIds = $all;
    }

    public function clearSelection(): void
    {
        $this->selectedIds = [];
    }

    public function findRentalsSelected(): void
    {
        $indexes = $this->indexesForSelectedIds();
        if ($indexes === []) {
            Notification::make()->warning()->title('Select messages first')->send();

            return;
        }

        $found = 0;
        $missing = 0;
        $errors = 0;
        $gapTallies = [];

        foreach ($indexes as $index) {
            try {
                $this->applyLookupToRow($index);
                if (($this->results[$index]['lookup_status'] ?? '') === 'found') {
                    $found++;
                } else {
                    $missing++;
                    foreach ((array) ($this->results[$index]['lookup_missing'] ?? []) as $gap) {
                        $gap = trim((string) $gap);
                        if ($gap === '') {
                            continue;
                        }
                        $gapTallies[$gap] = ($gapTallies[$gap] ?? 0) + 1;
                    }
                }
            } catch (Throwable $e) {
                $errors++;
                $this->results[$index]['lookup_status'] = 'not_found';
                $this->results[$index]['lookup_detail'] = $e->getMessage();
                $this->results[$index]['lookup_missing'] = ['error'];
            }
        }

        $gapHint = '';
        if ($gapTallies !== []) {
            $labels = IncomingFineLookup::missingLabels();
            arsort($gapTallies);
            $parts = [];
            foreach (array_slice($gapTallies, 0, 4, true) as $key => $count) {
                $parts[] = ($labels[$key] ?? $key).($count > 1 ? " ×{$count}" : '');
            }
            $gapHint = ' · gaps: '.implode(', ', $parts);
        }

        $notification = Notification::make()
            ->title('Saved to VIO DB')
            ->body("Found {$found} · not found {$missing}"
                .($errors > 0 ? " · errors {$errors}" : '')
                .$gapHint
                .' · next: Wheelsys import');

        if ($missing > 0 || $errors > 0) {
            $notification->warning();
        } else {
            $notification->success();
        }
        $notification->send();
    }

    /**
     * Merge inbox scan rows with existing fine_process_logs (same Graph message id).
     */
    private function enrichResultsFromDb(): void
    {
        $ids = array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['id'] ?? ''),
            $this->results
        )));
        if ($ids === []) {
            return;
        }

        $logs = FineProcessLog::query()
            ->whereIn('graph_message_id', $ids)
            ->get()
            ->keyBy('graph_message_id');

        foreach ($this->results as $i => $row) {
            $id = (string) ($row['id'] ?? '');
            $log = $logs->get($id);
            if (! $log instanceof FineProcessLog) {
                $this->results[$i]['in_vio'] = false;
                $this->results[$i]['in_excel_queue'] = false;
                $this->results[$i]['excel_exported'] = false;
                $this->results[$i]['selection_locked'] = false;

                continue;
            }

            $lookup = match (true) {
                $log->status === 'lookup_ambiguous'
                    || $log->wheels_export_status === FineProcessLog::WHEELS_EXPORT_NEEDS_REVIEW => 'needs_review',
                $log->status === 'lookup_found'
                    || ($log->wheels_export_status === FineProcessLog::WHEELS_EXPORT_PENDING && filled($log->driver_name))
                    || $log->wheels_export_status === FineProcessLog::WHEELS_EXPORT_EXPORTED => 'found',
                $log->status === 'lookup_missing' => 'not_found',
                filled($log->vehicle) && filled($log->driver_name) => 'found',
                filled($log->vehicle) => 'not_found',
                default => null,
            };

            $inExcelQueue = $log->wheels_export_status === FineProcessLog::WHEELS_EXPORT_PENDING
                && filled($log->vehicle)
                && filled($log->driver_name);
            $excelExported = $log->wheels_export_status === FineProcessLog::WHEELS_EXPORT_EXPORTED;

            $lookupDetail = $log->error
                ?: trim(($log->agr_no ? $log->agr_no.' · ' : '').($log->driver_name ?: ''));
            $lookupMissing = is_array($log->workflow['lookup_missing'] ?? null)
                ? array_values(array_map('strval', $log->workflow['lookup_missing']))
                : [];
            if ($lookup === 'needs_review') {
                $ambiguous = is_array($log->workflow['ambiguous_drivers'] ?? null)
                    ? $log->workflow['ambiguous_drivers']
                    : [];
                $lookupDetail = $ambiguous !== []
                    ? 'Needs review — '.count($ambiguous).' drivers that day ('.implode(', ', $ambiguous).')'
                    : (is_array($log->workflow['steps'][0] ?? null)
                        ? (string) ($log->workflow['steps'][0]['detail'] ?? 'Needs review — multiple drivers that day')
                        : 'Needs review — multiple drivers that day');
                $lookupMissing = [];
            } elseif ($lookup === 'not_found') {
                if ($lookupMissing === []) {
                    $lookupMissing = IncomingFineLookup::inferMissingFromLog($log);
                }
                $stepDetail = is_array($log->workflow['steps'][0] ?? null)
                    ? trim((string) ($log->workflow['steps'][0]['detail'] ?? ''))
                    : '';
                if ($stepDetail !== '' && (str_starts_with($stepDetail, 'Missing:') || filled($log->error))) {
                    $lookupDetail = $stepDetail;
                } elseif ($lookupDetail === '') {
                    $lookupDetail = IncomingFineLookup::presentMissing($lookupMissing, $log->error)['tooltip'];
                }
            }

            $merged = array_merge($row, [
                'in_vio' => true,
                'in_excel_queue' => $inExcelQueue,
                'process_log_id' => $log->id,
                'vehicle' => $log->vehicle ?: ($row['vehicle'] ?? ''),
                'driver_name' => $log->driver_name ?: ($row['driver_name'] ?? ''),
                'reference' => $log->reference ?: ($row['reference'] ?? ''),
                'ticket_date' => $log->ticket_date?->format('Y-m-d') ?: ($row['ticket_date'] ?? ''),
                'ticket_time' => $log->ticket_time ?: ($row['ticket_time'] ?? ''),
                'lookup_status' => $lookup,
                'lookup_detail' => $lookupDetail,
                'lookup_missing' => $lookupMissing,
                'excel_exported' => $excelExported,
                'excel_batch' => $log->wheels_export_batch,
                'excel_sharepoint_url' => $log->wheels_export_sharepoint_url,
                'wheels_import_status' => $log->wheels_import_status,
                'wheels_export_status' => $log->wheels_export_status,
                'workflow_steps' => is_array($log->workflow['steps'] ?? null) ? $log->workflow['steps'] : [],
                'local_attachment_dir' => $log->local_attachment_dir,
            ]);
            $merged['selection_locked'] = $this->rowSelectionLocked($merged);
            $this->results[$i] = $merged;
        }
    }

    public function openDetails(int $index): void
    {
        $row = $this->results[$index] ?? null;
        if (! is_array($row)) {
            Notification::make()->danger()->title('Message not found')->send();

            return;
        }

        try {
            if (($row['lookup_status'] ?? null) === null) {
                $this->applyLookupToRow($index);
                $row = $this->results[$index];
            }

            $this->previewIndex = $index;
            $this->previewMessage = $row;
            $this->previewDraft = is_array($row['draft'] ?? null)
                ? $row['draft']
                : app(IncomingFineReplyBuilder::class)->build($row, is_array($row['wheelsys'] ?? null) ? $row['wheelsys'] : null);
            $this->wheelsysSelected = is_array($row['wheelsys'] ?? null) ? $row['wheelsys'] : null;
            $this->wheelsysResults = is_array($row['rentals'] ?? null) ? $row['rentals'] : [];
            if ($this->wheelsysResults === [] && $this->wheelsysSelected) {
                $this->wheelsysResults = [$this->wheelsysSelected];
            }
            $this->wheelsysSelectedIndex = $this->wheelsysResults !== [] ? 0 : null;
            $this->searchVehicle = (string) ($row['vehicle'] ?? $this->previewDraft['vehicle_hint'] ?? '');
            $this->searchTicketDate = $this->ticketMomentForSearchInput(
                $row['ticket_datetime'] ?? null,
                $row['ticket_date'] ?? ($this->previewDraft['ticket_date_hint'] ?? null),
                $row['ticket_time'] ?? ($this->previewDraft['ticket_time_hint'] ?? null),
            );
            $this->processLogId = isset($row['process_log_id']) ? (int) $row['process_log_id'] : null;
            $this->workflowSteps = is_array($row['workflow_steps'] ?? null) ? $row['workflow_steps'] : [];
            $this->showTemplateEditor = false;
            $this->loadSourceMaterials($row);
            $this->showPreview = true;
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Details failed')->body($e->getMessage())->send();
        }
    }

    /**
     * Load original inbound mail + attachments for manual plate/driver reading.
     *
     * @param  array<string, mixed>  $row
     */
    private function loadSourceMaterials(array $row): void
    {
        $this->sourceMailHtml = '';
        $this->sourceMailText = '';
        $this->sourceExtractText = trim((string) ($row['attachment_text'] ?? ''));
        $this->sourceFiles = [];

        $messageId = (string) ($row['id'] ?? '');
        $inbox = app(MicrosoftGraphInbox::class);

        if ($messageId !== '') {
            try {
                $this->sourceMailHtml = $this->prepareInboundMailHtml(
                    $inbox->getMessageBodyHtml($messageId)
                );
            } catch (Throwable) {
                $this->sourceMailHtml = '';
            }
            if ($this->sourceMailHtml === '') {
                try {
                    $this->sourceMailText = $inbox->getMessageBodyText($messageId);
                } catch (Throwable) {
                    $this->sourceMailText = trim((string) ($row['preview'] ?? ''));
                }
            }
        } else {
            $this->sourceMailText = trim((string) ($row['preview'] ?? ''));
        }

        // Prefer a rendered HTML preview even when Graph only returns plain text.
        if ($this->sourceMailHtml === '' && $this->sourceMailText !== '') {
            $this->sourceMailHtml = $this->prepareInboundMailHtml(
                '<pre style="white-space:pre-wrap;margin:0;font:inherit;">'
                .e($this->sourceMailText)
                .'</pre>'
            );
            $this->sourceMailText = '';
        }

        $dir = trim(str_replace('\\', '/', (string) ($row['local_attachment_dir'] ?? '')), '/');
        if ($dir === '' && $this->processLogId) {
            $dir = trim(str_replace('\\', '/', (string) (FineProcessLog::query()->find($this->processLogId)?->local_attachment_dir ?? '')), '/');
        }

        if ($dir !== '' && Storage::disk('local')->exists($dir)) {
            foreach (Storage::disk('local')->files($dir) as $relative) {
                $name = basename($relative);
                $lower = strtolower($name);

                if ($lower === 'combined.extract.txt') {
                    if ($this->sourceExtractText === '') {
                        $this->sourceExtractText = trim((string) Storage::disk('local')->get($relative));
                    }

                    continue;
                }

                if (str_ends_with($lower, '.extract.txt')) {
                    continue;
                }

                $this->sourceFiles[] = [
                    'key' => 'local:'.$relative,
                    'name' => $name,
                    'source' => 'local',
                    'path' => $relative,
                    'bytes' => (int) Storage::disk('local')->size($relative),
                    'kind' => $this->sourceFileKind($name),
                ];
            }
        }

        if ($this->sourceFiles === [] && $messageId !== '' && ! empty($row['has_attachments'])) {
            try {
                foreach ($inbox->listFileAttachments($messageId) as $att) {
                    $name = (string) ($att['name'] ?? 'attachment.bin');
                    $this->sourceFiles[] = [
                        'key' => 'graph:'.($att['id'] ?? $name),
                        'name' => $name,
                        'source' => 'graph',
                        'graph_id' => (string) ($att['id'] ?? ''),
                        'bytes' => (int) ($att['size'] ?? 0),
                        'kind' => $this->sourceFileKind($name),
                    ];
                }
            } catch (Throwable) {
                // Non-fatal — operator can still read the mail body.
            }
        }
    }

    /**
     * Graph HTML for iframe preview: strip scripts/handlers, keep final layout/CSS.
     */
    private function prepareInboundMailHtml(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        // If Graph/store handed us escaped markup, unescape once so the iframe renders it.
        if (! str_contains($html, '<') && str_contains($html, '&lt;')) {
            $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/on\w+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/javascript\s*:/i', '', $html) ?? $html;

        if (! preg_match('/<html[\s>]/i', $html)) {
            $html = '<!DOCTYPE html><html><head><meta charset="utf-8">'
                .'<meta name="viewport" content="width=device-width, initial-scale=1">'
                .'<base target="_blank" rel="noopener">'
                .'<style>body{margin:16px;font:14px/1.45 system-ui,sans-serif;color:#0f172a;word-break:break-word;} img{max-width:100%;height:auto;}</style>'
                .'</head><body>'.$html.'</body></html>';
        }

        return $html;
    }

    private function sourceFileKind(string $name): string
    {
        $lower = strtolower($name);
        if (str_ends_with($lower, '.pdf')) {
            return 'pdf';
        }
        if (preg_match('/\.(jpe?g|png|webp|gif|bmp)$/i', $lower)) {
            return 'image';
        }
        if (preg_match('/\.(edoc|asice)$/i', $lower)) {
            return 'edoc';
        }

        return 'file';
    }

    public function downloadSourceFile(string $key): ?StreamedResponse
    {
        $file = $this->findSourceFile($key);
        if ($file === null) {
            Notification::make()->warning()->title('Attachment not found')->send();

            return null;
        }

        try {
            if (($file['source'] ?? '') === 'local') {
                $relative = (string) ($file['path'] ?? '');
                if ($relative === '' || ! Storage::disk('local')->exists($relative)) {
                    Notification::make()->warning()->title('Local file missing')->send();

                    return null;
                }

                return response()->streamDownload(
                    static function () use ($relative): void {
                        echo Storage::disk('local')->get($relative);
                    },
                    (string) ($file['name'] ?? 'attachment.bin'),
                    ['Content-Type' => $this->mimeForSourceFile($file)],
                );
            }

            if (($file['source'] ?? '') === 'graph') {
                $messageId = (string) ($this->previewMessage['id'] ?? '');
                $attachmentId = (string) ($file['graph_id'] ?? '');
                if ($messageId === '' || $attachmentId === '') {
                    Notification::make()->warning()->title('Graph attachment id missing')->send();

                    return null;
                }

                $bytes = app(MicrosoftGraphInbox::class)->downloadAttachmentContent($messageId, $attachmentId);

                return response()->streamDownload(
                    static function () use ($bytes): void {
                        echo $bytes;
                    },
                    (string) ($file['name'] ?? 'attachment.bin'),
                    ['Content-Type' => $this->mimeForSourceFile($file)],
                );
            }
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Download failed')->body($e->getMessage())->send();
        }

        return null;
    }

    public function openSourceFile(string $key): mixed
    {
        $file = $this->findSourceFile($key);
        if ($file === null) {
            Notification::make()->warning()->title('Attachment not found')->send();

            return null;
        }

        $kind = (string) ($file['kind'] ?? 'file');
        if (! in_array($kind, ['pdf', 'image'], true)) {
            return $this->downloadSourceFile($key);
        }

        try {
            if (($file['source'] ?? '') === 'local') {
                $relative = (string) ($file['path'] ?? '');
                $absolute = Storage::disk('local')->path($relative);
                if (! is_file($absolute)) {
                    Notification::make()->warning()->title('Local file missing')->send();

                    return null;
                }

                return response()->file($absolute, [
                    'Content-Type' => $this->mimeForSourceFile($file),
                    'Content-Disposition' => 'inline; filename="'.addslashes((string) ($file['name'] ?? 'file')).'"',
                ]);
            }

            if (($file['source'] ?? '') === 'graph') {
                $messageId = (string) ($this->previewMessage['id'] ?? '');
                $attachmentId = (string) ($file['graph_id'] ?? '');
                $bytes = app(MicrosoftGraphInbox::class)->downloadAttachmentContent($messageId, $attachmentId);
                $name = (string) ($file['name'] ?? 'attachment.bin');

                return response()->streamDownload(
                    static function () use ($bytes): void {
                        echo $bytes;
                    },
                    $name,
                    [
                        'Content-Type' => $this->mimeForSourceFile($file),
                        'Content-Disposition' => 'inline; filename="'.addslashes($name).'"',
                    ],
                );
            }
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Open failed')->body($e->getMessage())->send();
        }

        return null;
    }

    /**
     * @return array{key:string,name:string,source:string,path?:string,graph_id?:string,bytes:int,kind:string}|null
     */
    private function findSourceFile(string $key): ?array
    {
        foreach ($this->sourceFiles as $file) {
            if (($file['key'] ?? '') === $key) {
                return $file;
            }
        }

        return null;
    }

    /**
     * @param  array{name?:string,kind?:string}  $file
     */
    private function mimeForSourceFile(array $file): string
    {
        return match ($file['kind'] ?? $this->sourceFileKind((string) ($file['name'] ?? ''))) {
            'pdf' => 'application/pdf',
            'image' => match (strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION))) {
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                default => 'image/jpeg',
            },
            'edoc' => 'application/vnd.etsi.asic-e+zip',
            default => 'application/octet-stream',
        };
    }

    /**
     * @return list<int>
     */
    private function indexesForSelectedIds(): array
    {
        $wanted = array_fill_keys(array_map('strval', $this->selectedIds), true);
        $indexes = [];
        foreach ($this->results as $index => $row) {
            $id = (string) ($row['id'] ?? '');
            if ($id !== '' && isset($wanted[$id])) {
                $indexes[] = (int) $index;
            }
        }

        return $indexes;
    }

    private function applyLookupToRow(int $index): void
    {
        $row = $this->results[$index] ?? null;
        if (! is_array($row)) {
            throw new \RuntimeException('Message not found');
        }

        $result = app(IncomingFineLookup::class)->lookup($row, Auth::id());
        $rentals = [];
        foreach (($result['rentals'] ?? []) as $rental) {
            if (! is_array($rental)) {
                continue;
            }
            $rentals[] = array_merge(
                app(WheelsysClient::class)->rentalAsWheelsysDraft(
                    $rental,
                    $result['ticket_date'] !== '' ? $result['ticket_date'] : null,
                    $result['ticket_datetime'] !== '' ? $result['ticket_datetime'] : null,
                ),
                [
                    'ticket_number' => $result['reference'] !== '' ? $result['reference'] : null,
                    '_source' => 'rental',
                ]
            );
        }

        $wheelsys = is_array($result['wheelsys'] ?? null) ? $result['wheelsys'] : null;
        $merged = array_merge($row, $result['message'], [
            'in_vio' => true,
            'lookup_status' => $result['lookup_status'],
            'lookup_detail' => $result['lookup_detail'],
            'lookup_missing' => is_array($result['lookup_missing'] ?? null)
                ? array_values(array_map('strval', $result['lookup_missing']))
                : [],
            'vehicle' => $result['vehicle'] !== ''
                ? $result['vehicle']
                : strtoupper(trim((string) ($wheelsys['vehicle'] ?? ''))),
            'driver_name' => trim((string) ($wheelsys['driver'] ?? $result['log']->driver_name ?? '')),
            'reference' => $result['reference'],
            'ticket_date' => $result['ticket_date'],
            'ticket_time' => $result['ticket_time'],
            'wheelsys' => $wheelsys,
            'rentals' => $rentals,
            'draft' => $result['draft'],
            'process_log_id' => $result['log']->id,
            'workflow_steps' => $result['log']->workflow['steps'] ?? [],
            'attachment_text' => $result['message']['attachment_text'] ?? '',
            'local_attachment_dir' => $result['log']->local_attachment_dir,
            'excel_exported' => $result['log']->wheels_export_status === FineProcessLog::WHEELS_EXPORT_EXPORTED,
            'excel_batch' => $result['log']->wheels_export_batch,
            'excel_sharepoint_url' => $result['log']->wheels_export_sharepoint_url,
            'wheels_import_status' => $result['log']->wheels_import_status,
            'wheels_export_status' => $result['log']->wheels_export_status,
            'in_excel_queue' => $result['log']->wheels_export_status === FineProcessLog::WHEELS_EXPORT_PENDING
                && filled($result['log']->vehicle)
                && filled($result['log']->driver_name),
        ]);
        $merged['selection_locked'] = $this->rowSelectionLocked($merged);
        $this->results[$index] = $merged;
    }

    /**
     * Assisted ANR/ePaslaugos helpers for Preview (no portal API — copy + open).
     *
     * @return array<string, mixed>|null
     */
    public function getEpaslaugosAssistProperty(): ?array
    {
        if (! is_array($this->previewDraft) || ($this->previewDraft['reply_channel'] ?? '') !== 'epaslaugos') {
            return null;
        }

        return app(EpaslaugosAssist::class)->build(
            $this->previewDraft,
            is_array($this->wheelsysSelected) ? $this->wheelsysSelected : null,
            $this->epaslaugosPersonType,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLatviaAssistProperty(): ?array
    {
        if (! is_array($this->previewDraft) || ($this->previewDraft['info_pdf_template'] ?? '') !== 'latvia') {
            return null;
        }

        $log = $this->processLogId ? FineProcessLog::query()->find($this->processLogId) : null;

        return app(LatviaAuthorityPackageService::class)->assist($this->previewDraft, $log);
    }

    public function updatedEpaslaugosPersonType(): void
    {
        // Livewire recomputes computed property on next render.
    }

    public function notifyClipboardCopied(string $label = 'Copied'): void
    {
        Notification::make()
            ->success()
            ->title($label)
            ->send();
    }

    public function openTemplateEditor(): void
    {
        $this->showTemplateEditor = true;
    }

    public function closeTemplateEditor(): void
    {
        $this->showTemplateEditor = false;
        $this->refreshPreviewHtml();
    }

    public function refreshPreviewHtml(): void
    {
        if (! is_array($this->previewDraft)) {
            return;
        }

        $this->previewDraft = app(IncomingFineReplyBuilder::class)->refreshHtml($this->previewDraft);
    }

    public function clearSearchTicketDate(): void
    {
        $this->searchTicketDate = null;
        // Reset date = search all rentals for the plate (no covering-date filter).
        $this->searchWheelsys();
    }

    /**
     * Value for datetime-local input (Y-m-d\TH:i), preferring full offense moment.
     */
    private function ticketMomentForSearchInput(mixed $datetime, mixed $date = null, mixed $time = null): ?string
    {
        $datetime = trim((string) ($datetime ?? ''));
        $date = trim((string) ($date ?? ''));
        $time = trim((string) ($time ?? ''));

        try {
            if ($datetime !== '') {
                return \Illuminate\Support\Carbon::parse($datetime)->format('Y-m-d\TH:i');
            }
            if ($date !== '' && $time !== '') {
                return \Illuminate\Support\Carbon::parse($date.' '.$time)->format('Y-m-d\TH:i');
            }
            if ($date !== '') {
                return \Illuminate\Support\Carbon::parse($date)->format('Y-m-d\T00:00');
            }
        } catch (Throwable) {
            return $date !== '' ? $date : null;
        }

        return null;
    }

    public function searchWheelsys(): void
    {
        $vehicle = strtoupper(trim($this->searchVehicle));
        if ($vehicle === '') {
            Notification::make()->warning()->title('Enter a vehicle number')->send();

            return;
        }

        $this->wheelsysSelected = null;
        $this->wheelsysSelectedIndex = null;
        $client = app(WheelsysClient::class);

        try {
            $rawMoment = filled($this->searchTicketDate) ? trim((string) $this->searchTicketDate) : null;
            $ticketDate = $rawMoment !== null && $rawMoment !== '' ? substr($rawMoment, 0, 10) : null;
            // T00:00 is used as date-only placeholder in datetime-local — keep day-level covering.
            $ticketDateTime = ($rawMoment !== null
                && preg_match('/\d{1,2}:\d{2}/', $rawMoment)
                && ! preg_match('/T00:00(:00)?$/', $rawMoment))
                ? str_replace('T', ' ', $rawMoment)
                : null;

            // Prefer rentals covering the offense date+time (needed before Create).
            $rentals = $ticketDate
                ? $client->findRentalsCoveringDate($vehicle, $ticketDate, $ticketDateTime)
                : $client->searchRentalsByVehicle($vehicle);

            $this->wheelsysResults = array_map(
                fn (array $rental): array => array_merge(
                    $client->rentalAsWheelsysDraft($rental, $ticketDate, $ticketDateTime),
                    [
                        'ticket_number' => $this->previewDraft['reference'] ?? null,
                        '_source' => 'rental',
                    ]
                ),
                $rentals
            );

            // Also show existing traffic-violation rows (may already exist).
            $violations = $client->searchTrafficViolations($vehicle, $ticketDate);
            foreach ($violations as $violation) {
                $violation['_source'] = 'violation';
                $this->wheelsysResults[] = $violation;
            }
        } catch (Throwable $e) {
            $this->wheelsysResults = [];
            Notification::make()->danger()->title('Wheelsys search failed')->body($e->getMessage())->send();

            return;
        }

        if ($this->wheelsysResults === []) {
            Notification::make()
                ->warning()
                ->title('No Wheelsys rows found')
                ->body('No rental covering this plate/date and no existing violation. Use Create in Wheels.')
                ->send();

            return;
        }

        if (count($this->wheelsysResults) === 1) {
            $this->selectWheelsys(0, notify: false);
        }

        Notification::make()
            ->success()
            ->title('Wheelsys search complete')
            ->body(
                count($this->wheelsysResults).' row(s)'
                .(count($this->wheelsysResults) === 1 ? ' · selected automatically' : ' · pick one with radio')
            )
            ->send();
    }

    public function updatedWheelsysSelectedIndex(mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $this->selectWheelsys((int) $value);
    }

    public function selectWheelsys(int $index, bool $notify = true): void
    {
        $row = $this->wheelsysResults[$index] ?? null;
        if (! is_array($row) || ! is_array($this->previewMessage)) {
            return;
        }

        $this->wheelsysSelectedIndex = $index;
        if (($row['_source'] ?? '') === 'rental' || (($row['source'] ?? null) === 'rental')) {
            try {
                $row = app(WheelsysClient::class)->enrichDraftWithDriver($row);
            } catch (Throwable) {
                // Address enrichment is best-effort.
            }
        }
        $this->wheelsysSelected = $row;
        $this->wheelsysResults[$index] = $row;
        $moment = $this->ticketMomentForSearchInput(
            $row['ticket_datetime'] ?? null,
            $row['ticket_date'] ?? null,
            $row['ticket_time'] ?? null,
        );
        if ($moment !== null) {
            $this->searchTicketDate = $moment;
        }
        if (filled($row['vehicle'] ?? null)) {
            $this->searchVehicle = strtoupper((string) $row['vehicle']);
        }

        try {
            $this->previewDraft = app(IncomingFineReplyBuilder::class)->build($this->previewMessage, $row);
            $this->syncPreviewBackToResults();
            if ($notify) {
                Notification::make()
                    ->success()
                    ->title('Draft filled from Wheelsys')
                    ->body(($row['driver'] ?? '—').' / '.($row['vehicle'] ?? '—'))
                    ->send();
            }
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Could not rebuild draft')->body($e->getMessage())->send();
        }
    }

    private function syncPreviewBackToResults(): void
    {
        if ($this->previewIndex === null || ! isset($this->results[$this->previewIndex])) {
            return;
        }

        $i = $this->previewIndex;
        $wheelsys = $this->wheelsysSelected;
        $found = is_array($wheelsys)
            && filled($wheelsys['vehicle'] ?? null)
            && filled($wheelsys['driver'] ?? null);
        $this->results[$i]['wheelsys'] = $wheelsys;
        $this->results[$i]['rentals'] = $this->wheelsysResults;
        $this->results[$i]['draft'] = $this->previewDraft;
        $this->results[$i]['vehicle'] = (string) ($wheelsys['vehicle'] ?? $this->results[$i]['vehicle'] ?? '');
        $this->results[$i]['driver_name'] = (string) ($wheelsys['driver'] ?? '');
        $this->results[$i]['ticket_date'] = (string) ($wheelsys['ticket_date'] ?? $this->results[$i]['ticket_date'] ?? '');
        $this->results[$i]['reference'] = (string) ($wheelsys['ticket_number'] ?? $this->previewDraft['reference'] ?? $this->results[$i]['reference'] ?? '');
        $this->results[$i]['lookup_status'] = $found ? 'found' : 'not_found';
        $this->results[$i]['lookup_detail'] = $found
            ? trim(($wheelsys['agr_no'] ?? '').' · '.($wheelsys['driver'] ?? '—'))
            : (is_array($wheelsys) && filled($wheelsys['vehicle'] ?? null) && ! filled($wheelsys['driver'] ?? null)
                ? 'Missing driver — pick another rental'
                : 'Manual search — no rental selected');

        if (! empty($this->results[$i]['process_log_id']) && $found) {
            $log = FineProcessLog::query()->find((int) $this->results[$i]['process_log_id']);
            if ($log) {
                $ticketDate = $this->results[$i]['ticket_date'] ?: ($log->ticket_date?->format('Y-m-d'));
                $ticketTime = trim((string) ($this->results[$i]['ticket_time'] ?? $log->ticket_time ?? ''));
                // Human resolved a date-only / multi-driver case — default 09:00 for Wheelsys.
                if ($ticketDate && $ticketTime === '') {
                    $ticketTime = \App\Support\WheelsysTrafficViolationExcelExporter::DEFAULT_TICKET_TIME;
                    $this->results[$i]['ticket_time'] = $ticketTime;
                }

                $workflow = is_array($log->workflow) ? $log->workflow : [];
                $workflow['ambiguous_drivers'] = [];
                $workflow['day_driver_outcome'] = 'manual';
                $workflow['ticket_time_defaulted'] = ($ticketTime === \App\Support\WheelsysTrafficViolationExcelExporter::DEFAULT_TICKET_TIME)
                    && ! filled($wheelsys['ticket_time'] ?? null);

                $log->fill([
                    'vehicle' => $this->results[$i]['vehicle'] ?: null,
                    'reference' => $this->results[$i]['reference'] ?: null,
                    'ticket_date' => $ticketDate ?: null,
                    'ticket_time' => $ticketTime !== '' ? $ticketTime : null,
                    'agr_no' => $wheelsys['agr_no'] ?? null,
                    'rental_id' => isset($wheelsys['id']) ? (int) $wheelsys['id'] : $log->rental_id,
                    'driver_name' => $wheelsys['driver'] ?? null,
                    'status' => 'lookup_found',
                    'wheels_export_status' => $log->wheels_export_status === FineProcessLog::WHEELS_EXPORT_EXPORTED
                        ? FineProcessLog::WHEELS_EXPORT_EXPORTED
                        : FineProcessLog::WHEELS_EXPORT_PENDING,
                    'workflow' => $workflow,
                    'error' => null,
                ])->save();

                $this->results[$i]['in_excel_queue'] = true;
                $this->results[$i]['wheels_export_status'] = $log->wheels_export_status;
                $this->results[$i]['selection_locked'] = $this->rowSelectionLocked($this->results[$i]);

                try {
                    app(\App\Support\IncomingFineDocumentArchiver::class)->archive($log->fresh());
                } catch (Throwable) {
                    // Non-fatal — document can be archived on Generate import.
                }
            }
        }
    }

    public function sendReply(): void
    {
        if (! is_array($this->previewDraft)) {
            return;
        }

        if (($this->previewDraft['reply_channel'] ?? '') === 'epaslaugos') {
            Notification::make()
                ->warning()
                ->title('ANR/ROIK — ne el. laišku')
                ->body('Duomenis teikti per IRD ePaslaugas (Transporto priemonės naudotojo teikimas), ne į auto-anr-no-reply.')
                ->send();

            return;
        }

        if (($this->previewDraft['info_pdf_template'] ?? '') === 'latvia') {
            Notification::make()
                ->warning()
                ->title('Latvijos paketas')
                ->body('Naudokite „MarkSign LV raštą“, tada „Siųsti paketą į kanceleja“ (ne paprastą Send).')
                ->send();

            return;
        }

        try {
            $draft = app(IncomingFineReplyBuilder::class)->refreshHtml($this->previewDraft);
            $this->previewDraft = $draft;

            $log = $this->processLogId
                ? FineProcessLog::query()->find($this->processLogId)
                : null;

            $log = app(IncomingFineReplySender::class)->send($draft, $log);
            $this->processLogId = $log->id;

            $this->workflowSteps = array_map(function (array $step): array {
                if (($step['key'] ?? '') === 'send') {
                    $step['ok'] = true;
                    $step['detail'] = 'Sent to '.$draft['to'];
                }

                return $step;
            }, $this->workflowSteps);

            if ($log) {
                $workflow = is_array($log->workflow) ? $log->workflow : [];
                $workflow['steps'] = $this->workflowSteps;
                $log->fill(['workflow' => $workflow])->save();
            }

            if ($this->previewIndex !== null && isset($this->results[$this->previewIndex])) {
                $this->results[$this->previewIndex]['email_sent'] = true;
                $this->results[$this->previewIndex]['draft'] = $draft;
            }

            $pdfNote = '';
            if (is_array($log->workflow['info_pdf'] ?? null)) {
                $pdfNote = ' · PDF '.$log->workflow['info_pdf']['file_name'];
            }

            Notification::make()
                ->success()
                ->title('Reply sent')
                ->body('To: '.$draft['to'].$pdfNote)
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Send failed')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function createInWheelsys(): void
    {
        if (! is_array($this->previewMessage)) {
            return;
        }

        $wheelsys = $this->wheelsysSelected ?? [];
        $ticketDate = $wheelsys['ticket_date']
            ?? $this->searchTicketDate
            ?? ($this->previewDraft['ticket_date_hint'] ?? null);
        $ticketTime = $this->previewDraft['ticket_time_hint'] ?? null;
        $ticketDateTime = $wheelsys['ticket_datetime'] ?? null;
        if (! filled($ticketDateTime) && filled($ticketDate) && filled($ticketTime)) {
            $ticketDateTime = $ticketDate.'T'.$ticketTime;
        }

        $draft = [
            'vehicle' => $wheelsys['vehicle']
                ?? ($this->previewDraft['vehicle_hint'] ?? null)
                ?? $this->searchVehicle,
            'ticket_date' => $ticketDate,
            'ticket_datetime' => $ticketDateTime,
            'ticket_number' => $wheelsys['ticket_number']
                ?? ($this->previewDraft['reference'] ?? null),
            'authority_email' => $this->previewMessage['from_email'] ?? null,
            'authority_name' => $this->previewDraft['authority_name']
                ?? $this->previewMessage['authority_name']
                ?? null,
            'fine_received_date' => isset($this->previewMessage['received_at'])
                ? substr((string) $this->previewMessage['received_at'], 0, 10)
                : null,
            'driver' => $wheelsys['driver'] ?? null,
            'agr_no' => $wheelsys['agr_no'] ?? null,
            'birth_date' => $wheelsys['birth_date'] ?? null,
            'customer_email' => $wheelsys['customer_email'] ?? null,
        ];

        if (trim((string) ($draft['vehicle'] ?? '')) === '') {
            Notification::make()
                ->warning()
                ->title('Vehicle required')
                ->body('Search/select Wheelsys row or enter vehicle before Create in Wheels.')
                ->send();

            return;
        }

        session(['wheelsys_create_draft' => $draft]);
        $this->redirect(route('wheelsys.create-traffic-violation'));
    }

    public function process(int $index): void
    {
        $row = $this->results[$index] ?? null;
        if (! is_array($row)) {
            Notification::make()->danger()->title('Message not found')->send();

            return;
        }

        try {
            $result = app(IncomingFineWorkflow::class)->run($row, auth()->id());

            unset($this->results[$index]);
            $this->results = array_values($this->results);

            $this->processLogId = $result['log']->id;
            $this->workflowSteps = $result['steps'];
            $this->previewMessage = $result['message'];
            $this->previewDraft = $result['draft'];
            $this->wheelsysResults = $result['rentals'] !== []
                ? array_map(
                    fn (array $rental): array => array_merge(
                        app(WheelsysClient::class)->rentalAsWheelsysDraft(
                            $rental,
                            $result['fields']['ticket_date'] !== '' ? $result['fields']['ticket_date'] : null,
                            $result['fields']['ticket_datetime'] !== '' ? $result['fields']['ticket_datetime'] : null,
                        ),
                        [
                            'ticket_number' => $result['fields']['reference'] !== '' ? $result['fields']['reference'] : null,
                            '_source' => 'rental',
                        ]
                    ),
                    $result['rentals']
                )
                : [];
            $this->wheelsysSelected = $result['wheelsys'];
            $this->wheelsysSelectedIndex = $this->wheelsysResults !== [] ? 0 : null;
            $this->searchVehicle = (string) ($result['fields']['vehicle'] ?: ($result['draft']['vehicle_hint'] ?? ''));
            $this->searchTicketDate = $this->ticketMomentForSearchInput(
                $result['fields']['ticket_datetime'] !== '' ? $result['fields']['ticket_datetime'] : null,
                $result['fields']['ticket_date'] !== '' ? $result['fields']['ticket_date'] : null,
                $result['fields']['ticket_time'] !== '' ? $result['fields']['ticket_time'] : null,
            );
            $this->showTemplateEditor = false;
            $this->showPreview = true;

            $pendingOk = [
                'wheels_create',
                'send',
                'epaslaugos',
                'epaslaugos_atmesti',
                'latvia_marksign',
                'latvia_send',
            ];
            $failed = collect($result['steps'])->where('ok', false)->pluck('key')->all();
            $notification = Notification::make()
                ->title(count(array_diff($failed, $pendingOk)) === 0
                    ? 'Pipeline ready'
                    : 'Pipeline finished with gaps')
                ->body(collect($result['steps'])->map(fn (array $s): string => ($s['ok'] ? '✓' : '•').' '.$s['key'].': '.$s['detail'])->implode(' | '));

            if (count(array_diff($failed, $pendingOk)) === 0) {
                $notification->success();
            } else {
                $notification->warning();
            }
            $notification->send();
        } catch (Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Process failed')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function markWheelsCreated(): void
    {
        $this->persistWorkflowStep('wheels_create', 'Marked created in Wheelsys');

        if ($this->processLogId) {
            $log = FineProcessLog::query()->find($this->processLogId);
            if ($log && $log->wheels_export_status === FineProcessLog::WHEELS_EXPORT_PENDING) {
                $log->fill([
                    'wheels_export_status' => FineProcessLog::WHEELS_EXPORT_EXPORTED,
                    'wheels_export_batch' => $log->wheels_export_batch ?: ('manual-'.now()->format('Ymd-His')),
                    'wheels_exported_at' => now(),
                    'wheels_exported_by' => auth()->id(),
                ])->save();
            }

            if ($log) {
                try {
                    app(IncomingFineMailboxFolders::class)->moveToPending($log->fresh());
                } catch (Throwable) {
                    // Non-fatal.
                }
            }
        }

        Notification::make()->success()->title('Wheels create marked done')->send();
    }

    public function markEpaslaugosDone(): void
    {
        $this->persistWorkflowStep(
            'epaslaugos',
            'Submitted via ePaslaugos · Atmesti check pending'
        );
        if ($this->previewIndex !== null && isset($this->results[$this->previewIndex])) {
            $this->results[$this->previewIndex]['epaslaugos_done'] = true;
        }

        if ($this->processLogId) {
            $log = FineProcessLog::query()->find($this->processLogId);
            if ($log) {
                $workflow = is_array($log->workflow) ? $log->workflow : [];
                $workflow['epaslaugos'] = [
                    'submitted_at' => now()->toIso8601String(),
                    'person_type' => $this->epaslaugosPersonType,
                    'atmesti_status' => 'pending',
                    'atmesti_checked_at' => null,
                    'atmesti_note' => null,
                ];
                $workflow['steps'] = $this->workflowSteps;
                $log->fill([
                    'workflow' => $workflow,
                    'email_sent_at' => $log->email_sent_at ?: now(),
                    'status' => 'epaslaugos_done',
                ])->save();

                try {
                    app(IncomingFineMailboxFolders::class)->moveToProcessed($log->fresh());
                } catch (Throwable) {
                    // Non-fatal.
                }
            }
        }

        Notification::make()
            ->success()
            ->title('ePaslaugos marked done')
            ->body('Įrašas įtrauktas į Atmesti follow-up eilę.')
            ->send();
    }

    public function prepareLatviaMarksign(): void
    {
        if (! is_array($this->previewDraft) || ! $this->processLogId) {
            Notification::make()->warning()->title('Process fine first')->send();

            return;
        }

        try {
            $log = FineProcessLog::query()->findOrFail($this->processLogId);
            $draft = app(IncomingFineReplyBuilder::class)->refreshHtml($this->previewDraft);
            if (is_array($this->wheelsysSelected)) {
                $draft['wheelsys'] = $this->wheelsysSelected;
            }
            $this->previewDraft = $draft;

            $result = app(LatviaAuthorityPackageService::class)->prepareLetterForMarksign($log, $draft);
            $this->persistWorkflowStep('latvia_marksign', 'Awaiting MarkSign · '.$result['uuid']);

            Notification::make()
                ->success()
                ->title('LV raštas MarkSign’e')
                ->body('UUID: '.$result['uuid'])
                ->persistent()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Latvia MarkSign failed')
                ->body($e->getMessage())
                ->persistent()
                ->send();
        }
    }

    public function sendLatviaPackage(): void
    {
        if (! is_array($this->previewDraft) || ! $this->processLogId) {
            return;
        }

        try {
            $log = FineProcessLog::query()->findOrFail($this->processLogId);
            $draft = app(IncomingFineReplyBuilder::class)->refreshHtml($this->previewDraft);
            if (is_array($this->wheelsysSelected)) {
                $draft['wheelsys'] = $this->wheelsysSelected;
            }

            $rntRelative = null;
            if ($this->latviaRntUpload instanceof TemporaryUploadedFile) {
                $rntRelative = $this->latviaRntUpload->store('latvia-rnt', 'local');
            } elseif (is_string($this->latviaRntUpload) && $this->latviaRntUpload !== ''
                && Storage::disk('local')->exists($this->latviaRntUpload)) {
                $rntRelative = $this->latviaRntUpload;
            }

            $result = app(LatviaAuthorityPackageService::class)->sendPackage(
                $log,
                $draft,
                $rntRelative,
            );

            $this->persistWorkflowStep(
                'latvia_send',
                'Sent to '.$result['to'].' · '.$result['attachments'].' attachment(s)'
            );

            Notification::make()
                ->success()
                ->title('Latvijos paketas išsiųstas')
                ->body($result['to'].' · '.$result['attachments'].' priedai')
                ->persistent()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Latvia package send failed')
                ->body($e->getMessage())
                ->persistent()
                ->send();
        }
    }

    private function persistWorkflowStep(string $key, string $detail): void
    {
        $this->workflowSteps = array_map(function (array $step) use ($key, $detail): array {
            if (($step['key'] ?? '') === $key) {
                $step['ok'] = true;
                $step['detail'] = $detail;
            }

            return $step;
        }, $this->workflowSteps);

        if (! $this->processLogId) {
            return;
        }

        $log = FineProcessLog::query()->find($this->processLogId);
        if (! $log) {
            return;
        }

        $workflow = is_array($log->workflow) ? $log->workflow : [];
        $workflow['steps'] = $this->workflowSteps;
        $log->fill(['workflow' => $workflow])->save();
    }

}
