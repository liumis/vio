<?php

namespace App\Support;

/**
 * Lithuanian personal code (asmens kodas) helpers.
 * Format: G YY MM DD XXX C (11 digits). Birth date is encoded in positions 2–7.
 */
final class LithuanianPersonalCode
{
    /**
     * Extract YYYY-MM-DD from an asmens kodas found in free text (e.g. Occupation).
     */
    public static function birthDateFrom(mixed $haystack): ?string
    {
        $code = self::extract($haystack);
        if ($code === null) {
            return null;
        }

        return self::birthDateFromCode($code);
    }

    /**
     * First valid 11-digit LT personal code in the haystack, or null.
     */
    public static function extract(mixed $haystack): ?string
    {
        $raw = trim((string) $haystack);
        if ($raw === '') {
            return null;
        }

        // Prefer a standalone 11-digit token; also allow codes glued to punctuation.
        if (preg_match_all('/(?<!\d)([1-6]\d{10})(?!\d)/', $raw, $matches)) {
            foreach ($matches[1] as $candidate) {
                if (self::isValid($candidate)) {
                    return $candidate;
                }
            }
        }

        // Digits only (Occupation sometimes stores just the code with spaces).
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (strlen($digits) === 11 && self::isValid($digits)) {
            return $digits;
        }

        return null;
    }

    public static function isValid(string $code): bool
    {
        if (! preg_match('/^[1-6]\d{10}$/', $code)) {
            return false;
        }

        if (self::birthDateFromCode($code) === null) {
            return false;
        }

        $digits = array_map('intval', str_split($code));
        $weights1 = [1, 2, 3, 4, 5, 6, 7, 8, 9, 1];
        $sum1 = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum1 += $digits[$i] * $weights1[$i];
        }
        $r1 = $sum1 % 11;
        if ($r1 !== 10) {
            return $digits[10] === $r1;
        }

        $weights2 = [3, 4, 5, 6, 7, 8, 9, 1, 2, 3];
        $sum2 = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum2 += $digits[$i] * $weights2[$i];
        }
        $r2 = $sum2 % 11;

        return $digits[10] === ($r2 === 10 ? 0 : $r2);
    }

    public static function birthDateFromCode(string $code): ?string
    {
        if (! preg_match('/^[1-6]\d{10}$/', $code)) {
            return null;
        }

        $g = (int) $code[0];
        $yy = (int) substr($code, 1, 2);
        $mm = (int) substr($code, 3, 2);
        $dd = (int) substr($code, 5, 2);

        $century = match ($g) {
            1, 2 => 1800,
            3, 4 => 1900,
            5, 6 => 2000,
            default => null,
        };
        if ($century === null) {
            return null;
        }

        $year = $century + $yy;
        if (! checkdate($mm, $dd, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $mm, $dd);
    }
}
