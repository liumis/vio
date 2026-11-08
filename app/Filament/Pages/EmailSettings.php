<?php

namespace App\Filament\Pages;

use App\Models\EmailSetting;
use App\Support\DebtTrackerService;
use App\Support\LatviaAuthorityPackageService;
use App\Support\MicrosoftGraphSharePoint;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Throwable;

class EmailSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'System settings';

    protected static ?string $title = 'System settings';

    protected static ?string $slug = 'system-settings';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.email-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = EmailSetting::current();
        $sp = EmailSetting::sharePointConfig();

        $this->form->fill([
            'from_name' => $settings->from_name ?: config('mail.from.name'),
            'from_email' => $settings->from_email ?: config('mail.from.address'),
            'reply_to' => $settings->reply_to ?: env('MAIL_REPLY_TO_ADDRESS'),
            'subject' => $settings->subject,
            'inbox_mailbox' => $settings->inbox_mailbox ?: 'administration@greenlease.lt',
            'o365_enabled' => (bool) ($settings->o365_enabled ?: config('services.microsoft_o365.enabled', false)),
            'o365_tenant_id' => $settings->o365_tenant_id ?: config('services.microsoft_o365.tenant_id'),
            'o365_client_id' => $settings->o365_client_id ?: config('services.microsoft_o365.client_id'),
            'o365_client_secret' => '',
            'has_client_secret' => filled($settings->o365_client_secret) || filled(config('services.microsoft_o365.client_secret')),
            'smtp_host' => $settings->smtp_host ?: config('mail.mailers.smtp.host'),
            'smtp_port' => $settings->smtp_port ?: config('mail.mailers.smtp.port'),
            'smtp_encryption' => $settings->smtp_encryption ?: env('MAIL_ENCRYPTION', 'tls'),
            'smtp_username' => $settings->smtp_username ?: config('mail.mailers.smtp.username'),
            'smtp_password' => '',
            'has_smtp_password' => filled($settings->smtp_password) || filled(config('mail.mailers.smtp.password')),
            'sharepoint_site_url' => $settings->sharepoint_site_url ?: $sp['site_url'],
            'sharepoint_folder_path' => $settings->sharepoint_folder_path ?: $sp['folder_path'],
            'wheelsys_username' => EmailSetting::wheelsysConfig()['username'],
            'wheelsys_password' => '',
            'has_wheelsys_password' => filled($settings->wheelsys_password)
                || filled(config('services.wheelsys.password')),
            'wheelsys_manual_username' => EmailSetting::wheelsysManualConfig()['username']
                ?: ($settings->wheelsys_manual_username ?: ''),
            'wheelsys_manual_password' => '',
            'has_wheelsys_manual_password' => filled($settings->wheelsys_manual_password),
            'marksign_token' => '',
            'has_marksign_token' => filled($settings->marksign_token) || filled(config('services.marksign.token')),
            'marksign_signer_email' => $settings->marksign_signer_email
                ?: config('services.marksign.signer_email', 'mykolas@sitandgo.lt'),
            'marksign_signer_name' => $settings->marksign_signer_name
                ?: config('services.marksign.signer_name', 'Mykolas Sabaitis'),
            'debt_tracker_google_url' => $settings->debt_tracker_google_url
                ?: DebtTrackerService::DEFAULT_GOOGLE_URL,
            'debt_tracker_url' => $settings->debt_tracker_url ?: '',
            'debt_tracker_sheet' => $settings->debt_tracker_sheet ?: DebtTrackerService::DEFAULT_SHEET,
            'debt_tracker_file_name' => $settings->debt_tracker_file_name ?: DebtTrackerService::DEFAULT_FILE_NAME,
            'power_of_attorney_valid_until' => $settings->power_of_attorney_valid_until
                ? $settings->power_of_attorney_valid_until->format('Y-m-d')
                : null,
            'power_of_attorney_status' => filled($settings->power_of_attorney_signed_path)
                ? 'Pasirašytas'
                : (filled($settings->power_of_attorney_marksign_uuid) ? 'Laukia MarkSign' : 'Nėra'),
            'power_of_attorney_signing_url' => $settings->power_of_attorney_signing_url ?: '',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Outbound email')
                    ->schema([
                        Forms\Components\TextInput::make('from_name')
                            ->label('From name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('from_email')
                            ->label('From email (Graph send as)')
                            ->email()
                            ->maxLength(255)
                            ->helperText('Mailbox used for Graph sendMail, e.g. a licensed user that can send.'),
                        Forms\Components\TextInput::make('reply_to')
                            ->label('Reply-To')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('subject')
                            ->label('Default subject')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Inbox (violations mailbox)')
                    ->description('Shared mailbox to read incoming fines from (Microsoft Graph).')
                    ->schema([
                        Forms\Components\TextInput::make('inbox_mailbox')
                            ->label('Inbox mailbox')
                            ->email()
                            ->maxLength(255)
                            ->helperText('e.g. administration@greenlease.lt'),
                    ]),

                Forms\Components\Section::make('Microsoft 365 / Graph app')
                    ->description('Stored in DB (secret encrypted). Leave secret blank to keep the current value.')
                    ->schema([
                        Forms\Components\Toggle::make('o365_enabled')
                            ->label('Use Microsoft Graph')
                            ->inline(false),
                        Forms\Components\TextInput::make('o365_tenant_id')
                            ->label('Tenant ID')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('o365_client_id')
                            ->label('Client ID')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('o365_client_secret')
                            ->label('Client secret')
                            ->password()
                            ->revealable()
                            ->maxLength(2000)
                            ->helperText(fn (Forms\Get $get): string => $get('has_client_secret')
                                ? 'A secret is already saved. Enter a new one only to replace it.'
                                : 'No secret saved yet.'),
                        Forms\Components\Hidden::make('has_client_secret'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('SharePoint (fine documents)')
                    ->description('Target folder for uploaded fine PDFs. Uses the same Graph app credentials.')
                    ->schema([
                        Forms\Components\TextInput::make('sharepoint_site_url')
                            ->label('Site URL')
                            ->maxLength(500)
                            ->helperText('e.g. https://greenlease.sharepoint.com/sites/Saugykla'),
                        Forms\Components\TextInput::make('sharepoint_folder_path')
                            ->label('Folder path (in default Documents library)')
                            ->maxLength(500)
                            ->helperText('e.g. Lithuania/Administration/Policijos administravimas'),
                    ]),

                Forms\Components\Section::make('Wheelsys')
                    ->description('Both accounts store username + password (encrypted). VIO automation is used for lookup / Update status / SSO. Manual is your personal browser login (single-access policy).')
                    ->schema([
                        Forms\Components\TextInput::make('wheelsys_username')
                            ->label('VIO automation username')
                            ->maxLength(255)
                            ->helperText('Account VIO uses for API/SSO. Overrides .env when set.'),
                        Forms\Components\TextInput::make('wheelsys_password')
                            ->label('VIO automation password')
                            ->password()
                            ->revealable()
                            ->maxLength(2000)
                            ->helperText(fn (Forms\Get $get): string => $get('has_wheelsys_password')
                                ? 'A password is already saved. Enter a new one only to replace it.'
                                : 'No password saved yet — falls back to WHEELSYS_PASSWORD in .env.'),
                        Forms\Components\Hidden::make('has_wheelsys_password'),
                        Forms\Components\TextInput::make('wheelsys_manual_username')
                            ->label('Manual username')
                            ->maxLength(255)
                            ->helperText('Your personal Wheelsys user for browser work.'),
                        Forms\Components\TextInput::make('wheelsys_manual_password')
                            ->label('Manual password')
                            ->password()
                            ->revealable()
                            ->maxLength(2000)
                            ->helperText(fn (Forms\Get $get): string => $get('has_wheelsys_manual_password')
                                ? 'A password is already saved. Enter a new one only to replace it.'
                                : 'No password saved yet.'),
                        Forms\Components\Hidden::make('has_wheelsys_manual_password'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('CHARGEBACKS / DEBTS tracker')
                    ->description('Kliento atsekamumo lentelė (instrukcija: po laiško klientui → LT debt). Google = originalas; SharePoint = VIO darbinė kopija.')
                    ->schema([
                        Forms\Components\TextInput::make('debt_tracker_google_url')
                            ->label('Google Sheets (originalas)')
                            ->url()
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('debt_tracker_url')
                            ->label('SharePoint nuoroda (darbinė)')
                            ->url()
                            ->maxLength(1000)
                            ->helperText('Administruojama nuoroda. Po „Upload copy to SharePoint“ čia įrašoma nauja nuoroda.')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('debt_tracker_file_name')
                            ->label('SharePoint failo vardas')
                            ->maxLength(255)
                            ->default('CHARGEBACKS-DEBTS.xlsx'),
                        Forms\Components\TextInput::make('debt_tracker_sheet')
                            ->label('Lapas (sheet)')
                            ->maxLength(64)
                            ->default('LT debt')
                            ->helperText('Instrukcija: CHARGEBACKS/DEBTS → LT debt'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('MarkSign')
                    ->description('Chargeback notices + Latvijos CPP raštai / įgaliojimai (same token as pakaitinis).')
                    ->schema([
                        Forms\Components\TextInput::make('marksign_token')
                            ->label('MarkSign token')
                            ->password()
                            ->revealable()
                            ->maxLength(2000)
                            ->helperText(fn (Forms\Get $get): string => $get('has_marksign_token')
                                ? 'A token is already saved / in .env. Enter a new one only to replace it.'
                                : 'No token saved yet — falls back to MARKSIGN_TOKEN in .env.'),
                        Forms\Components\Hidden::make('has_marksign_token'),
                        Forms\Components\TextInput::make('marksign_signer_email')
                            ->label('Signer email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->helperText('Director who signs notices, e.g. mykolas@sitandgo.lt'),
                        Forms\Components\TextInput::make('marksign_signer_name')
                            ->label('Signer name')
                            ->maxLength(255)
                            ->helperText('Full name shown on the notice and in MarkSign.'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Latvija · įgaliojimas (POA)')
                    ->description('Instrukcija: įgaliojimą rengti ~1 metams. Jis prisegamas prie kiekvieno LV paketo į kanceleja.cpp@vp.gov.lv.')
                    ->schema([
                        Forms\Components\TextInput::make('power_of_attorney_status')
                            ->label('Statusas')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('power_of_attorney_valid_until')
                            ->label('Galioja iki')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('power_of_attorney_signing_url')
                            ->label('MarkSign signing URL')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('SMTP (fallback)')
                    ->description('Used when Microsoft Graph is disabled. Password is encrypted. Leave blank to keep current.')
                    ->schema([
                        Forms\Components\TextInput::make('smtp_host')
                            ->label('SMTP host')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('smtp_port')
                            ->label('SMTP port')
                            ->numeric(),
                        Forms\Components\Select::make('smtp_encryption')
                            ->label('Encryption')
                            ->options([
                                'tls' => 'TLS',
                                'ssl' => 'SSL',
                                '' => 'None',
                            ]),
                        Forms\Components\TextInput::make('smtp_username')
                            ->label('SMTP username')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('smtp_password')
                            ->label('SMTP password')
                            ->password()
                            ->revealable()
                            ->maxLength(2000)
                            ->helperText(fn (Forms\Get $get): string => $get('has_smtp_password')
                                ? 'A password is already saved. Enter a new one only to replace it.'
                                : 'No password saved yet.'),
                        Forms\Components\Hidden::make('has_smtp_password'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $settings = EmailSetting::current();

        $payload = [
            'from_name' => $state['from_name'] ?? null,
            'from_email' => $state['from_email'] ?? null,
            'reply_to' => $state['reply_to'] ?? null,
            'subject' => $state['subject'] ?? null,
            'inbox_mailbox' => $state['inbox_mailbox'] ?? null,
            'o365_enabled' => (bool) ($state['o365_enabled'] ?? false),
            'o365_tenant_id' => $state['o365_tenant_id'] ?? null,
            'o365_client_id' => $state['o365_client_id'] ?? null,
            'smtp_host' => $state['smtp_host'] ?? null,
            'smtp_port' => isset($state['smtp_port']) && $state['smtp_port'] !== '' ? (int) $state['smtp_port'] : null,
            'smtp_encryption' => $state['smtp_encryption'] ?? null,
            'smtp_username' => $state['smtp_username'] ?? null,
            'sharepoint_site_url' => $state['sharepoint_site_url'] ?? null,
            'sharepoint_folder_path' => isset($state['sharepoint_folder_path'])
                ? trim(str_replace('\\', '/', (string) $state['sharepoint_folder_path']), '/')
                : null,
            'wheelsys_username' => trim((string) ($state['wheelsys_username'] ?? '')) ?: null,
            'wheelsys_manual_username' => trim((string) ($state['wheelsys_manual_username'] ?? '')) ?: null,
            'marksign_signer_email' => trim((string) ($state['marksign_signer_email'] ?? '')) ?: null,
            'marksign_signer_name' => trim((string) ($state['marksign_signer_name'] ?? '')) ?: null,
            'debt_tracker_google_url' => trim((string) ($state['debt_tracker_google_url'] ?? '')) ?: null,
            'debt_tracker_url' => trim((string) ($state['debt_tracker_url'] ?? '')) ?: null,
            'debt_tracker_sheet' => trim((string) ($state['debt_tracker_sheet'] ?? '')) ?: null,
            'debt_tracker_file_name' => trim((string) ($state['debt_tracker_file_name'] ?? '')) ?: null,
        ];

        $newSecret = trim((string) ($state['o365_client_secret'] ?? ''));
        if ($newSecret !== '') {
            $payload['o365_client_secret'] = $newSecret;
        } elseif (! filled($settings->o365_client_secret) && filled(config('services.microsoft_o365.client_secret'))) {
            $payload['o365_client_secret'] = (string) config('services.microsoft_o365.client_secret');
        }

        $newSmtpPassword = trim((string) ($state['smtp_password'] ?? ''));
        if ($newSmtpPassword !== '') {
            $payload['smtp_password'] = $newSmtpPassword;
        } elseif (! filled($settings->smtp_password) && filled(config('mail.mailers.smtp.password'))) {
            $payload['smtp_password'] = (string) config('mail.mailers.smtp.password');
        }

        $newWheelsysPassword = trim((string) ($state['wheelsys_password'] ?? ''));
        if ($newWheelsysPassword !== '') {
            $payload['wheelsys_password'] = $newWheelsysPassword;
        }

        $newWheelsysManualPassword = trim((string) ($state['wheelsys_manual_password'] ?? ''));
        if ($newWheelsysManualPassword !== '') {
            $payload['wheelsys_manual_password'] = $newWheelsysManualPassword;
        }

        $newMarksignToken = trim((string) ($state['marksign_token'] ?? ''));
        if ($newMarksignToken !== '') {
            $payload['marksign_token'] = $newMarksignToken;
        } elseif (! filled($settings->marksign_token) && filled(config('services.marksign.token'))) {
            $payload['marksign_token'] = (string) config('services.marksign.token');
        }

        $settings->fill($payload)->save();
        EmailSetting::applyRuntimeMailConfig();

        $fresh = $settings->fresh();
        $this->form->fill([
            ...$state,
            'o365_client_secret' => '',
            'smtp_password' => '',
            'wheelsys_password' => '',
            'wheelsys_manual_password' => '',
            'marksign_token' => '',
            'has_client_secret' => filled($fresh->o365_client_secret),
            'has_smtp_password' => filled($fresh->smtp_password),
            'has_wheelsys_password' => filled($fresh->wheelsys_password)
                || filled(config('services.wheelsys.password')),
            'has_wheelsys_manual_password' => filled($fresh->wheelsys_manual_password),
            'has_marksign_token' => filled($fresh->marksign_token) || filled(config('services.marksign.token')),
        ]);

        Notification::make()
            ->success()
            ->title('Settings saved')
            ->body('Email, SharePoint, Wheelsys and MarkSign settings are stored in the database.')
            ->send();
    }

    public function testSharePoint(): void
    {
        // Persist current form values first so the test uses what is on screen.
        $this->save();

        try {
            $sp = app(MicrosoftGraphSharePoint::class);
            $folder = $sp->ensureFolderExists();
            $fileName = 'vio-sharepoint-test-'.now()->format('Ymd-His').'.txt';
            $uploaded = $sp->upload(
                $fileName,
                "VIO SharePoint connectivity test\nUploaded at ".now()->toIso8601String()."\n",
            );

            Notification::make()
                ->success()
                ->title('SharePoint OK')
                ->body('Folder: '.($folder['name'] ?: 'ok').'. Uploaded: '.$uploaded['name'].($uploaded['webUrl'] !== '' ? ' — '.$uploaded['webUrl'] : ''))
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->danger()
                ->title('SharePoint test failed')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function uploadDebtTrackerCopy(): void
    {
        $this->save();

        try {
            $result = app(DebtTrackerService::class)->uploadWorkingCopy();
            $fresh = EmailSetting::current()->fresh();
            $this->form->fill([
                ...$this->form->getState(),
                'debt_tracker_url' => $fresh->debt_tracker_url,
                'debt_tracker_google_url' => $fresh->debt_tracker_google_url,
                'debt_tracker_sheet' => $fresh->debt_tracker_sheet,
                'debt_tracker_file_name' => $fresh->debt_tracker_file_name,
                'marksign_token' => '',
                'o365_client_secret' => '',
                'smtp_password' => '',
                'wheelsys_password' => '',
                'wheelsys_manual_password' => '',
            ]);

            Notification::make()
                ->success()
                ->title('CHARGEBACKS/DEBTS įkeltas į SharePoint')
                ->body(($result['name'] ?? '').($result['webUrl'] !== '' ? ' — '.$result['webUrl'] : ''))
                ->persistent()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Debt tracker upload failed')
                ->body($e->getMessage())
                ->persistent()
                ->send();
        }
    }

    public function renewPowerOfAttorney(): void
    {
        $this->save();

        try {
            $result = app(LatviaAuthorityPackageService::class)->renewPowerOfAttorneyForMarksign();
            $fresh = EmailSetting::current()->fresh();
            $this->form->fill([
                ...$this->form->getState(),
                'power_of_attorney_status' => 'Laukia MarkSign',
                'power_of_attorney_valid_until' => $fresh->power_of_attorney_valid_until
                    ? $fresh->power_of_attorney_valid_until->format('Y-m-d')
                    : $result['valid_until'],
                'power_of_attorney_signing_url' => $result['signing_url'],
                'marksign_token' => '',
                'o365_client_secret' => '',
                'smtp_password' => '',
                'wheelsys_password' => '',
                'wheelsys_manual_password' => '',
            ]);

            Notification::make()
                ->success()
                ->title('Įgaliojimas MarkSign’e')
                ->body('UUID: '.$result['uuid'].' · galioja iki '.$result['valid_until'])
                ->persistent()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->danger()
                ->title('POA MarkSign failed')
                ->body($e->getMessage())
                ->persistent()
                ->send();
        }
    }
}
