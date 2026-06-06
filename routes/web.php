<?php

use App\Http\Controllers\DriverInfoController;
use Illuminate\Support\Facades\Route;

Route::get('/driver-info', [DriverInfoController::class, 'create'])->name('driver-info.create');
Route::post('/driver-info', [DriverInfoController::class, 'store'])->name('driver-info.store');

Route::get('/preview/email/violation', function () {
    return view('emails.violation-notification', [
        'subject' => 'Violation notification',
        'recipientName' => 'John Driver',
        'ticketNumber' => 'TK-2026-00951',
        'violationType' => 'Speeding',
        'ticketDate' => '2026-04-24',
        'totalCharge' => '125.00',
        'currency' => 'EUR',
        'vehicle' => '34-AB-1234',
        'issuingAuthority' => 'City Traffic Police',
        'detailsUrl' => url('/violations'),
    ]);
});

Route::get('/preview/email/password-reset', function () {
    return view('emails.password-reset', [
        'subject' => 'Reset your Sit&Go password',
        'recipientName' => 'John Driver',
        'resetUrl' => url('/password-reset/example-token?email=admin@example.com'),
        'expireMinutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
    ]);
});

