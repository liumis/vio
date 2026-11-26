<?php

namespace Tests\Unit;

use App\Support\IncomingFineMailScanner;
use PHPUnit\Framework\TestCase;

class IncomingFineMailScannerReminderTest extends TestCase
{
    public function test_mokejimo_laikas_is_non_fine(): void
    {
        $this->assertTrue(
            IncomingFineMailScanner::isNonFineNotice('Mokėjimo laikas', 'Primename sumokėti skolą.')
        );
    }

    public function test_real_julianus_fine_subject_not_blocked_by_phrase_alone(): void
    {
        // Generic fine wording must still pass the non-fine phrase gate.
        $this->assertFalse(
            IncomingFineMailScanner::isNonFineNotice(
                'Pranešimas dėl administracinio nusižengimo',
                'Skolos susidarymo data ir laikas 2026-08-15'
            )
        );
    }
}
