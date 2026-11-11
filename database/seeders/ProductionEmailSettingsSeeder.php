<?php

namespace Database\Seeders;

use App\Models\EmailSetting;
use App\Support\DebtTrackerService;
use Illuminate\Database\Seeder;

/**
 * System settings for prod (email / Graph / SharePoint / Wheelsys / MarkSign / debt tracker).
 * Secrets come from Cloud env via config/* (config:cache safe). Never commit secrets.
 */
class ProductionEmailSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = EmailSetting::current();

        $payload = [
            'from_name' => (string) config('mail.from.name', 'Sit&Go cars rent'),
            'from_email' => (string) config('mail.from.address', 'administration@greenlease.com'),
            'reply_to' => (string) config('vio.mail_reply_to', 'administration@greenlease.com'),
            'subject' => (string) config('vio.email_subject', 'Violation notification'),
            'inbox_mailbox' => (string) config('vio.inbox_mailbox', 'administration@greenlease.lt'),
            'o365_enabled' => (bool) config('services.microsoft_o365.enabled', true),
            'o365_tenant_id' => config('services.microsoft_o365.tenant_id') ?: $settings->o365_tenant_id,
            'o365_client_id' => config('services.microsoft_o365.client_id') ?: $settings->o365_client_id,
            'smtp_host' => (string) config('mail.mailers.smtp.host', 'smtp.office365.com'),
            'smtp_port' => (int) config('mail.mailers.smtp.port', 587),
            'smtp_encryption' => (string) config('vio.smtp_encryption', 'tls'),
            'smtp_username' => config('mail.mailers.smtp.username') ?: $settings->smtp_username,
            'sharepoint_site_url' => config('services.sharepoint.site_url') ?: $settings->sharepoint_site_url,
            'sharepoint_folder_path' => config('services.sharepoint.folder_path') ?: $settings->sharepoint_folder_path,
            'wheelsys_username' => config('services.wheelsys.username')
                ?: ($settings->wheelsys_username ?: 'IT'),
            'wheelsys_manual_username' => config('services.wheelsys.manual_username')
                ?: ($settings->wheelsys_manual_username ?: 'Liudas'),
            'marksign_signer_email' => config('services.marksign.signer_email', 'mykolas@sitandgo.lt'),
            'marksign_signer_name' => config('services.marksign.signer_name', 'Mykolas Sabaitis'),
            'debt_tracker_google_url' => config(
                'vio.debt_tracker_google_url',
                DebtTrackerService::DEFAULT_GOOGLE_URL
            ),
            'debt_tracker_url' => config('vio.debt_tracker_sharepoint_url') ?: $settings->debt_tracker_url,
            'debt_tracker_sheet' => config('vio.debt_tracker_sheet', DebtTrackerService::DEFAULT_SHEET),
            'debt_tracker_file_name' => config('vio.debt_tracker_file_name', DebtTrackerService::DEFAULT_FILE_NAME),
        ];

        // Secrets: write into DB only when present in config (Settings UI + runtime).
        $o365Secret = config('services.microsoft_o365.client_secret');
        if (filled($o365Secret)) {
            $payload['o365_client_secret'] = $o365Secret;
        }

        $mailPassword = config('mail.mailers.smtp.password');
        if (filled($mailPassword)) {
            $payload['smtp_password'] = $mailPassword;
        }

        $wheelsysPassword = config('services.wheelsys.password');
        if (filled($wheelsysPassword)) {
            $payload['wheelsys_password'] = $wheelsysPassword;
        }

        $wheelsysManualPassword = config('services.wheelsys.manual_password');
        if (filled($wheelsysManualPassword)) {
            $payload['wheelsys_manual_password'] = $wheelsysManualPassword;
        }

        $marksignToken = config('services.marksign.token');
        if (filled($marksignToken)) {
            $payload['marksign_token'] = $marksignToken;
        }

        $settings->fill($payload)->save();

        $this->command?->info('Email / system settings upserted (secrets from config/env when set).');
        $this->command?->table(
            ['Check', 'Value'],
            [
                ['O365 tenant', filled($payload['o365_tenant_id'] ?? null) ? 'set' : 'missing'],
                ['O365 client', filled($payload['o365_client_id'] ?? null) ? 'set' : 'missing'],
                ['O365 secret', filled($o365Secret) ? 'set' : 'missing'],
                ['Wheelsys password', filled($wheelsysPassword) ? 'set' : 'missing'],
                ['MarkSign token', filled($marksignToken) ? 'set' : 'missing'],
            ]
        );
    }
}
