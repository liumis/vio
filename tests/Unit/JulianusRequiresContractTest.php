<?php

namespace Tests\Unit;

use App\Models\Authority;
use App\Models\FineProcessLog;
use App\Support\FineReplyReadiness;
use App\Support\IncomingFineReplyBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JulianusRequiresContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_julianus_readiness_requires_contract(): void
    {
        $authority = Authority::query()->create([
            'name' => 'Julianus inkaso',
            'from_email' => 'klausimai@julianus.lt',
            'main_email' => 'info@julianus.lt',
            'to_email' => 'info@julianus.lt',
            'email_pattern' => '@julianus.lt',
            'mail_template' => "Sveiki,\n\nVairuotojas: [%2%]\nAuto: [%4%]\nEl. paštas: [%8%]\nGimimo data: [%7%]\n\nPagarbiai\n",
            'requires_contract' => true,
        ]);

        $log = FineProcessLog::query()->create([
            'graph_message_id' => 'test-julianus-contract-'.uniqid(),
            'authority_id' => $authority->id,
            'authority_name' => $authority->name,
            'from_email' => $authority->from_email,
            'vehicle' => 'NUC319',
            'driver_name' => 'Dursun Polat',
            'reference' => '716323194',
            'ticket_date' => '2026-11-01',
            'agr_no' => null,
            'workflow' => [
                'wheelsys' => [
                    'driver' => 'Dursun Polat',
                    'vehicle' => 'NUC319',
                    'customer_email' => 'a@b.com',
                    'birth_date' => '1985-01-07',
                    'party' => 'driver',
                ],
            ],
        ]);

        $info = FineReplyReadiness::forLog($log->fresh()->load('authority'));
        $this->assertFalse($info['ready']);
        $this->assertContains('contract', $info['missing']);

        $log->fill(['agr_no' => 'RNT-12345'])->save();
        $workflow = $log->workflow;
        $workflow['wheelsys']['agr_no'] = 'RNT-12345';
        $log->fill(['workflow' => $workflow])->save();

        $info2 = FineReplyReadiness::forLog($log->fresh()->load('authority'));
        $this->assertNotContains('contract', $info2['missing']);
    }

    public function test_julianus_reply_body_includes_contract_line(): void
    {
        $authority = Authority::query()->create([
            'name' => 'Julianus inkaso',
            'from_email' => 'klausimai@julianus.lt',
            'main_email' => 'info@julianus.lt',
            'to_email' => 'info@julianus.lt',
            'email_pattern' => '@julianus.lt',
            'mail_template' => "Sveiki,\n\nVairuotojas: [%2%]\nAuto: [%4%]\nEl. paštas: [%8%]\nGimimo data: [%7%]\n\nPagarbiai\n",
            'requires_contract' => true,
        ]);

        $log = FineProcessLog::query()->create([
            'graph_message_id' => 'test-julianus-body-'.uniqid(),
            'authority_id' => $authority->id,
            'authority_name' => $authority->name,
            'from_email' => $authority->from_email,
            'vehicle' => 'NUC319',
            'driver_name' => 'Dursun Polat',
            'reference' => '716323194',
            'ticket_date' => '2026-11-01',
            'agr_no' => 'RNT-999',
        ]);

        $draft = app(IncomingFineReplyBuilder::class)->buildForProcessLog($log, [
            'driver' => 'Dursun Polat',
            'vehicle' => 'NUC319',
            'customer_email' => 'a@b.com',
            'birth_date' => '1985-01-07',
            'agr_no' => 'RNT-999',
            'party' => 'driver',
        ]);

        $this->assertTrue($draft['requires_contract']);
        $this->assertSame('RNT-999', $draft['agr_no']);
        $this->assertStringContainsString('Nuomos sutartis (RNT): RNT-999', $draft['body_text']);
        $this->assertNotContains('contract', $draft['missing_fields']);
    }
}
