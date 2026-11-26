<?php

namespace Tests\Unit;

use App\Models\Authority;
use App\Support\IncomingFineReplyBuilder;
use PHPUnit\Framework\TestCase;

class CorporateMailTemplateTest extends TestCase
{
    public function test_resolves_corporate_template_when_party_is_corporate(): void
    {
        $authority = new Authority([
            'mail_template' => "Driver [%2%]",
            'mail_template_corporate' => "Įmonė: [%2%]\nĮmonės kodas: [%13%]",
        ]);

        $builder = new IncomingFineReplyBuilder;

        $this->assertSame(
            "Įmonė: [%2%]\nĮmonės kodas: [%13%]",
            $builder->resolveMailTemplate($authority, 'corporate')
        );
        $this->assertSame(
            'Driver [%2%]',
            $builder->resolveMailTemplate($authority, 'driver')
        );
    }

    public function test_falls_back_to_driver_template_when_corporate_empty(): void
    {
        $authority = new Authority([
            'mail_template' => 'Driver [%2%]',
            'mail_template_corporate' => null,
        ]);

        $builder = new IncomingFineReplyBuilder;

        $this->assertSame(
            'Driver [%2%]',
            $builder->resolveMailTemplate($authority, 'corporate')
        );
    }

    public function test_formats_corporate_field_labels(): void
    {
        $builder = new IncomingFineReplyBuilder;
        $raw = 'Sveiki, Automobilį **NTD729** pažeidimo metu vairavo: Įmonė: **Baltic Ground Services, UAB** Įmonės kodas: **123** Adresas: Vilnius El. paštas: a@b.lt Telefono numeris: 860000000';

        $formatted = $builder->formatReplyBodyForReadability($raw);

        $this->assertStringContainsString("\nĮmonė:", $formatted);
        $this->assertStringContainsString("\nĮmonės kodas:", $formatted);
        $this->assertStringContainsString("\nTelefono numeris:", $formatted);
    }
}
