<?php

namespace App\Filament\Concerns;

use App\Models\FineProcessLog;
use App\Support\EpaslaugosAssist;
use App\Support\FineReplyReadiness;
use App\Support\IncomingFineReplyBuilder;
use App\Support\IncomingFineReplySender;
use App\Support\MicrosoftGraphInbox;
use App\Support\WheelsysClient;
use App\Support\WheelsysRentalDocumentArchiver;
use App\Support\WheelsysTrafficViolationExcelExporter;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Details / informavimas modal for FineProcessLog rows (Imported fines).
 */
trait InteractsWithFineInformavimas
{
    public bool $showInformuoti = false;

    public ?int $informuotiLogId = null;

    /** @var array<string, mixed>|null */
    public ?array $previewDraft = null;

    /** @var array<string, mixed>|null */
    public ?array $previewMessage = null;

    /** @var list<array<string, mixed>> */
    public array $wheelsysResults = [];

    /** @var array<string, mixed>|null */
    public ?array $wheelsysSelected = null;

    public ?int $wheelsysSelectedIndex = null;

    public string $searchVehicle = '';

    public ?string $searchTicketDate = null;

    public bool $showTemplateEditor = false;

    public string $sourceMailHtml = '';

    public string $sourceExtractText = '';

    /** @var list<array{key:string,name:string,path?:string,bytes:int,kind:string}> */
    public array $sourceFiles = [];

    /** @var array{ready:bool,sent:bool,channel:string,missing:list<string>,label:string,detail:string,missing_labels:list<string>,severity:string}|null */
    public ?array $informuotiReadiness = null;

    /**
     * Files that would be attached on Send (notice + RNT contracts).
     *
     * @var array{notice:list<array{name:string,bytes:int}>,contracts:list<array{name:string,bytes:int}>,requires_contract:bool,error:?string}|null
     */
    public ?array $sendAttachmentPreview = null;

    public string $epaslaugosPersonType = EpaslaugosAssist::PERSON_LT_NATURAL;

