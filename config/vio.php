<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Production bootstrap operators
    |--------------------------------------------------------------------------
    |
    | Used by ProductionUsersSeeder / vio:cloud-deploy. Must live in config so
    | values survive php artisan config:cache on Laravel Cloud.
    |
    */

    'bootstrap_password' => env('VIO_BOOTSTRAP_PASSWORD'),

    'bootstrap_reset_passwords' => (bool) env('VIO_BOOTSTRAP_RESET_PASSWORDS', false),

    /*
    |--------------------------------------------------------------------------
    | System settings defaults (ProductionEmailSettingsSeeder)
    |--------------------------------------------------------------------------
    */

    'inbox_mailbox' => env('VIO_INBOX_MAILBOX', 'administration@greenlease.lt'),

    'email_subject' => env('VIO_EMAIL_SUBJECT', 'Violation notification'),

    'mail_reply_to' => env('MAIL_REPLY_TO_ADDRESS', 'administration@greenlease.com'),

    'smtp_encryption' => env('MAIL_ENCRYPTION', 'tls'),

    'debt_tracker_google_url' => env(
        'DEBT_TRACKER_GOOGLE_URL',
        'https://docs.google.com/spreadsheets/d/1oQr8h3ecQHguDv03sm34YQ649fjrWnr_7awVHFub7LU/edit#gid=1707280998'
    ),

    'debt_tracker_sharepoint_url' => env('DEBT_TRACKER_SHAREPOINT_URL'),

    'debt_tracker_sheet' => env('DEBT_TRACKER_SHEET', 'LT debt'),

    'debt_tracker_file_name' => env('DEBT_TRACKER_FILE_NAME', 'CHARGEBACKS-DEBTS.xlsx'),

];
