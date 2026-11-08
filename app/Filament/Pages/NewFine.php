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
 * Manual entry for physically received fine letters (post / scans).
 */
class NewFine extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-plus';

    protected static ?string $navigationLabel = 'New fine';

    protected static ?string $navigationGroup = 'Fines';

    protected static ?string $title = 'New fine';

    protected static ?string $slug = 'new-fine';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.new-fine';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'received_at' => now()->timezone(config('app.timezone'))->format('Y-m-d'),
            'ticket_time' => null,
            'authority_id' => null,
            'authority_name' => null,
            'attachments' => [],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Fine details')
                    ->description('Physical letter / scan. Same fields later go into the Wheelsys import file.')
                    ->schema([
                        Forms\Components\TextInput::make('vehicle')
                            ->label('Vehicle (plate)')
                            ->required()
                            ->maxLength(32)
                            ->dehydrateStateUsing(fn (?string $state): string => strtoupper(trim((string) $state))),
                        Forms\Components\TextInput::make('reference')
                            ->label('Reference no')
                            ->required()
                            ->maxLength(120)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Forms\Set $set, Forms\Get $get): void {
                                $ref = strtoupper(trim((string) $state));
                                if ($ref === '' || ! str_starts_with($ref, 'CAN')) {
                                    return;
                                }
                                if (filled($get('authority_id'))) {
                                    return;
                                }
                                $poland = Authority::query()
                                    ->where('email_pattern', '@gitd.gov.pl')
                                    ->first();
                                if ($poland) {
                                    $set('authority_id', $poland->id);
                                    $set('authority_name', $poland->name);
                                }
                            })
                            ->helperText('e.g. CAN… (Lenkija) / GA… / ROIK / A51-…'),
                        Forms\Components\DatePicker::make('ticket_date')
                            ->label('Ticket date')
                            ->required()
                            ->native(false)
                            ->displayFormat('Y-m-d'),
                        Forms\Components\TextInput::make('ticket_time')
                            ->label('Ticket time')
                            ->placeholder('HH:MM')
                            ->maxLength(8)
                            ->helperText('Optional. Wheels uses plate + date/time to resolve rental / customer / agent.'),
                        Forms\Components\DatePicker::make('received_at')
                            ->label('Fine received date')
                            ->required()
                            ->native(false)
                            ->displayFormat('Y-m-d')
                            ->helperText('Letter received date (goes to Wheels Fine received date).'),
                        Forms\Components\TextInput::make('driver_name')
                            ->label('Driver / customer name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Used for SharePoint file naming: "{Name} {Reference}.pdf".'),
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
                            ->maxLength(255)
                            ->helperText('Optional free text if not in the list (e.g. Latvian CPP).'),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Attachments')
                    ->description('Scanned or photographed letter pages. Multiple files allowed — stored on SharePoint.')
                    ->schema([
                        Forms\Components\FileUpload::make('attachments')
                            ->label('Files')
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
                                'image/heic',
                                'image/heif',
                            ])
                            ->maxFiles(15)
                            ->maxSize(20480)
                            ->helperText('PDF or images, up to 15 files.'),
                    ]),
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
        $ticketTime = trim((string) ($state['ticket_time'] ?? ''));
        $receivedDate = $state['received_at'] ?? null;
        $authorityId = $state['authority_id'] ?? null;
        $authorityName = trim((string) ($state['authority_name'] ?? ''));
        $notes = trim((string) ($state['notes'] ?? ''));
        $attachments = $state['attachments'] ?? [];

        if ($vehicle === '' || $reference === '' || $driver === '' || blank($ticketDate) || blank($receivedDate)) {
            Notification::make()->danger()->title('Fill required fields')->send();

            return;
        }

        if (! is_array($attachments) || $attachments === []) {
            Notification::make()->danger()->title('Attach at least one file')->send();

            return;
        }

        if ($ticketTime !== '' && ! preg_match('/^\d{1,2}:\d{2}/', $ticketTime)) {
            Notification::make()->warning()->title('Ticket time should look like HH:MM')->send();

            return;
        }

        if ($authorityName === '' && $authorityId) {
            $authorityName = trim((string) Authority::query()->whereKey($authorityId)->value('name'));
        }

        // Physical Poland CAN letters → GITD authority (instruction VII.2).
        if ((! $authorityId || $authorityName === '') && str_starts_with(strtoupper($reference), 'CAN')) {
            $poland = Authority::query()->where('email_pattern', '@gitd.gov.pl')->first();
            if ($poland) {
                $authorityId = $poland->id;
                $authorityName = (string) $poland->name;
            }
        }

        $isPolandCan = str_starts_with(strtoupper($reference), 'CAN')
            || str_contains(strtolower($authorityName), 'lenkij')
            || str_contains(strtolower($authorityName), 'gitd');

        $token = (string) Str::ulid();
        $localDir = 'fine-attachments/physical-'.$token;
        Storage::disk('local')->makeDirectory($localDir);

        $paths = [];
        $uploadTemps = [];
        foreach ($attachments as $stored) {
            $relative = is_string($stored) ? $stored : null;
            if ($relative === null || $relative === '') {
                continue;
            }
            if (! Storage::disk('local')->exists($relative)) {
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
                // ignore cleanup failures
            }
        }

        $log = FineProcessLog::query()->create([
            'graph_message_id' => 'physical-'.$token,
            'mailbox' => 'physical-mail',
            'from_email' => null,
            'subject' => $notes !== '' ? $notes : ('Physical mail '.$reference),
            'received_at' => $receivedDate,
            'authority_id' => $authorityId,
            'authority_name' => $authorityName !== '' ? $authorityName : null,
            'vehicle' => $vehicle,
            'reference' => $reference,
            'ticket_date' => $ticketDate,
            'ticket_time' => $ticketTime !== '' ? $ticketTime : null,
            'driver_name' => $driver,
            'status' => 'lookup_found',
            'attachments_uploaded' => count($urls),
            'sharepoint_urls' => $urls,
            'local_attachment_dir' => $localDir,
            'wheels_export_status' => FineProcessLog::WHEELS_EXPORT_PENDING,
            'workflow' => [
                'source' => 'physical_mail',
                'steps' => array_values(array_filter([
                    ['key' => 'physical_entry', 'ok' => true, 'detail' => 'Entered from New fine'],
                    ['key' => 'sharepoint', 'ok' => true, 'detail' => count($urls).' file(s) on SharePoint'],
                    ['key' => 'wheels_create', 'ok' => false, 'detail' => 'Queued for Wheelsys import'],
                    $isPolandCan ? [
                        'key' => 'poland_reply',
                        'ok' => false,
                        'detail' => 'Atsakymas el. paštu → canard@gitd.gov.pl (Subject: CAN… + valst. nr.)',
                    ] : null,
                ])),
            ],
            'processed_by' => $userId,
            'processed_at' => now(),
            'error' => $uploadErrors !== [] ? implode('; ', $uploadErrors) : null,
        ]);

        ActivityLogger::log(
            'Physical fine created: '.$reference.' / '.$vehicle,
            Auth::user(),
            null,
            [
                'fine_process_log_id' => $log->id,
                'sharepoint_count' => count($urls),
            ]
        );

        Notification::make()
            ->success()
            ->title('Fine saved')
            ->body($vehicle.' · '.$reference.' · '.count($urls).' file(s) on SharePoint · queued for Wheelsys import')
            ->send();

        $this->form->fill([
            'received_at' => now()->timezone(config('app.timezone'))->format('Y-m-d'),
            'ticket_date' => null,
            'ticket_time' => null,
            'vehicle' => null,
            'reference' => null,
            'driver_name' => null,
            'authority_id' => null,
            'authority_name' => null,
            'notes' => null,
            'attachments' => [],
        ]);
    }
}