    /**
     * Assisted ANR/ePaslaugos helpers (no portal API — open + copy).
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

    public function updatedEpaslaugosPersonType(): void
    {
        // Recompute assist fields on next render.
    }

    public function notifyClipboardCopied(string $label = 'Copied'): void
    {
        Notification::make()->success()->title($label)->send();
    }

    public function openInformuoti(int $logId): void
    {
        $log = FineProcessLog::query()->with('authority')->find($logId);
        if (! $log) {
            Notification::make()->danger()->title('Fine not found')->send();

            return;
        }

        $wheelsys = FineReplyReadiness::enrichAndStore($log);
        $log = $log->fresh();

        try {
            $draft = app(IncomingFineReplyBuilder::class)->buildForProcessLog($log, $wheelsys);
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Could not build reply draft')->body($e->getMessage())->send();

            return;
        }

        $readiness = FineReplyReadiness::forLog($log, is_array($wheelsys) ? $wheelsys : null);
        $draftMissing = is_array($draft['missing_fields'] ?? null) ? $draft['missing_fields'] : [];
        $missing = array_values(array_unique(array_merge($readiness['missing'], $draftMissing)));
        $this->informuotiReadiness = array_merge($readiness, [
            'missing' => $missing,
            'missing_labels' => FineReplyReadiness::missingLabels($missing),
            'severity' => FineReplyReadiness::missingSeverity($missing),
            'ready' => $missing === [] && ! empty($readiness['ready']),
        ]);

        $this->informuotiLogId = $log->id;
        $this->previewDraft = $draft;
        $this->previewMessage = [
            'id' => $log->graph_message_id,
            'from_email' => $log->from_email,
            'from_name' => $log->authority_name,
            'authority_id' => $log->authority_id,
            'authority_name' => $log->authority_name,
            'subject' => $log->subject,
            'received_at' => optional($log->received_at)?->toIso8601String(),
            'received_at_local' => optional($log->received_at)
                ?->timezone(config('app.timezone'))
                ?->format('Y-m-d H:i'),
        ];
        $this->wheelsysSelected = $wheelsys !== [] ? $wheelsys : null;
        $this->wheelsysResults = $this->wheelsysSelected ? [$this->wheelsysSelected] : [];
        $this->wheelsysSelectedIndex = $this->wheelsysResults !== [] ? 0 : null;
        $this->searchVehicle = strtoupper((string) ($log->vehicle ?? $draft['vehicle_hint'] ?? ''));
        $this->searchTicketDate = $this->ticketMomentForSearchInput(
            null,
            $log->ticket_date?->format('Y-m-d') ?? ($draft['ticket_date_hint'] ?? null),
            $log->ticket_time ?: ($draft['ticket_time_hint'] ?? null),
        );
        $this->showTemplateEditor = false;
        $this->loadInformuotiSourceMaterials($log);
        $this->refreshSendAttachmentPreview();
        $this->showInformuoti = true;
    }

    public function closeInformuoti(): void
    {
        $this->showInformuoti = false;
        $this->informuotiLogId = null;
        $this->previewDraft = null;
        $this->previewMessage = null;
        $this->wheelsysResults = [];
        $this->wheelsysSelected = null;
        $this->wheelsysSelectedIndex = null;
        $this->showTemplateEditor = false;
        $this->sourceMailHtml = '';
        $this->sourceExtractText = '';
        $this->sourceFiles = [];
        $this->informuotiReadiness = null;
        $this->sendAttachmentPreview = null;
    }

    public function refreshSendAttachmentPreview(): void
    {
        $this->sendAttachmentPreview = null;
        if (! $this->informuotiLogId || ! is_array($this->previewDraft)) {
            return;
        }

        $log = FineProcessLog::query()->with('authority')->find($this->informuotiLogId);
        if (! $log) {
            return;
        }

        try {
            $this->sendAttachmentPreview = app(IncomingFineReplySender::class)
                ->previewAttachments($this->previewDraft, $log);
            $this->sendAttachmentPreview['error'] = null;
        } catch (Throwable $e) {
            $this->sendAttachmentPreview = [
                'notice' => [],
                'contracts' => [],
                'requires_contract' => ! empty($this->previewDraft['requires_contract']),
                'error' => $e->getMessage(),
            ];
        }
    }

    public function fetchRntDocsForInformuoti(): void
    {
        if (! $this->informuotiLogId) {
            return;
        }

        $log = FineProcessLog::query()->find($this->informuotiLogId);
        if (! $log) {
            return;
        }

        try {
            $result = app(WheelsysRentalDocumentArchiver::class)->archiveForLog($log, force: true);
            Notification::make()
                ->success()
                ->title('RNT docs saved')
                ->body($result['count'].' file(s) → '.$result['folder'])
                ->send();
            $this->refreshSendAttachmentPreview();
        } catch (Throwable $e) {
            Notification::make()
                ->danger()
                ->title('RNT fetch failed')
                ->body($e->getMessage())
                ->persistent()
                ->send();
        }
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
            $rawMoment = filled($this->searchTicketDate) ? trim((string) $this->searchTicketDate) : null;
            $ticketDate = $rawMoment !== null && $rawMoment !== '' ? substr($rawMoment, 0, 10) : null;
            $ticketDateTime = ($rawMoment !== null
                && preg_match('/\d{1,2}:\d{2}/', $rawMoment)
                && ! preg_match('/T00:00(:00)?$/', $rawMoment))
                ? str_replace('T', ' ', $rawMoment)
                : null;

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

            foreach ($client->searchTrafficViolations($vehicle, $ticketDate) as $violation) {
                $violation['_source'] = 'violation';
                $this->wheelsysResults[] = $violation;
            }
        } catch (Throwable $e) {
            $this->wheelsysResults = [];
            Notification::make()->danger()->title('Wheelsys search failed')->body($e->getMessage())->send();

            return;
        }

        if ($this->wheelsysResults === []) {
            Notification::make()->warning()->title('No Wheelsys rows found')->send();

            return;
        }

        if (count($this->wheelsysResults) === 1) {
            $this->selectWheelsys(0, notify: false);
        }

        Notification::make()
            ->success()
            ->title('Wheelsys search complete')
            ->body(count($this->wheelsysResults).' row(s)')
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
            }
        }
        $this->wheelsysSelected = $row;
        $this->wheelsysResults[$index] = $row;

        if (filled($row['vehicle'] ?? null)) {
            $this->searchVehicle = strtoupper((string) $row['vehicle']);
        }

        try {
            $this->previewDraft = app(IncomingFineReplyBuilder::class)->build($this->previewMessage, $row);
            $this->persistInformuotiSelection($row);
            $this->refreshInformuotiReadiness($row);
            if ($notify) {
                Notification::make()
                    ->success()
                    ->title('Draft filled from Wheelsys')
                    ->body(($row['driver'] ?? '—').' / '.($row['vehicle'] ?? '—'))
                    ->send();
            }
            $this->notifyExternalCompanyFill($row);
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Could not rebuild draft')->body($e->getMessage())->send();
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function notifyExternalCompanyFill(array $row): void
    {
        $ext = is_array($row['_external_company'] ?? null) ? $row['_external_company'] : null;
        if ($ext === null || ($ext['status'] ?? '') !== 'filled' || ($ext['filled'] ?? []) === []) {
            return;
        }

        $parts = [];
        if (in_array('company', $ext['filled'], true)) {
            $parts[] = 'company name';
        }
        if (in_array('company_code', $ext['filled'], true)) {
            $parts[] = 'company code';
        }

        Notification::make()
            ->warning()
            ->title('Filled from external source')
            ->body(
                implode(' + ', $parts)
                .' · '.($ext['display_name'] ?? $ext['name'] ?? '—')
                .(filled($ext['code'] ?? null) ? ' · '.$ext['code'] : '')
                .' ('.($ext['source'] ?? 'rekvizitai.lt / JAR').')'
            )
            ->send();
    }

    /**
     * @param  array<string, mixed>|null  $wheelsys
     */
    private function refreshInformuotiReadiness(?array $wheelsys = null): void
    {
        if (! $this->informuotiLogId) {
            return;
        }

        $log = FineProcessLog::query()->with('authority')->find($this->informuotiLogId);
        if (! $log) {
            return;
        }

        $readiness = FineReplyReadiness::forLog($log, $wheelsys);
        $draftMissing = is_array($this->previewDraft['missing_fields'] ?? null)
            ? $this->previewDraft['missing_fields']
            : [];
        $missing = array_values(array_unique(array_merge($readiness['missing'], $draftMissing)));
        $this->informuotiReadiness = array_merge($readiness, [
            'missing' => $missing,
            'missing_labels' => FineReplyReadiness::missingLabels($missing),
            'severity' => FineReplyReadiness::missingSeverity($missing),
            'ready' => $missing === [] && ! empty($readiness['ready']),
        ]);
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
                ->body('Duomenis teikti per IRD ePaslaugas, ne į no-reply.')
                ->send();

            return;
        }

