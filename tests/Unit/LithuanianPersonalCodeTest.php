<?php

namespace Tests\Unit;

use App\Support\LithuanianPersonalCode;
use PHPUnit\Framework\TestCase;

class LithuanianPersonalCodeTest extends TestCase
{
    public function test_parses_birth_date_from_valid_code(): void
    {
        $this->assertSame('1990-01-15', LithuanianPersonalCode::birthDateFrom('39001151238'));
        $this->assertSame('1985-03-15', LithuanianPersonalCode::birthDateFrom('48503151233'));
        $this->assertSame('2001-01-01', LithuanianPersonalCode::birthDateFrom('50101010016'));
    }

    public function test_extracts_code_from_occupation_text(): void
    {
        $this->assertSame(
            '1990-01-15',
            LithuanianPersonalCode::birthDateFrom('Occupation: 39001151238 LT')
        );
        $this->assertSame(
            '1990-01-15',
            LithuanianPersonalCode::birthDateFrom('39001151238')
        );
    }

    public function test_rejects_invalid_checksum_and_date(): void
    {
        $this->assertNull(LithuanianPersonalCode::birthDateFrom('39001151234'));
        $this->assertNull(LithuanianPersonalCode::birthDateFrom('39999151238'));
        $this->assertNull(LithuanianPersonalCode::birthDateFrom('abc'));
        $this->assertNull(LithuanianPersonalCode::birthDateFrom(''));
    }
}
