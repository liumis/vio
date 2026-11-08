<?php

namespace App\Filament\Pages;

use App\Models\Authority;
use App\Models\FineProcessLog;
use App\Support\ActivityLogger;
use App\Support\IncomingFineDocumentArchiver;
use App\Support\MicrosoftGraphSharePoint;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Manual form for court decisions / nutarimai (instruction VIII).
 * No portal automation — checklist + archive + Wheelsys queue.
 */
class CourtDecisions extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Court decisions';

    protected static ?string $navigationGroup = 'Fines';

    protected static ?string $title = 'Teismo nutarimai (manual)';

    protected static ?string $slug = 'court-decisions';

    protected static ?int $navigationSort = 7;

    protected static string $view = 'filament.pages.court-decisions';

    public const LAWYER_EMAIL = 'kestutis.puidokas@gmail.com';

    public ?array $data = [];

    public function mount(): void
    {
        $teismasId = Authority::query()->where('email_pattern', '@teismas.lt')->value('id');

        $this->form->fill([
            'received_at' => now()->timezone(config('app.timezone'))->format('Y-m-d'),
            'authority_id' => $teismasId,
            'authority_name' => $teismasId
                ? Authority::query()->whereKey($teismasId)->value('name')
                : 'Teismas',
            'lawyer_email' => self::LAWYER_EMAIL,
            'lawyer_status' => 'forwarded_pending',
            'branch' => 'LT',
            'charge_fine_and_admin' => true,
            'payment_copy_to_teismas' => false,
            'attachments' => [],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Checklist (instrukcija VIII)')
                    ->description('Automatizacijos nėra — pažymėkite rankinius žingsnius.')
                    ->schema([
                        Forms\Components\TextInput::make('lawyer_email')
                            ->label('Teisininkas')
                            ->email()
                            ->required()
                            ->default(self::LAWYER_EMAIL)
                            ->helperText('Pirmiausia persiųsti @teismas.lt laišką teisininkui.'),
                        Forms\Components\Select::make('lawyer_status')
                            ->label('Teisininko statusas')
                            ->options([
                                'forwarded_pending' => 'Persiųsta — laukiama atsakymo',
                                'pay' => 'Atsakymas: reikia apmokėti',
                                'dispute' => 'Atsakymas: skundas / ginčas',
                            ])
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('branch')
                            ->label('Padalinys (kieno klientas)')
                            ->options([
                                'LT' => 'LT',
                                'LV' => 'LV (Santa / greenmotion.lv)',
                                'EE' => 'EE (greenmotion.ee)',
                            ])
                            ->required()
                            ->native(false)
                            ->helperText('Auksinė taisyklė: kieno klientas — to pajamos ir sąnaudos.'),
                        Forms\Components\Toggle::make('charge_fine_and_admin')
                            ->label('Iš kliento nuskaičiuoti baudą + administracinį mokestį')
                            ->default(true)
                            ->inline(false)
                            ->helperText('Įmonė sumokėjo teismui — abu mokesčiai klientui.'),
                        Forms\Components\Toggle::make('payment_copy_to_teismas')
                            ->label('Priminta: pavedimo kopija → vilniaus.apylinkes@teismas.lt (paskirtyje nutarimo Nr.)')
                            ->default(false)
                            ->inline(false),
                        Forms\Components\Textarea::make('checklist_notes')
                            ->label('Pastabos')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Fine details')
                    ->schema([
                        Forms\Components\TextInput::make('vehicle')
                            ->label('Vehicle (plate)')
                            ->required()
                            ->maxLength(32)
                            ->dehydrateStateUsing(fn (?string $state): string => strtoupper(trim((string) $state))),
                        Forms\Components\TextInput::make('reference')
                            ->label('Nutarimo / bylos Nr.')
                            ->required()
                            ->maxLength(120)
                            ->helperText('Dažnai E1-…'),
                        Forms\Components\DatePicker::make('ticket_date')
                            ->label('Ticket / offence date')
                            ->required()
                            ->native(false)
                            ->displayFormat('Y-m-d'),
                        Forms\Components\DatePicker::make('received_at')
                            ->label('Fine received date')
                            ->required()
                            ->native(false)
                            ->displayFormat('Y-m-d'),
                        Forms\Components\TextInput::make('driver_name')
                            ->label('Driver / customer')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('authority_id')
                            ->label('Authority')
                            ->options(fn (): array => Authority::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->live()
                            ->afterStateUpdated(function (?int $state, Forms\Set $set): void {
                                if ($state === null) {
                                    return;
                                }
                                $name = Authority::query()->whereKey($state)->value('name');
                                if (filled($name)) {
                                    $set('authority_name', $name);
                                }
                            }),
                        Forms\Components\TextInput::make('authority_name')
                            ->label('Authority name (override)')
                            ->maxLength(255),
                        Forms\Components\FileUpload::make('attachments')
                            ->label('Teismo dokumentai')
                            ->multiple()
                            ->required()
                            ->disk('local')
                            ->directory('fine-attachments/uploads')
                            ->visibility('private')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ])
                            ->maxFiles(15)
                            ->maxSize(20480)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $userId = Auth::id();

        $vehicle = strtoupper(trim((string) ($state['vehicle'] ?? '')));
        $reference = trim((string) ($state['reference'] ?? ''));
        $driver = trim((string) ($state['driver_name'] ?? ''));
        $ticketDate = $state['ticket_date'] ?? null;
        $receivedDate = $state['received_at'] ?? null;
        $authorityId = $state['authority_id'] ?? null;
        $authorityName = trim((string) ($state['authority_name'] ?? ''));
        $attachments = $state['attachments'] ?? [];
        $lawyerStatus = (string) ($state['lawyer_status'] ?? 'forwarded_pending');
        $branch = (string) ($state['branch'] ?? 'LT');
        $lawyerEmail = trim((string) ($state['lawyer_email'] ?? self::LAWYER_EMAIL));

        if ($vehicle === '' || $reference === '' || $driver === '' || blank($ticketDate) || blank($receivedDate)) {
            Notification::make()->danger()->title('Fill required fields')->send();

            return;
        }

        if (! is_array($attachments) || $attachments === []) {
            Notification::make()->danger()->title('Attach at least one file')->send();

            return;
        }

        if ($authorityName === '' && $authorityId) {
            $authorityName = trim((string) Authority::query()->whereKey($authorityId)->value('name'));
        }
        if (! $authorityId) {
            $teismas = Authority::query()->where('email_pattern', '@teismas.lt')->first();
            if ($teismas) {
                $authorityId = $teismas->id;
                $authorityName = $authorityName !== '' ? $authorityName : (string) $teismas->name;
            }
        }

        $token = (string) Str::ulid();
        $localDir = 'fine-attachments/court-'.$token;
        Storage::disk('local')->makeDirectory($localDir);

        $paths = [];
        $uploadTemps = [];
        foreach ($attachments as $stored) {
            $relative = is_string($stored) ? $stored : null;
            if ($relative === null || $relative === '' || ! Storage::disk('local')->exists($relative)) {
                continue;
            }
            $basename = basename(str_replace('\\', '/', $relative));
            $target = $localDir.'/'.$basename;
            Storage::disk('local')->copy($relative, $target);
            $paths[] = $target;
            $uploadTemps[] = $relative;
        }

        if ($paths === []) {
            Notification::make()->danger()->title('Could not read uploaded files')->send();

            return;
        }

        $archiver = app(IncomingFineDocumentArchiver::class);
        $sharePoint = app(MicrosoftGraphSharePoint::class);
        $urls = [];
        $uploadErrors = [];

        foreach (array_values($paths) as $i => $relativePath) {
            try {
                $contents = Storage::disk('local')->get($relativePath);
                $ext = pathinfo($relativePath, PATHINFO_EXTENSION) ?: 'pdf';
                $nameRef = count($paths) > 1 ? $reference.' ('.($i + 1).')' : $reference;
                $fileName = $archiver->documentFileName($driver, $nameRef, $ext);
                $upload = $sharePoint->upload($fileName, $contents);
                if (($upload['webUrl'] ?? '') !== '') {
                    $urls[] = $upload['webUrl'];
                }
            } catch (Throwable $e) {
                $uploadErrors[] = basename($relativePath).': '.$e->getMessage();
            }
        }

        if ($urls === []) {
            Notification::make()
                ->danger()
                ->title('SharePoint upload failed')
                ->body(implode("\n", $uploadErrors) ?: 'No files uploaded.')
                ->persistent()
                ->send();

            return;
        }

        foreach ($uploadTemps as $temp) {
            try {
                Storage::disk('local')->delete($temp);
            } catch (Throwable) {
                // ignore
            }
        }

        $lawyerOk = in_array($lawyerStatus, ['pay', 'dispute'], true);
        $log = FineProcessLog::query()->create([
            'graph_message_id' => 'court-'.$token,
            'mailbox' => 'court-manual',
            'from_email' => null,
            'subject' => 'Court decision '.$reference,
            'received_at' => $receivedDate,
            'authority_id' => $authorityId,
            'authority_name' => $authorityName !== '' ? $authorityName : 'Teismas',
            'vehicle' => $vehicle,
            'reference' => $reference,
            'ticket_date' => $ticketDate,
            'driver_name' => $driver,
            'status' => 'lookup_found',
            'attachments_uploaded' => count($urls),
            'sharepoint_urls' => $urls,
            'local_attachment_dir' => $localDir,
            'wheels_export_status' => FineProcessLog::WHEELS_EXPORT_PENDING,
            'workflow' => [
                'source' => 'court_manual',
                'court' => [
                    'lawyer_email' => $lawyerEmail,
                    'lawyer_status' => $lawyerStatus,
                    'branch' => $branch,
                    'charge_fine_and_admin' => (bool) ($state['charge_fine_and_admin'] ?? true),
                    'payment_copy_to_teismas' => (bool) ($state['payment_copy_to_teismas'] ?? false),
                    'notes' => trim((string) ($state['checklist_notes'] ?? '')),
                ],
                'steps' => [
                    ['key' => 'court_entry', 'ok' => true, 'detail' => 'Manual court form'],
                    ['key' => 'sharepoint', 'ok' => true, 'detail' => count($urls).' file(s) on SharePoint'],
                    [
                        'key' => 'lawyer',
                        'ok' => $lawyerOk,
                        'detail' => $lawyerStatus === 'forwarded_pending'
                            ? 'Forwarded to '.$lawyerEmail.' — awaiting reply'
                            : ($lawyerStatus === 'pay' ? 'Lawyer: pay' : 'Lawyer: dispute'),
                    ],
                    [
                        'key' => 'branch',
                        'ok' => true,
                        'detail' => 'Branch '.$branch.((bool) ($state['charge_fine_and_admin'] ?? true)
                            ? ' · charge fine+admin'
                            : ''),
                    ],
                    ['key' => 'wheels_create', 'ok' => false, 'detail' => 'Queued for Wheelsys import'],
                ],
            ],
            'processed_by' => $userId,
            'processed_at' => now(),
            'error' => $uploadErrors !== [] ? implode('; ', $uploadErrors) : null,
        ]);

        ActivityLogger::log(
            'Court decision logged: '.$reference.' / '.$vehicle,
            Auth::user(),
            null,
            ['fine_process_log_id' => $log->id, 'lawyer_status' => $lawyerStatus, 'branch' => $branch]
        );

        Notification::make()
            ->success()
            ->title('Teismo nutarimas išsaugotas')
            ->body($vehicle.' · '.$reference.' · padalinys '.$branch.' · Wheelsys import eilėje')
            ->send();

        $this->mount();
    }
}
