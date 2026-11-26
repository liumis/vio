<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('authorities')) {
            return;
        }

        if (! Schema::hasColumn('authorities', 'mail_template_corporate')) {
            Schema::table('authorities', function (Blueprint $table): void {
                $table->text('mail_template_corporate')->nullable()->after('mail_template');
            });
        }

        $corporateTemplate = <<<'TXT'
Sveiki,

Automobilį [%4%] pažeidimo metu vairavo:

Įmonė: [%2%]
Įmonės kodas: [%13%]
Adresas: [%9%]
El. paštas: [%8%]
Telefono numeris: [%10%]

Pagarbiai
TXT;

        // Backfill for authorities that already have a driver-style reply template.
        DB::table('authorities')
            ->whereNotNull('mail_template')
            ->where('mail_template', '!=', '')
            ->where(function ($q): void {
                $q->whereNull('mail_template_corporate')
                    ->orWhere('mail_template_corporate', '');
            })
            ->where('mail_template', 'like', '%[%4%]%')
            ->where('mail_template', 'like', '%[%2%]%')
            ->update([
                'mail_template_corporate' => $corporateTemplate,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('authorities', 'mail_template_corporate')) {
            Schema::table('authorities', function (Blueprint $table): void {
                $table->dropColumn('mail_template_corporate');
            });
        }
    }
};
