<?php

namespace Database\Seeders;

use App\Models\Authority;
use Illuminate\Database\Seeder;

class AuthoritySeeder extends Seeder
{
    public function run(): void
    {
        $driverReplyTemplate = 'Sveiki, automobilį [%4%] pažeidimo metu vairavo: vardas, pavardė: [%2%] gim. data : [%7%] telefono nr.: [%10%] el.paštas : [%8%] vair. pažymėjimo numeris: [%5%] vair. pažymėjimo išdavimo data : [%11%] vair.pažymėjimą išdavusi valstybė : [%12%] Adresas: [%9%]';

        $vilniusTemplate = 'Sveiki, Pagal gautą Vilniaus miesto savivaldybės administracijos viešosios tvarkos grupės pranešimą Nr. [%1%], pateikiama informacija apie asmenį, [%2%], [%3%], padariusį pažeidimą transporto priemone valstybinis numeris [%4%] Vardas, pavardė: [%6%] Gimimo data arba asmens kodas: [%7%] El. Paštas: [%8%] Adresas: [%9%] Telefonas: [%10%]';

        // Instrukcija: Vilniaus ANAM — „Informacija turi būti pateikta per 5 darbo dienas
        // nuo laiško gavimo“. ROIK / kitos institucijos — ta pati seka, atskiro termino nėra.
        $defaultTerminas = [
            'informavimo_terminas' => 5,
            'informavimo_terminas_tipas' => 'working',
        ];

        $rows = [
            [
                'name' => 'Vilniaus miesto savivaldybė',
                'from_email' => 'anam@vilnius.lt',
                'main_email' => 'info@vilnius.lt',
                'to_email' => 'anam@vilnius.lt',
                'email_pattern' => '@vilnius.lt',
                'mail_template' => $vilniusTemplate,
                'info_pdf_template' => 'vilnius',
                ...$defaultTerminas,
            ],
            [
                'name' => 'Unipark',
                'from_email' => 'no.reply@unipark.lt',
                'main_email' => 'info@unipark.lt',
                'to_email' => 'info@unipark.lt',
                'email_pattern' => '@unipark.lt',
                'mail_template' => $driverReplyTemplate,
                'info_pdf_template' => null,
                ...$defaultTerminas,
            ],
            [
                'name' => 'Judu',
                'from_email' => 'noreply@judu.lt',
                'main_email' => 'info@judu.lt',
                'to_email' => 'info@judu.lt',
                'email_pattern' => '@judu.lt',
                'mail_template' => $driverReplyTemplate,
                'info_pdf_template' => null,
                ...$defaultTerminas,
            ],
            [
                'name' => 'Julianus inkaso',
                'from_email' => 'klausimai@julianus.lt',
                'main_email' => 'info@julianus.lt',
                'to_email' => 'info@julianus.lt',
                'email_pattern' => '@julianus.lt',
                'mail_template' => $driverReplyTemplate,
                'info_pdf_template' => null,
                ...$defaultTerminas,
            ],
            [
                // ROIK/ANR: per „Policijos administravimo instrukcija“ duomenys teikiami
                // IRD ePaslaugose (ne el. laišku į auto-anr-no-reply@anr.gov.lt).
                'name' => 'Administracinių nusižengimų registras',
                'from_email' => 'auto-anr-no-reply@anr.gov.lt',
                'main_email' => null,
                'to_email' => null,
                'email_pattern' => '@anr.gov.lt',
                'mail_template' => $driverReplyTemplate,
                'info_pdf_template' => null,
                ...$defaultTerminas,
            ],
            [
                'name' => 'Legal Balance',
                'from_email' => 'apmokejimai@legalbalance.lt',
                'main_email' => 'apmokejimai@legalbalance.lt',
                'to_email' => 'apmokejimai@legalbalance.lt',
                'email_pattern' => '@legalbalance.lt',
                'mail_template' => $driverReplyTemplate,
                'info_pdf_template' => null,
                ...$defaultTerminas,
            ],
            [
                'name' => 'Parkavimas mieste',
                'from_email' => 'info@parkavimasmieste.lt',
                'main_email' => 'info@parkavimasmieste.lt',
                'to_email' => 'info@parkavimasmieste.lt',
                'email_pattern' => '@parkavimasmieste.lt',
                'mail_template' => $driverReplyTemplate,
                'info_pdf_template' => null,
                ...$defaultTerminas,
            ],
            [
                'name' => 'Latvijos policija (CPP)',
                'from_email' => 'kanceleja.cpp@vp.gov.lv',
                'main_email' => 'kanceleja.cpp@vp.gov.lv',
                'to_email' => 'kanceleja.cpp@vp.gov.lv',
                'email_pattern' => '@vp.gov.lv',
                'mail_template' => $driverReplyTemplate,
                'info_pdf_template' => 'latvia',
                ...$defaultTerminas,
            ],
            [
                // Fizinių laiškų CAN… numeriai — atsakymas el. paštu (instrukcija VII.2).
                'name' => 'Lenkijos GITD (CAN)',
                'from_email' => 'canard@gitd.gov.pl',
                'main_email' => 'canard@gitd.gov.pl',
                'to_email' => 'canard@gitd.gov.pl',
                'email_pattern' => '@gitd.gov.pl',
                'mail_template' => $driverReplyTemplate,
                'info_pdf_template' => null,
                ...$defaultTerminas,
            ],
            [
                // Teismo nutarimai — manual forma (instrukcija VIII); pirmiausia teisininkui.
                'name' => 'Teismas',
                'from_email' => null,
                'main_email' => 'vilniaus.apylinkes@teismas.lt',
                'to_email' => null,
                'email_pattern' => '@teismas.lt',
                'mail_template' => $driverReplyTemplate,
                'info_pdf_template' => null,
                ...$defaultTerminas,
            ],
        ];

        foreach ($rows as $row) {
            Authority::query()->updateOrCreate(
                ['email_pattern' => $row['email_pattern']],
                $row,
            );
        }
    }
}
