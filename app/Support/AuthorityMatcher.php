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

    /**
     * Guess Authority from subject/body/attachment text (forwards, keyword-only hits).
     */
    public static function findFromHaystack(string $haystack): ?Authority
    {
        $haystack = trim($haystack);
        if ($haystack === '') {
            return null;
        }

        $lower = mb_strtolower($haystack, 'UTF-8');

        $pattern = null;

        if (preg_match('/\bROIK\b/iu', $haystack)
            || str_contains($lower, 'anr.gov.lt')
            || str_contains($lower, 'administracinių nusižengimų registro')
            || str_contains($lower, 'administraciniu nusizengimu registro')
        ) {
            $pattern = '@anr.gov.lt';
        } elseif (preg_match('/\bA\d{1,4}-\d{3,}\b/u', $haystack)
            || str_contains($lower, 'vilniaus miesto savivaldy')
            || str_contains($lower, 'viešosios tvarkos grup')
            || str_contains($lower, 'viesosios tvarkos grup')
            || str_contains($lower, '@vilnius.lt')
        ) {
            $pattern = '@vilnius.lt';
        } elseif (str_contains($lower, 'judu')
            || preg_match('/\bA\d{7,}\b/u', $haystack)
        ) {
            // Judu refs look like A1424058; only use that hint with JUDU keyword to avoid false Vilnius-less hits.
            if (str_contains($lower, 'judu')) {
                $pattern = '@judu.lt';
            }
        }

        if ($pattern === null) {
            if (str_contains($lower, '@teismas.lt')
                || str_contains($lower, 'teismo nutar')
                || str_contains($lower, 'teismo nutart')
                || preg_match('/\bE1-\d+/iu', $haystack)
            ) {
                $pattern = '@teismas.lt';
            } elseif (preg_match('/\bCAN[A-Z0-9\-]*\d/iu', $haystack)
                || str_contains($lower, 'gitd.gov.pl')
                || str_contains($lower, 'canard@gitd')
                || str_contains($lower, 'lenkijos')
                || (str_contains($lower, 'poland') && str_contains($lower, 'gitd'))
            ) {
                $pattern = '@gitd.gov.pl';
            } elseif (preg_match('/\b(?:GA|PZ)\d{5,}\b/iu', $haystack)
                || str_contains($lower, 'vp.gov.lv')
                || str_contains($lower, 'valsts polic')
                || str_contains($lower, 'latvijas polic')
            ) {
                $pattern = '@vp.gov.lv';
            } elseif (str_contains($lower, 'unipark') || str_contains($lower, '@unipark.lt')) {
                $pattern = '@unipark.lt';
            } elseif (str_contains($lower, 'julianus') || str_contains($lower, '@julianus.lt')) {
                $pattern = '@julianus.lt';
            } elseif (str_contains($lower, 'legal balance') || str_contains($lower, 'legalbalance')) {
                $pattern = '@legalbalance.lt';
            } elseif (str_contains($lower, 'parkavimas mieste') || str_contains($lower, 'parkavimasmieste')) {
                $pattern = '@parkavimasmieste.lt';
            }
        }

        if ($pattern === null) {
            return null;
        }

        return Authority::query()
            ->where('email_pattern', $pattern)
            ->first();
    }
}
