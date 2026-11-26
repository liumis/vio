<?php

namespace Tests\Unit;

use App\Support\IncomingFineReplyBuilder;
use PHPUnit\Framework\TestCase;

class AuthorityReplyFormattingTest extends TestCase
{
    public function test_formats_smashed_one_line_reply(): void
    {
        $builder = new IncomingFineReplyBuilder;
        $raw = 'Sveiki, automobilį **ABC123** pažeidimo metu vairavo: vardas, pavardė: **Jonas** gim. data : **1990-01-15** telefono nr.: 860000000 el.paštas : a@b.lt Adresas: Vilnius';

        $formatted = $builder->formatReplyBodyForReadability($raw);

        $this->assertStringContainsString("Sveiki,\n\n", $formatted);
        $this->assertStringContainsString("\nvardas, pavardė:", $formatted);
        $this->assertStringContainsString("\ngim. data", $formatted);
        $this->assertStringContainsString("\nAdresas:", $formatted);
        $this->assertGreaterThanOrEqual(3, substr_count($formatted, "\n"));
    }

    public function test_keeps_already_multiline_body(): void
    {
        $builder = new IncomingFineReplyBuilder;
        $raw = "Sveiki,\n\nVardas, pavardė: Jonas\nAdresas: Vilnius\n\nPagarbiai";

        $this->assertSame($raw, $builder->formatReplyBodyForReadability($raw));
    }

}
