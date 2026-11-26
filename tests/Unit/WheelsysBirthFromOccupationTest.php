<?php

namespace Tests\Unit;

use App\Support\WheelsysClient;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class WheelsysBirthFromOccupationTest extends TestCase
{
    public function test_normalize_driver_row_fills_birth_from_occupation(): void
    {
        $client = new WheelsysClient;
        $method = new ReflectionMethod(WheelsysClient::class, 'normalizeDriverRow');
        $method->setAccessible(true);

        $row = $method->invoke($client, [
            'Id' => 1,
            'Name' => 'Jonas Jonaitis',
            'Occupation' => '39001151238',
            'BirthDate' => null,
            'Address' => 'Gedimino 1',
            'City' => 'Vilnius',
        ]);

        $this->assertSame('1990-01-15', $row['birth_date']);
        $this->assertSame('39001151238', $row['personal_code']);
        $this->assertSame('39001151238', $row['occupation']);
    }

    public function test_birth_date_from_personal_code_sources_reads_raw_occupation(): void
    {
        $client = new WheelsysClient;
        $method = new ReflectionMethod(WheelsysClient::class, 'birthDateFromPersonalCodeSources');
        $method->setAccessible(true);

        $birth = $method->invoke($client, [
            'raw' => ['Occupation' => 'AK 48503151233'],
        ]);

        $this->assertSame('1985-03-15', $birth);
    }
}
