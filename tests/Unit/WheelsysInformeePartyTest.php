<?php

namespace Tests\Unit;

use App\Support\WheelsysClient;
use PHPUnit\Framework\TestCase;

class WheelsysInformeePartyTest extends TestCase
{
    private WheelsysClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new WheelsysClient;
    }

    public function test_prefers_driver_when_both_present(): void
    {
        $draft = $this->client->rentalAsWheelsysDraft([
            'id' => 1,
            'driver' => 'MINDAUGAS NEVULIS',
            'corporate' => 'Augma, UAB',
            'driver_id' => 247759,
            'agr_no' => 'RNT-89755',
        ]);

        $this->assertSame('driver', $draft['party']);
        $this->assertSame('MINDAUGAS NEVULIS', $draft['driver']);
        $this->assertSame('Augma, UAB', $draft['corporate']);
    }

    public function test_falls_back_to_corporate_when_no_driver(): void
    {
        $draft = $this->client->rentalAsWheelsysDraft([
            'id' => 213847,
            'driver' => '',
            'corporate' => 'Baltic Ground Services, UAB',
            'driver_id' => null,
            'agr_no' => 'RNT-89363',
            'raw' => [
                'Driver_Name' => null,
                'Driver_Id' => null,
                'Corporate_Name' => 'Baltic Ground Services, UAB',
            ],
        ]);

        $this->assertSame('corporate', $draft['party']);
        $this->assertSame('Baltic Ground Services, UAB', $draft['driver']);
    }

    public function test_resolve_does_not_treat_flattened_corporate_name_as_driver(): void
    {
        $party = $this->client->resolveInformeeParty([
            'party' => 'corporate',
            'driver' => 'Baltic Ground Services, UAB',
            'corporate' => 'Baltic Ground Services, UAB',
            'driver_id' => null,
            'raw' => [],
        ]);

        $this->assertSame('corporate', $party);
    }
}
