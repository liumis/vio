<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Fills missing Lithuanian company name ↔ code using public JAR-backed data
 * (Contoros API; same register data as rekvizitai.lt).
 */
final class LithuanianCompanyLookup
{
    private const SEARCH_URL = 'https://www.kontoros.lt/api/v1/companies/search';

    private const BY_CODE_URL = 'https://www.kontoros.lt/api/v1/companies/';

    /**
     * Resolve the missing side when exactly one of name/code is known.
     *
     * @return array{
     *     name: string,
     *     code: string,
     *     display_name: string,
     *     filled: list<string>,
     *     source: string,
     *     source_url: string|null,
     *     rekvizitai_url: string|null,
     *     query: string,
     *     status: 'filled'|'ambiguous'|'not_found'|'skipped'
     * }|null
     */
    public function resolve(string $name, string $code): ?array
    {
        $name = trim($name);
        $code = $this->normalizeCompanyCode($code);

        $hasName = $name !== '';
        $hasCode = $code !== '';

        if ($hasName === $hasCode) {
            return null;
        }

        try {
            $match = $hasCode
                ? $this->findByCode($code)
                : $this->findByName($name);

            if ($match === null) {
                return [
                    'name' => $name,
                    'code' => $code,
                    'display_name' => $name,
                    'filled' => [],
                    'source' => 'rekvizitai.lt / JAR',
                    'source_url' => null,
                    'rekvizitai_url' => $hasCode
                        ? $this->rekvizitaiUrlForCode($code)
                        : $this->rekvizitaiUrlForQuery($name),
                    'query' => $hasCode ? $code : $name,
                    'status' => 'not_found',
                ];
            }

            if (($match['status'] ?? '') === 'ambiguous') {
                return [
                    'name' => $name,
                    'code' => $code,
                    'display_name' => $name,
                    'filled' => [],
                    'source' => 'rekvizitai.lt / JAR',
                    'source_url' => null,
                    'rekvizitai_url' => $this->rekvizitaiUrlForQuery($name),
                    'query' => $name,
                    'status' => 'ambiguous',
                ];
            }

            $resolvedName = trim((string) ($match['display_name'] ?? $match['name'] ?? ''));
            $resolvedCode = $this->normalizeCompanyCode((string) ($match['code'] ?? ''));
            $filled = [];

            if (! $hasName && $resolvedName !== '') {
                $filled[] = 'company';
                $name = $resolvedName;
            }
            if (! $hasCode && $resolvedCode !== '') {
                $filled[] = 'company_code';
                $code = $resolvedCode;
            }

            if ($filled === []) {
                return null;
            }

            return [
                'name' => $name,
                'code' => $code,
                'display_name' => $resolvedName !== '' ? $resolvedName : $name,
                'filled' => $filled,
                'source' => 'rekvizitai.lt / JAR',
                'source_url' => filled($match['url'] ?? null) ? (string) $match['url'] : null,
                'rekvizitai_url' => $code !== ''
                    ? $this->rekvizitaiUrlForCode($code)
                    : $this->rekvizitaiUrlForQuery($name),
                'query' => $hasCode ? $code : $name,
                'status' => 'filled',
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{code?:string,name?:string,display_name?:string,url?:string,status?:string}|null
     */
    public function findByCode(string $code): ?array
    {
        $code = $this->normalizeCompanyCode($code);
        if ($code === '') {
            return null;
        }

        $response = Http::acceptJson()
            ->timeout(HttpTimeouts::short())
            ->get(self::BY_CODE_URL.$code);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json('data');
        if (! is_array($data) || trim((string) ($data['code'] ?? '')) === '') {
            return null;
        }

        return [
            'code' => (string) ($data['code'] ?? ''),
            'name' => (string) ($data['name'] ?? ''),
            'display_name' => (string) ($data['display_name'] ?? $data['name'] ?? ''),
            'url' => (string) ($data['url'] ?? ''),
        ];
    }

    /**
     * @return array{code?:string,name?:string,display_name?:string,url?:string,status?:string}|null
     */
    public function findByName(string $name): ?array
    {
        $name = trim($name);
        if (mb_strlen($name) < 2) {
            return null;
        }

        $response = Http::acceptJson()
            ->timeout(HttpTimeouts::short())
            ->get(self::SEARCH_URL, [
                'q' => $name,
                'limit' => 8,
                'active' => 1,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $rows = $response->json('data');
        if (! is_array($rows) || $rows === []) {
            return null;
        }

        $wanted = $this->normalizeCompanyName($name);
        $exact = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $candidates = [
                $this->normalizeCompanyName((string) ($row['display_name'] ?? '')),
                $this->normalizeCompanyName((string) ($row['name'] ?? '')),
            ];
            if (in_array($wanted, $candidates, true)) {
                $exact[] = $row;
            }
        }

        if (count($exact) === 1) {
            $row = $exact[0];

            return [
                'code' => (string) ($row['code'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'display_name' => (string) ($row['display_name'] ?? $row['name'] ?? ''),
                'url' => (string) ($row['url'] ?? ''),
            ];
        }

        if (count($exact) > 1) {
            return ['status' => 'ambiguous'];
        }

        // Single search hit and names clearly overlap — accept.
        if (count($rows) === 1 && is_array($rows[0])) {
            $row = $rows[0];
            $got = $this->normalizeCompanyName((string) ($row['display_name'] ?? $row['name'] ?? ''));
            if ($got !== '' && ($this->namesOverlap($wanted, $got) || $this->namesOverlap($got, $wanted))) {
                return [
                    'code' => (string) ($row['code'] ?? ''),
                    'name' => (string) ($row['name'] ?? ''),
                    'display_name' => (string) ($row['display_name'] ?? $row['name'] ?? ''),
                    'url' => (string) ($row['url'] ?? ''),
                ];
            }
        }

        return ['status' => 'ambiguous'];
    }

    public function normalizeCompanyCode(string $value): string
    {
        $value = strtoupper(trim($value));
        if ($value === '') {
            return '';
        }

        // Plain JAR code.
        if (preg_match('/^\d{7,9}$/', $value)) {
            return $value;
        }

        // Digits only after stripping noise (ignore VAT LT… unless leftover is 7–9 digits).
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (preg_match('/^\d{7,9}$/', $digits)) {
            return $digits;
        }

        return '';
    }

    public function normalizeCompanyName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = str_replace(['"', '„', '"', "'", '«', '»'], '', $name);
        $name = preg_replace(
            '/\b(uab|ab|mb|ii|viešoji\s+įstaiga|uždaroji\s+akcinė\s+bendrovė|uzdaroji\s+akcine\s+bendrove)\b/u',
            '',
            $name
        ) ?? $name;
        $name = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name) ?? $name;
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return trim($name);
    }

    private function namesOverlap(string $a, string $b): bool
    {
        if ($a === '' || $b === '') {
            return false;
        }

        return str_contains($a, $b) || str_contains($b, $a);
    }

    private function rekvizitaiUrlForCode(string $code): string
    {
        return 'https://rekvizitai.vz.lt/en/company-search/?code='.rawurlencode($code);
    }

    private function rekvizitaiUrlForQuery(string $query): string
    {
        return 'https://rekvizitai.vz.lt/en/company-search/?name='.rawurlencode($query);
    }
}
