<?php

namespace App\Http\Controllers;

use App\Support\DriverFieldValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DriverInfoController extends Controller
{
    public function create(): View
    {
        return view('driver-info');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'birth_date' => ['required', 'date'],
            'driver_license' => ['required', 'regex:/^\d+$/'],
            'privacy_policy_accepted' => ['accepted'],
        ], [
            'birth_date.required' => 'Gimimo data is required.',
            'birth_date.date' => 'Gimimo data is not a valid date.',
            'driver_license.required' => 'Vairuotojo pažymėjimo Nr. is required.',
            'driver_license.regex' => 'Vairuotojo pažymėjimo Nr. must contain digits only.',
            'privacy_policy_accepted.accepted' => 'You must agree to the privacy policy.',
        ]);

        $birthDateError = DriverFieldValidator::birthDateError($validated['birth_date']);
        if ($birthDateError !== null) {
            return back()
                ->withInput()
                ->withErrors(['birth_date' => $birthDateError]);
        }

        return back()->with('status', 'Duomenys patvirtinti.');
    }
}
