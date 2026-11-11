<?php

return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'microsoft_o365' => [
        'enabled' => env('O365_MAIL_ENABLED', false),
        'tenant_id' => env('O365_TENANT_ID'),
        'client_id' => env('O365_CLIENT_ID'),
        'client_secret' => env('O365_CLIENT_SECRET'),
    ],

    'wheelsys' => [
        'base_url' => env('WHEELSYS_BASE_URL', 'https://lt.wheelsys.greenmotion.com'),
        'return_url' => env('WHEELSYS_RETURN_URL', '/ui/dashboards/dailyview.aspx'),
        'import_path' => env('WHEELSYS_IMPORT_PATH', '/ui/manage/tasks/import.aspx'),
        'username' => env('WHEELSYS_USERNAME'),
        'password' => env('WHEELSYS_PASSWORD'),
        'manual_username' => env('WHEELSYS_MANUAL_USERNAME'),
        'manual_password' => env('WHEELSYS_MANUAL_PASSWORD'),
        // Ticket Type must match Wheelsys support list Description
        // (Company → Manage Support Lists → Fleet → Traffic Violation Types).
        'default_ticket_type' => env('WHEELSYS_DEFAULT_TICKET_TYPE', 'Administration violation'),
        // Station code from Wheelsys (e.g. MAIN = Vilnius airport). Required by Import.
        'default_station' => env('WHEELSYS_DEFAULT_STATION', 'MAIN'),
        // State Description from Wheelsys Traffic Violation States support list. Required by Import.
        'default_ticket_state' => env('WHEELSYS_DEFAULT_TICKET_STATE', 'Open'),
    ],



    'sharepoint' => [
        'site_url' => env('SHAREPOINT_SITE_URL', 'https://greenlease.sharepoint.com/sites/Saugykla'),
        'folder_path' => env('SHAREPOINT_FOLDER_PATH', 'Lithuania/Administration/Policijos administravimas'),
    ],

    'marksign' => [
        'token' => env('MARKSIGN_TOKEN'),
        'base_url' => env('MARKSIGN_BASE_URL', 'https://api.marksign.eu'),
        'signer_email' => env('MARKSIGN_SIGNER_EMAIL', 'mykolas@sitandgo.lt'),
        'signer_name' => env('MARKSIGN_SIGNER_NAME', 'Mykolas Sabaitis'),
    ],

    'ocr_space' => [
        'key' => env('OCR_SPACE_API_KEY'),
    ],

];