        try {
            $draft = app(IncomingFineReplyBuilder::class)->refreshHtml($this->previewDraft);
            $this->previewDraft = $draft;

            $missing = is_array($draft['missing_fields'] ?? null) ? $draft['missing_fields'] : [];
            $log = $this->informuotiLogId
                ? FineProcessLog::query()->find($this->informuotiLogId)
                : null;

            if (
                in_array('contract', $missing, true)
                || (
                    ! empty($draft['requires_contract'])
                    && (
                        ! filled($draft['agr_no'] ?? null)
                        || ($log !== null && ! filled($log->rental_id))
                    )
                )
            ) {
                Notification::make()
                    ->danger()
                    ->title('Contract (RNT) required')
                    ->body('Julianus: pick a Wheelsys rental so RNT + rental_id are set (needed to open/attach the contract PDF).')
                    ->send();

                return;
            }

            $log = app(IncomingFineReplySender::class)->send($draft, $log);
            $this->informuotiLogId = $log->id;

            $workflow = is_array($log->workflow) ? $log->workflow : [];
            $workflow['wheelsys'] = $this->wheelsysSelected;
            $workflow['steps'] = array_values(array_merge(
                is_array($workflow['steps'] ?? null) ? $workflow['steps'] : [],
                [['key' => 'authority_reply', 'ok' => true, 'detail' => 'Sent to '.$draft['to']]]
            ));
            $log->fill(['workflow' => $workflow])->save();

            Notification::make()
                ->success()
                ->title('Reply sent')
                ->body('To: '.$draft['to'])
                ->send();

            $this->closeInformuoti();
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Send failed')->body($e->getMessage())->send();
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
        $ticketTime = $this->previewDraft['ticket_time_hint']
            ?? WheelsysTrafficViolationExcelExporter::DEFAULT_TICKET_TIME;
        $ticketDateTime = $wheelsys['ticket_datetime'] ?? null;
        if (! filled($ticketDateTime) && filled($ticketDate) && filled($ticketTime)) {
            $ticketDateTime = substr((string) $ticketDate, 0, 10).'T'.$ticketTime;
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
            Notification::make()->warning()->title('Vehicle required')->send();

            return;
        }

        session(['wheelsys_create_draft' => $draft]);
        $this->redirect(route('wheelsys.create-traffic-violation'));
    }

