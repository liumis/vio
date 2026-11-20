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

    public function test_extracts_judu_plate_from_subject_parentheses(): void
    {
        $subject = 'JUDU: Dėl nesumokėtos rinkliavos. Pranešimo Nr. A1429299 (CAV1AR)';
        $text = "Laikas 2026-11-19 13:41\nPranešimo Nr.A1429299\nValstybinis Nr.CAV1AR\nSumokėti iki 2026-11-26 23:59";
        $this->assertSame('CAV1AR', $this->builder->extractVehicle($subject, $text));
    }

    public function test_judu_does_not_treat_sumoketi_iki_year_as_plate(): void
    {
        $text = "Laikas 2026-11-19 13:41\nPranešimo Nr.A1429299\nValstybinis Nr.CAV1AR\nSumokėti iki 2026-11-26 23:59";
        $this->assertSame('CAV1AR', $this->builder->extractVehicle('JUDU notice', $text));
        $this->assertNotSame('IKI2026', $this->builder->extractVehicle('JUDU notice', $text));
    }

    public function test_extracts_latvian_registration_label(): void
    {
        $text = "Transportlīdzekļa reģistrācijas Nr.: MAB841\nLēmums PZ00160267";
        $this->assertSame('MAB841', $this->builder->extractVehicle('Vēstule', $text));
    }

    public function test_extracts_judu_laikas_time(): void
    {
        $text = "Laikas 2026-11-18 13:55\nPranešimo Nr.A1428780\nValstybinis Nr.EF9367\nSumokėti iki 2026-11-25 23:59";
        $this->assertSame('2026-11-18', $this->builder->extractTicketDate('JUDU', $text));
        $this->assertSame('13:55', $this->builder->extractTicketTime('JUDU', $text));
    }

    public function test_extracts_julianus_iso_time(): void
    {
        $text = 'PAŽEIDIMO FIKSAVIMO DATA - KIA MRH986;2026-08-15T11:12:55';
        $this->assertSame('11:12', $this->builder->extractTicketTime('Julianus', $text));
    }

    public function test_extracts_camera_filename_time(): void
    {
        $text = '20260906143426188-ID_cam-NTH054_B.jpeg';
        $this->assertSame('14:34', $this->builder->extractTicketTime('x', $text));
    }
}
