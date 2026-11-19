<?php

namespace Tests\Unit;

use App\Support\IncomingFineReplyBuilder;
use PHPUnit\Framework\TestCase;

class IncomingFineVehicleExtractTest extends TestCase
{
    private IncomingFineReplyBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new IncomingFineReplyBuilder;
    }

    public function test_extracts_labeled_unipark_plate(): void
    {
        $text = "UNIPARK pranešimas\nValstybinis numeris: MAB841\nSkolos susidarymo data 2026-10-19";
        $this->assertSame('MAB841', $this->builder->extractVehicle('Unipark', $text));
    }

    public function test_extracts_brand_and_plate(): void
    {
        $text = 'PAŽEIDIMO FIKSAVIMO DATA - KIA MRH986;2026-08-15T11:12:55';
        $this->assertSame('MRH986', $this->builder->extractVehicle('Julianus', $text));
    }

    public function test_extracts_plate_glued_to_date(): void
    {
        $text = 'fiksavimo data: SKODA NNO302;2026-09-30:';
        $this->assertSame('NNO302', $this->builder->extractVehicle('x', $text));
    }

    public function test_ignores_unipark_ticket_code_in_subject(): void
    {
        $this->assertSame('', $this->builder->extractVehicle('[UNIPARK] Re: 6T8Y439', 'Anuliavome pranešimą'));
    }

    public function test_extracts_v_slash_n_label(): void
    {
        $text = "V/N: ABC123\nsuma 12 EUR";
        $this->assertSame('ABC123', $this->builder->extractVehicle('Judu', $text));
    }

    public function test_extracts_latvian_registration_label(): void
    {
        $text = "Transportlīdzekļa reģistrācijas Nr.: MAB841\nLēmums PZ00160267";
        $this->assertSame('MAB841', $this->builder->extractVehicle('Vēstule', $text));
    }
}
