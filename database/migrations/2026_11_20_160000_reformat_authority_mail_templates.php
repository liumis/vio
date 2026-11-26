<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('authorities') || ! Schema::hasColumn('authorities', 'mail_template')) {
            return;
        }

        $driverReplyTemplate = <<<'TXT'
Sveiki,

Automobilį [%4%] pažeidimo metu vairavo:

Vardas, pavardė: [%2%]
Gimimo data: [%7%]
Telefono nr.: [%10%]
El. paštas: [%8%]
Vair. pažymėjimo numeris: [%5%]
Vair. pažymėjimo išdavimo data: [%11%]
Vair. pažymėjimą išdavusi valstybė: [%12%]
Adresas: [%9%]

Pagarbiai
TXT;

        $vilniusTemplate = <<<'TXT'
Sveiki,

Pagal gautą Vilniaus miesto savivaldybės administracijos viešosios tvarkos grupės pranešimą Nr. [%1%], pateikiama informacija apie asmenį [%2%], [%3%], padariusį pažeidimą transporto priemone valstybinis numeris [%4%].

Vardas, pavardė: [%6%]
Gimimo data arba asmens kodas: [%7%]
El. paštas: [%8%]
Adresas: [%9%]
Telefonas: [%10%]

Pagarbiai
TXT;

        // Known single-line legacy templates (exact match) → formatted versions.
        $legacyDriver = 'Sveiki, automobilį [%4%] pažeidimo metu vairavo: vardas, pavardė: [%2%] gim. data : [%7%] telefono nr.: [%10%] el.paštas : [%8%] vair. pažymėjimo numeris: [%5%] vair. pažymėjimo išdavimo data : [%11%] vair.pažymėjimą išdavusi valstybė : [%12%] Adresas: [%9%]';
        $legacyVilnius = 'Sveiki, Pagal gautą Vilniaus miesto savivaldybės administracijos viešosios tvarkos grupės pranešimą Nr. [%1%], pateikiama informacija apie asmenį, [%2%], [%3%], padariusį pažeidimą transporto priemone valstybinis numeris [%4%] Vardas, pavardė: [%6%] Gimimo data arba asmens kodas: [%7%] El. Paštas: [%8%] Adresas: [%9%] Telefonas: [%10%]';

        DB::table('authorities')->where('mail_template', $legacyDriver)->update([
            'mail_template' => $driverReplyTemplate,
            'updated_at' => now(),
        ]);

        DB::table('authorities')->where('mail_template', $legacyVilnius)->update([
            'mail_template' => $vilniusTemplate,
            'updated_at' => now(),
        ]);

        // Vilnius by pattern if still one-liners with the same tokens.
        DB::table('authorities')
            ->where('email_pattern', 'like', '%vilnius.lt%')
            ->whereNotNull('mail_template')
            ->where('mail_template', 'not like', "%\n%")
            ->where('mail_template', 'like', '%[%1%]%')
            ->update([
                'mail_template' => $vilniusTemplate,
                'updated_at' => now(),
            ]);

        // Generic driver reply one-liners (have plate + birth tokens, no newlines).
        DB::table('authorities')
            ->whereNotNull('mail_template')
            ->where('mail_template', 'not like', "%\n%")
            ->where('mail_template', 'like', '%[%4%]%')
            ->where('mail_template', 'like', '%[%7%]%')
            ->where('mail_template', 'like', '%[%2%]%')
            ->where(function ($q): void {
                $q->whereNull('email_pattern')
                    ->orWhere('email_pattern', 'not like', '%vilnius.lt%');
            })
            ->update([
                'mail_template' => $driverReplyTemplate,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Non-reversible content cleanup — keep formatted templates.
    }
};
