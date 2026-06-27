<?php

namespace App\Support;

use App\Models\Authority;

/**
 * Matches an imported authority email to an Authority record by domain,
 * using the Authority "email_pattern" field (usually the "@domain.com" part)
 * instead of an exact email comparison.
 */
final class AuthorityMatcher
{
    /**
     * Lowercased domain part after the last "@" (the pattern itself if no "@").
     */
    public static function domainOf(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $atPos = strrpos($value, '@');

        return $atPos === false ? $value : substr($value, $atPos + 1);
    }

    /**
     * Normalize a stored pattern to a bare domain ("@vilnius.lt" => "vilnius.lt").
     */
    public static function normalizePattern(string $pattern): string
    {
        return self::domainOf($pattern);
    }

    public static function findForEmail(string $authorityEmail): ?Authority
    {
        $domain = self::domainOf($authorityEmail);

        if ($domain === '') {
            return null;
        }

        return Authority::query()
            ->whereNotNull('email_pattern')
            ->where('email_pattern', '!=', '')
            ->get()
            ->first(fn (Authority $authority): bool => self::normalizePattern((string) $authority->email_pattern) === $domain);
    }
}
