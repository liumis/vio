<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class EmailSetting extends Model
{
    protected $fillable = [
        'from_name',
        'from_email',
        'reply_to',
        'subject',
        'o365_enabled',
        'o365_tenant_id',
        'o365_client_id',
        'o365_client_secret',
        'inbox_mailbox',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'smtp_username',
        'smtp_password',
        'sharepoint_site_url',
        'sharepoint_folder_path',
        'wheelsys_username',
        'wheelsys_password',
        'wheelsys_manual_username',
        'wheelsys_manual_password',
        'marksign_token',
        'marksign_signer_email',
        'marksign_signer_name',
        'debt_tracker_url',
        'debt_tracker_google_url',
        'debt_tracker_sheet',
        'debt_tracker_file_name',
        'power_of_attorney_pdf_path',
        'power_of_attorney_signed_path',
        'power_of_attorney_valid_until',
        'power_of_attorney_marksign_uuid',
        'power_of_attorney_signing_url',
    ];

    protected function casts(): array
    {
        return [
            'o365_enabled' => 'boolean',
            'o365_client_secret' => 'encrypted',
            'smtp_password' => 'encrypted',
            'wheelsys_password' => 'encrypted',
            'wheelsys_manual_password' => 'encrypted',
            'marksign_token' => 'encrypted',
            'smtp_port' => 'integer',
            'power_of_attorney_valid_until' => 'date',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    /**
     * @return array{
     *     enabled: bool,
     *     tenant_id: string,
     *     client_id: string,
     *     client_secret: string,
     *     from_email: string,
     *     from_name: string,
     *     reply_to: string,
     *     subject: string,
     *     inbox_mailbox: string
     * }
     */
    public static function graphConfig(): array
    {
        $settings = static::query()->first();

        $tenantId = trim((string) ($settings?->o365_tenant_id ?: config('services.microsoft_o365.tenant_id')));
        $clientId = trim((string) ($settings?->o365_client_id ?: config('services.microsoft_o365.client_id')));
        $clientSecret = trim((string) ($settings?->o365_client_secret ?: config('services.microsoft_o365.client_secret')));
        $enabled = (bool) ($settings?->o365_enabled ?? config('services.microsoft_o365.enabled', false));

        if (! $enabled && $tenantId !== '' && $clientId !== '' && $clientSecret !== '') {
            $enabled = true;
        }

        return [
            'enabled' => $enabled,
            'tenant_id' => $tenantId,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'from_email' => trim((string) ($settings?->from_email ?: config('mail.from.address'))),
            'from_name' => trim((string) ($settings?->from_name ?: config('mail.from.name'))),
            'reply_to' => trim((string) ($settings?->reply_to ?: env('MAIL_REPLY_TO_ADDRESS', ''))),
            'subject' => trim((string) ($settings?->subject ?: 'Violation notification')),
            'inbox_mailbox' => trim((string) ($settings?->inbox_mailbox ?: '')),
        ];
    }

    /**
     * @return array{site_url:string,folder_path:string}
     */
    public static function sharePointConfig(): array
    {
        $settings = static::query()->first();

        return [
            'site_url' => trim((string) ($settings?->sharepoint_site_url
                ?: config('services.sharepoint.site_url')
                ?: 'https://greenlease.sharepoint.com/sites/Saugykla')),
            'folder_path' => trim((string) ($settings?->sharepoint_folder_path
                ?: config('services.sharepoint.folder_path')
                ?: 'Lithuania/Administration/Policijos administravimas'), '/'),
        ];
    }

    /**
     * DB credentials override .env when set (Settings → System settings → Wheelsys).
     *
     * @return array{username: string, password: string, base_url: string}
     */
    public static function wheelsysConfig(): array
    {
        $username = '';
        $password = '';

        try {
            $settings = static::query()->first();
            $username = trim((string) ($settings?->wheelsys_username ?: ''));
            $password = trim((string) ($settings?->wheelsys_password ?: ''));
        } catch (\Throwable) {
            // Corrupt encrypted payload / DB blip — fall back to env.
        }

        if ($username === '') {
            $username = trim((string) config('services.wheelsys.username', ''));
        }
        if ($password === '') {
            $password = trim((string) config('services.wheelsys.password', ''));
        }

        return [
            'username' => $username,
            'password' => $password,
            'base_url' => rtrim((string) config('services.wheelsys.base_url', 'https://lt.wheelsys.greenmotion.com'), '/'),
        ];
    }

    /**
     * Personal / browser Wheelsys login (not used by VIO automation API/SSO).
     *
     * @return array{username: string, password: string}
     */
    public static function wheelsysManualConfig(): array
    {
        $username = '';
        $password = '';

        try {
            $settings = static::query()->first();
            $username = trim((string) ($settings?->wheelsys_manual_username ?: ''));
            $password = trim((string) ($settings?->wheelsys_manual_password ?: ''));
        } catch (\Throwable) {
            // Corrupt encrypted payload / DB blip.
        }

        return [
            'username' => $username,
            'password' => $password,
        ];
    }

    /**
     * @return array{token:string,base_url:string}
     */
    public static function marksignConfig(): array
    {
        $token = '';
        try {
            $settings = static::query()->first();
            $token = trim((string) ($settings?->marksign_token ?: ''));
        } catch (\Throwable) {
            $token = '';
        }

        if ($token === '') {
            $token = trim((string) config('services.marksign.token', ''));
        }

        return [
            'token' => $token,
            'base_url' => rtrim((string) config('services.marksign.base_url', 'https://api.marksign.eu'), '/'),
        ];
    }

    /**
     * Director who signs chargeback notices in MarkSign.
     *
     * @return array{name:string,surname:string,email:string,full_name:string}
     */
    public static function marksignSigner(): array
    {
        $email = '';
        $fullName = '';
        try {
            $settings = static::query()->first();
            $email = trim((string) ($settings?->marksign_signer_email ?: ''));
            $fullName = trim((string) ($settings?->marksign_signer_name ?: ''));
        } catch (\Throwable) {
            // ignore
        }

        if ($email === '') {
            $email = trim((string) config('services.marksign.signer_email', 'mykolas@sitandgo.lt'));
        }
        if ($fullName === '') {
            $fullName = trim((string) config('services.marksign.signer_name', 'Mykolas Sabaitis'));
        }

        $parts = preg_split('/\s+/', $fullName, 2) ?: [];
        $name = trim((string) ($parts[0] ?? 'Mykolas'));
        $surname = trim((string) ($parts[1] ?? 'Sabaitis'));

        return [
            'name' => $name !== '' ? $name : 'Mykolas',
            'surname' => $surname !== '' ? $surname : 'Sabaitis',
            'email' => $email,
            'full_name' => trim($name.' '.$surname),
        ];
    }

    public static function applyRuntimeMailConfig(): void
    {
        try {
            $settings = static::query()->first();
        } catch (\Throwable) {
            return;
        }

        if (! $settings) {
            return;
        }

        if (filled($settings->from_email)) {
            Config::set('mail.from.address', $settings->from_email);
        }
        if (filled($settings->from_name)) {
            Config::set('mail.from.name', $settings->from_name);
        }
        if (filled($settings->smtp_host)) {
            Config::set('mail.mailers.smtp.host', $settings->smtp_host);
        }
        if (filled($settings->smtp_port)) {
            Config::set('mail.mailers.smtp.port', (int) $settings->smtp_port);
        }
        if (filled($settings->smtp_username)) {
            Config::set('mail.mailers.smtp.username', $settings->smtp_username);
        }
        if (filled($settings->smtp_password)) {
            Config::set('mail.mailers.smtp.password', $settings->smtp_password);
        }
        if (filled($settings->smtp_encryption)) {
            $encryption = strtolower(trim((string) $settings->smtp_encryption));
            Config::set('mail.mailers.smtp.scheme', $encryption === 'ssl' ? 'smtps' : null);
            Config::set('mail.mailers.smtp.encryption', $encryption);
        }
    }
}
