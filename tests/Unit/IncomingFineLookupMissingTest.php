<?php

namespace Tests\Unit;

use App\Support\IncomingFineLookup;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class IncomingFineLookupMissingTest extends TestCase
{
    public function test_present_missing_single_gap(): void
    {
        $presented = IncomingFineLookup::presentMissing(['plate']);

        $this->assertSame('Not found · Plate / numeris', $presented['chip']);
        $this->assertStringContainsString('Plate / numeris', $presented['tooltip']);
    }

    public function test_present_missing_many_gaps_uses_count_chip(): void
    {
        $presented = IncomingFineLookup::presentMissing(['plate', 'ticket_date', 'reference']);

        $this->assertSame('Not found · 3 missing', $presented['chip']);
        $this->assertStringContainsString('Plate / numeris', $presented['tooltip']);
        $this->assertStringContainsString('Ticket date', $presented['tooltip']);
        $this->assertStringContainsString('Reference / bylos nr.', $presented['tooltip']);
    }

    public function test_build_lookup_missing_reports_plate_and_date(): void
    {
        $lookup = new IncomingFineLookup;
        $method = new ReflectionMethod(IncomingFineLookup::class, 'buildLookupMissing');
        $method->setAccessible(true);

        $missing = $method->invoke($lookup, [
            'vehicle' => '',
            'ticket_date' => '',
            'ticket_time' => '',
            'ticket_datetime' => '',
            'reference' => '',
        ], '', null, '', null);

        $this->assertContains('plate', $missing);
        $this->assertContains('ticket_date', $missing);
        $this->assertContains('reference', $missing);
        $this->assertNotContains('rental', $missing);
    }

    public function test_build_lookup_missing_reports_driver_when_rental_without_name(): void
    {
        $lookup = new IncomingFineLookup;
        $method = new ReflectionMethod(IncomingFineLookup::class, 'buildLookupMissing');
        $method->setAccessible(true);

        $missing = $method->invoke($lookup, [
            'vehicle' => 'NTD729',
            'ticket_date' => '2026-11-01',
            'ticket_time' => '10:00',
            'ticket_datetime' => '2026-11-01T10:00',
            'reference' => 'ROIK 123',
        ], '2026-11-01', ['id' => 1, 'agr_no' => 'RNT-1'], '', null);

        $this->assertSame(['driver'], $missing);
    }
}
