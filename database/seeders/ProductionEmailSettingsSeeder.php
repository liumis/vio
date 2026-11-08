<?php

namespace Database\Seeders;

use App\Models\EmailSetting;
use App\Support\DebtTrackerService;
use Illuminate\Database\Seeder;

/**
 * System settings for prod (email / Graph / SharePoint / Wheelsys / MarkSign / debt tracker).
 * Secrets are taken from .env (never committed). Non-secret defaults are upserted.
 */
class ProductionEmailSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = EmailSetting::current();

        $payload = [
            'from_name' => env('MAIL_FROM_NAME', 'Sit&Go cars rent'),
            'from_email' => env('MAIL_FROM_ADDRESS', 'administration@greenlease.com'),
            'reply_to' => env('MAIL_REPLY_TO_ADDRESS', 'administration@greenlease.com'),
            'subject' => env('VIO_EMAIL_SUBJECT', 'Violation notification'),
            'inbox_mailbox' => env('VIO_INBOX_MAILBOX', 'administration@greenlease.lt'),
            'o365_enabled' => filter_var(env('O365_MAIL_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            'o365_tenant_id' => env('O365_TENANT_ID') ?: $settings->o365_tenant_id,
            'o365_client_id' => env('O365_CLIENT_ID') ?: $settings->o365_client_id,
            'smtp_host' => env('MAIL_HOST', 'smtp.office365.com'),
            'smtp_port' => (int) env('MAIL_PORT', 587),
            'smtp_encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'smtp_username' => env('MAIL_USERNAME') ?: $settings->smtp_username,
            'sharepoint_site_url' => env('SHAREPOINT_SITE_URL', config('services.sharepoint.site_url')),
            'sharepoint_folder_path' => env(
                'SHAREPOINT_FOLDER_PATH',
                config('services.sharepoint.folder_path')
            ),
            'wheelsys_username' => env('WHEELSYS_USERNAME') ?: ($settings->wheelsys_username ?: 'IT'),
            'wheelsys_manual_username' => env('WHEELSYS_MANUAL_USERNAME')
                ?: ($settings->wheelsys_manual_username ?: 'Liudas'),
            'marksign_signer_email' => env('MARKSIGN_SIGNER_EMAIL', 'mykolas@sitandgo.lt'),
            'marksign_signer_name' => env('MARKSIGN_SIGNER_NAME', 'Mykolas Sabaitis'),
            'debt_tracker_google_url' => env(
                'DEBT_TRACKER_GOOGLE_URL',
                DebtTrackerService::DEFAULT_GOOGLE_URL
            ),
            'debt_tracker_url' => env('DEBT_TRACKER_SHAREPOINT_URL') ?: $settings->debt_tracker_url,
            'debt_tracker_sheet' => env('DEBT_TRACKER_SHEET', DebtTrackerService::DEFAULT_SHEET),
            'debt_tracker_file_name' => env('DEBT_TRACKER_FILE_NAME', DebtTrackerService::DEFAULT_FILE_NAME),
        ];

        // Secrets: write into DB only when present in env (Settings UI + runtime).
        if (filled(env('O365_CLIENT_SECRET'))) {
            $payload['o365_client_secret'] = env('O365_CLIENT_SECRET');
        }
        if (filled(env('MAIL_PASSWORD'))) {
            $payload['smtp_password'] = env('MAIL_PASSWORD');
        }
        if (filled(env('WHEELSYS_PASSWORD'))) {
            $payload['wheelsys_password'] = env('WHEELSYS_PASSWORD');
        }
        if (filled(env('WHEELSYS_MANUAL_PASSWORD'))) {
            $payload['wheelsys_manual_password'] = env('WHEELSYS_MANUAL_PASSWORD');
        }
        if (filled(env('MARKSIGN_TOKEN'))) {
            $payload['marksign_token'] = env('MARKSIGN_TOKEN');
        }

        $settings->fill($payload)->save();

        $this->command?->info('Email / system settings upserted (secrets from .env when set).');
    }
}
