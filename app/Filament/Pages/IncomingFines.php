<?php

namespace App\Filament\Pages;

use App\Models\Authority;
use App\Models\EmailSetting;
use App\Models\FineProcessLog;
use App\Support\EpaslaugosAssist;
use App\Support\IncomingFineLookup;
use App\Support\IncomingFineMailScanner;
use App\Support\IncomingFineReplyBuilder;
use App\Support\IncomingFineReplySender;
use App\Support\IncomingFineWorkflow;
use App\Support\LatviaAuthorityPackageService;
use App\Support\WheelsysClient;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
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

    protected static ?string $navigationLabel = 'New fines';

    protected static ?string $navigationGroup = 'Fines';

    protected static ?string $title = 'New fines';

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
                    ->required()
                    ->helperText('Inbox only. Already-handled rows show as Already have. Wheelsys login: Settings → System settings.'),
            ])
            ->statePath('data');
    }

    /**
     * @return array<string|int, string>
     */
    public function getAuthorityFilterOptionsProperty(): array
    {
        $options = Authority::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        return ['' => 'All authorities', 'unmatched' => 'Unmatched (—)'] + $options;
    }

    public function clearAuthorityFilter(): void
    {
        $this->data['authority_id'] = null;
    }

    /**
     * Fetched rows filtered by Authority select (preserves original indexes for Preview/Process).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFilteredResultsProperty(): array
    {
        $filter = $this->data['authority_id'] ?? null;
        if ($filter === null || $filter === '') {
            return $this->results;
        }

        // Keep original keys so process() indexes stay valid.
        if ($filter === 'unmatched') {
            return array_filter(
                $this->results,
                static fn (array $row): bool => empty($row['authority_id'])
            );
        }

        $id = (int) $filter;

        return array_filter(
            $this->results,
            static fn (array $row): bool => (int) ($row['authority_id'] ?? 0) === $id
        );
    }

    public function fetch(): void
    {
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

            // Drop any selected ids that are already in VIO (checkboxes disabled).
            $blocked = [];
            foreach ($this->results as $row) {
                if (! empty($row['in_vio']) || ! empty($row['in_excel_queue']) || ! empty($row['excel_exported'])) {
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
    }

    /**
     * @return list<string>
     */
    private function selectableFilteredIds(): array
    {
        $ids = [];
        foreach ($this->filteredResults as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (! empty($row['in_vio']) || ! empty($row['in_excel_queue']) || ! empty($row['excel_exported'])) {
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

        foreach ($indexes as $index) {
            try {
                $this->applyLookupToRow($index);
                if (($this->results[$index]['lookup_status'] ?? '') === 'found') {
                    $found++;
                } else {
                    $missing++;
                }
            } catch (Throwable $e) {
                $errors++;
                $this->results[$index]['lookup_status'] = 'not_found';
                $this->results[$index]['lookup_detail'] = $e->getMessage();
            }
        }

        $notification = Notification::make()
            ->title('Saved to VIO DB')
            ->body("Found {$found} · not found {$missing}"
                .($errors > 0 ? " · errors {$errors}" : '')
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

                continue;
            }

            $lookup = match (true) {
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

            $this->results[$i] = array_merge($row, [
                'in_vio' => true,
                'in_excel_queue' => $inExcelQueue,
                'process_log_id' => $log->id,
                'vehicle' => $log->vehicle ?: ($row['vehicle'] ?? ''),
                'driver_name' => $log->driver_name ?: ($row['driver_name'] ?? ''),
                'reference' => $log->reference ?: ($row['reference'] ?? ''),
                'ticket_date' => $log->ticket_date?->format('Y-m-d') ?: ($row['ticket_date'] ?? ''),
                'lookup_status' => $lookup,
                'lookup_detail' => $log->error
                    ?: trim(($log->agr_no ? $log->agr_no.' · ' : '').($log->driver_name ?: '')),
                'excel_exported' => $excelExported,
                'excel_batch' => $log->wheels_export_batch,
                'excel_sharepoint_url' => $log->wheels_export_sharepoint_url,
                'wheels_import_status' => $log->wheels_import_status,
                'wheels_export_status' => $log->wheels_export_status,
                'workflow_steps' => is_array($log->workflow['steps'] ?? null) ? $log->workflow['steps'] : [],
            ]);
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
            $this->searchTicketDate = filled($row['ticket_date'] ?? null) ? (string) $row['ticket_date'] : null;
            $this->processLogId = isset($row['process_log_id']) ? (int) $row['process_log_id'] : null;
            $this->workflowSteps = is_array($row['workflow_steps'] ?? null) ? $row['workflow_steps'] : [];
            $this->showTemplateEditor = false;
            $this->showPreview = true;
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Details failed')->body($e->getMessage())->send();
        }
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
        $this->results[$index] = array_merge($row, $result['message'], [
            'in_vio' => true,
            'lookup_status' => $result['lookup_status'],
            'lookup_detail' => $result['lookup_detail'],
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
            'excel_exported' => $result['log']->wheels_export_status === FineProcessLog::WHEELS_EXPORT_EXPORTED,
            'excel_batch' => $result['log']->wheels_export_batch,
            'excel_sharepoint_url' => $result['log']->wheels_export_sharepoint_url,
            'wheels_import_status' => $result['log']->wheels_import_status,
            'wheels_export_status' => $result['log']->wheels_export_status,
            'in_excel_queue' => $result['log']->wheels_export_status === FineProcessLog::WHEELS_EXPORT_PENDING
                && filled($result['log']->vehicle)
                && filled($result['log']->driver_name),
        ]);
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
            $ticketDate = filled($this->searchTicketDate) ? $this->searchTicketDate : null;

            // Prefer rentals covering the offense date (needed before Create).
            $rentals = $ticketDate
                ? $client->findRentalsCoveringDate($vehicle, $ticketDate)
                : $client->searchRentalsByVehicle($vehicle);

            $this->wheelsysResults = array_map(
                fn (array $rental): array => array_merge(
                    $client->rentalAsWheelsysDraft($rental, $ticketDate),
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
        if (filled($row['ticket_date'] ?? null)) {
            $this->searchTicketDate = (string) $row['ticket_date'];
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
                $log->fill([
                    'vehicle' => $this->results[$i]['vehicle'] ?: null,
                    'reference' => $this->results[$i]['reference'] ?: null,
                    'ticket_date' => $this->results[$i]['ticket_date'] ?: null,
                    'agr_no' => $wheelsys['agr_no'] ?? null,
                    'driver_name' => $wheelsys['driver'] ?? null,
                    'status' => 'lookup_found',
                    'wheels_export_status' => $log->wheels_export_status === FineProcessLog::WHEELS_EXPORT_EXPORTED
                        ? FineProcessLog::WHEELS_EXPORT_EXPORTED
                        : FineProcessLog::WHEELS_EXPORT_PENDING,
                    'error' => null,
                ])->save();

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
            $ticketDateTime = $ticketDate.'T'.$ticketTime.':00';
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
            $this->searchTicketDate = $result['fields']['ticket_date'] !== ''
                ? $result['fields']['ticket_date']
                : null;
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
                $log->fill(['workflow' => $workflow])->save();
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
