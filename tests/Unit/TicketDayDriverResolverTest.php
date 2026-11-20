<?php

namespace Tests\Unit;

use App\Support\TicketDayDriverResolver;
use PHPUnit\Framework\TestCase;

class TicketDayDriverResolverTest extends TestCase
{
    private TicketDayDriverResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new TicketDayDriverResolver;
    }

    public function test_none_when_empty(): void
    {
        $result = $this->resolver->resolveUniqueDrivers([]);
        $this->assertSame('none', $result['outcome']);
        $this->assertNull($result['chosen']);
    }

    public function test_single_driver_even_with_multiple_rentals(): void
    {
        $rentals = [
            ['id' => 1, 'driver_id' => 10, 'driver' => 'Jonas Jonaitis', 'agr_no' => 'A1'],
            ['id' => 2, 'driver_id' => 10, 'driver' => 'Jonas Jonaitis', 'agr_no' => 'A2'],
        ];
        $result = $this->resolver->resolveUniqueDrivers($rentals);
        $this->assertSame('single', $result['outcome']);
        $this->assertSame(1, $result['chosen']['id']);
        $this->assertCount(1, $result['driver_keys']);
    }

    public function test_ambiguous_when_two_drivers_same_day(): void
    {
        $rentals = [
            ['id' => 1, 'driver_id' => 10, 'driver' => 'Jonas Jonaitis', 'agr_no' => 'A1'],
            ['id' => 2, 'driver_id' => 20, 'driver' => 'Petras Petraitis', 'agr_no' => 'A2'],
        ];
        $result = $this->resolver->resolveUniqueDrivers($rentals);
        $this->assertSame('ambiguous', $result['outcome']);
        $this->assertNull($result['chosen']);
        $this->assertCount(2, $result['driver_labels']);
        $this->assertContains('Jonas Jonaitis', $result['driver_labels']);
        $this->assertContains('Petras Petraitis', $result['driver_labels']);
    }

    public function test_falls_back_to_driver_name_when_no_id(): void
    {
        $rentals = [
            ['id' => 1, 'driver' => 'Jonas Jonaitis'],
            ['id' => 2, 'driver' => '  jonas   jonaitis '],
        ];
        $result = $this->resolver->resolveUniqueDrivers($rentals);
        $this->assertSame('single', $result['outcome']);
    }
}