    public function markWheelsCreated(): void
    {
        if (! $this->informuotiLogId) {
            return;
        }

        $log = FineProcessLog::query()->find($this->informuotiLogId);
        if (! $log) {
            return;
        }

        $workflow = is_array($log->workflow) ? $log->workflow : [];
        $workflow['steps'] = array_values(array_merge(
            is_array($workflow['steps'] ?? null) ? $workflow['steps'] : [],
            [['key' => 'wheels_create', 'ok' => true, 'detail' => 'Marked created in Wheelsys']]
        ));

        $fill = ['workflow' => $workflow];
        if ($log->wheels_import_status !== FineProcessLog::WHEELS_IMPORT_IMPORTED) {
            $fill['wheels_import_status'] = FineProcessLog::WHEELS_IMPORT_IMPORTED;
            $fill['wheels_imported_at'] = now();
            $fill['wheels_imported_by'] = Auth::id();
        }
        $log->fill($fill)->save();

        try {
            app(\App\Support\IncomingFineMailboxFolders::class)->moveToPending($log->fresh());
        } catch (Throwable) {
            // Non-fatal.
        }

        Notification::make()->success()->title('Wheels create marked done')->send();
    }

    public function markEpaslaugosDone(): void
    {
        if (! $this->informuotiLogId) {
            return;
        }

        $log = FineProcessLog::query()->find($this->informuotiLogId);
        if (! $log) {
            return;
        }

        $workflow = is_array($log->workflow) ? $log->workflow : [];
        $workflow['steps'] = array_values(array_merge(
            is_array($workflow['steps'] ?? null) ? $workflow['steps'] : [],
            [['key' => 'epaslaugos', 'ok' => true, 'detail' => 'Marked ePaslaugos done']]
        ));
        $log->fill([
            'workflow' => $workflow,
            'email_sent_at' => $log->email_sent_at ?: now(),
            'status' => 'epaslaugos_done',
        ])->save();

        try {
            app(\App\Support\IncomingFineMailboxFolders::class)->moveToProcessed($log->fresh());
        } catch (Throwable) {
            // Non-fatal.
        }

        Notification::make()->success()->title('ePaslaugos marked done')->send();
        $this->closeInformuoti();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function persistInformuotiSelection(array $row): void
    {
        if (! $this->informuotiLogId) {
            return;
        }

        $log = FineProcessLog::query()->find($this->informuotiLogId);
        if (! $log) {
            return;
        }

        $workflow = is_array($log->workflow) ? $log->workflow : [];
        $workflow['wheelsys'] = $row;

        $log->fill([
            'vehicle' => filled($row['vehicle'] ?? null) ? strtoupper((string) $row['vehicle']) : $log->vehicle,
            'driver_name' => filled($row['driver'] ?? null) ? (string) $row['driver'] : $log->driver_name,
            'agr_no' => filled($row['agr_no'] ?? null) ? (string) $row['agr_no'] : $log->agr_no,
            'rental_id' => isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : $log->rental_id,
            'reference' => filled($row['ticket_number'] ?? null) ? (string) $row['ticket_number'] : $log->reference,
            'ticket_date' => filled($row['ticket_date'] ?? null) ? (string) $row['ticket_date'] : $log->ticket_date,
            'workflow' => $workflow,
        ])->save();

        if (is_array($this->previewDraft)) {
            $this->previewDraft['agr_no'] = $log->agr_no;
            $this->previewDraft['requires_contract'] = $log->authority?->requiresContract()
                ?? ! empty($this->previewDraft['requires_contract']);
            $this->refreshSendAttachmentPreview();
        }
    }

    private function ticketMomentForSearchInput(mixed $datetime, mixed $date, mixed $time): ?string
    {
        if (filled($datetime)) {
            $raw = trim((string) $datetime);
            if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{1,2}):(\d{2})/', $raw, $m)) {
                return $m[1].'T'.sprintf('%02d:%02d', (int) $m[2], (int) $m[3]);
            }
            if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $m)) {
                return $m[1].'T00:00';
            }
        }

        $date = filled($date) ? substr((string) $date, 0, 10) : '';
        if ($date === '') {
            return null;
        }

        $time = trim((string) ($time ?? ''));
        if ($time !== '' && preg_match('/^(\d{1,2}):(\d{2})/', $time, $m)) {
            return $date.'T'.sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return $date.'T00:00';
    }

    private function loadInformuotiSourceMaterials(FineProcessLog $log): void
    {
        $this->sourceMailHtml = '';
        $this->sourceExtractText = '';
        $this->sourceFiles = [];

        $messageId = trim((string) ($log->graph_message_id ?? ''));
        $inbox = app(MicrosoftGraphInbox::class);

        if ($messageId !== '' && ! preg_match('/^(physical|court|manual|manual-send)-/i', $messageId)) {
            try {
                $this->sourceMailHtml = $this->prepareInboundMailHtml($inbox->getMessageBodyHtml($messageId));
            } catch (Throwable) {
                $this->sourceMailHtml = '';
            }
            if ($this->sourceMailHtml === '') {
                try {
                    $text = $inbox->getMessageBodyText($messageId);
                    if ($text !== '') {
                        $this->sourceMailHtml = $this->prepareInboundMailHtml(
                            '<pre style="white-space:pre-wrap;margin:0;font:inherit;">'.e($text).'</pre>'
                        );
                    }
                } catch (Throwable) {
                }
            }
        }

        $dir = trim(str_replace('\\', '/', (string) ($log->local_attachment_dir ?? '')), '/');
        if ($dir === '' || ! Storage::disk('local')->exists($dir)) {
            return;
        }

        foreach (Storage::disk('local')->files($dir) as $relative) {
            $name = basename($relative);
            $lower = strtolower($name);

            if ($lower === 'combined.extract.txt') {
                $this->sourceExtractText = trim((string) Storage::disk('local')->get($relative));

                continue;
            }
            if (str_ends_with($lower, '.extract.txt')) {
                continue;
            }

            $this->sourceFiles[] = [
                'key' => 'local:'.$relative,
                'name' => $name,
                'path' => $relative,
                'bytes' => (int) Storage::disk('local')->size($relative),
                'kind' => match (true) {
                    str_ends_with($lower, '.pdf') => 'pdf',
                    (bool) preg_match('/\.(jpe?g|png|gif|webp)$/', $lower) => 'image',
                    default => 'file',
                },
            ];
        }
    }

    private function prepareInboundMailHtml(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

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
}
