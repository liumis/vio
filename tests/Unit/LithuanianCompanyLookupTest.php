<?php

namespace Tests\Unit;

use App\Support\LithuanianCompanyLookup;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LithuanianCompanyLookupTest extends TestCase
{
    public function test_fills_code_from_unique_name_match(): void
    {
        Http::fake([
            'www.kontoros.lt/api/v1/companies/search*' => Http::response([
                'data' => [[
                    'code' => '300136658',
                    'name' => 'UAB "Baltic Ground Services"',
                    'display_name' => 'Baltic Ground Services, UAB',
                    'url' => 'https://www.kontoros.lt/imone/300136658/x',
                ]],
                'count' => 1,
            ]),
        ]);

        $result = (new LithuanianCompanyLookup)->resolve('Baltic Ground Services, UAB', '');

        $this->assertSame('filled', $result['status'] ?? null);
        $this->assertSame(['company_code'], $result['filled'] ?? null);
        $this->assertSame('300136658', $result['code'] ?? null);
    }

    public function test_fills_name_from_code(): void
    {
        Http::fake([
            'www.kontoros.lt/api/v1/companies/300138495' => Http::response([
                'data' => [
                    'code' => '300138495',
                    'name' => 'UAB "BMS TECHNOLOGIJA"',
                    'display_name' => 'BMS TECHNOLOGIJA, UAB',
                    'url' => 'https://www.kontoros.lt/imone/300138495/x',
                ],
            ]),
        ]);

        $result = (new LithuanianCompanyLookup)->resolve('', '300138495');

        $this->assertSame('filled', $result['status'] ?? null);
        $this->assertSame(['company'], $result['filled'] ?? null);
        $this->assertSame('BMS TECHNOLOGIJA, UAB', $result['display_name'] ?? null);
    }

    public function test_skips_when_both_or_neither_side_present(): void
    {
        $lookup = new LithuanianCompanyLookup;

        $this->assertNull($lookup->resolve('Acme, UAB', '123456789'));
        $this->assertNull($lookup->resolve('', ''));
    }

    public function test_normalize_company_name_strips_legal_form(): void
    {
        $lookup = new LithuanianCompanyLookup;

        $this->assertSame(
            'baltic ground services',
            $lookup->normalizeCompanyName('Baltic Ground Services, UAB')
        );
    }
}
