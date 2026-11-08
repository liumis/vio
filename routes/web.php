<?php

use App\Http\Controllers\MarkSignCallbackController;
use App\Http\Controllers\WheelsysSsoController;
use Illuminate\Support\Facades\Route;

Route::post('/marksign/callback', [MarkSignCallbackController::class, 'handle'])->name('marksign.callback');

Route::middleware('auth')->group(function () {
    Route::get('/wheelsys/open', [WheelsysSsoController::class, 'open'])->name('wheelsys.sso');
    Route::get('/wheelsys/create-traffic-violation', [WheelsysSsoController::class, 'createTrafficViolation'])
        ->name('wheelsys.create-traffic-violation');
});



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

