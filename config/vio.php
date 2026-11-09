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

];
