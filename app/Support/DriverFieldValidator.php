<?php

namespace App\Support;

use Carbon\Carbon;

final class DriverFieldValidator
{
    public static function birthDateError(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        try {
            if (Carbon::parse($value)->age < 18) {
                return 'Gimimo data must indicate age 18 or older.';
            }
        } catch (\Throwable) {
            return 'Gimimo data is not a valid date.';
        }

        return null;
    }

    public static function driverLicenseError(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        if (! preg_match('/^\d+$/', (string) $value)) {
            return 'Vairuotojo pažymėjimo Nr. must contain digits only.';
        }

        return null;
    }
}
