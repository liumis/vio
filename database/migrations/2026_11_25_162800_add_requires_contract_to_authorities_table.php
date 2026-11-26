<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('authorities', 'requires_contract')) {
            Schema::table('authorities', function (Blueprint $table): void {
                $table->boolean('requires_contract')->default(false)->after('info_pdf_template');
            });
        }

        // Julianus Inkaso: authority reply must track / attach the rental contract (RNT).
        DB::table('authorities')
            ->where(function ($q): void {
                $q->where('email_pattern', 'like', '%julianus%')
                    ->orWhere('name', 'like', '%Julianus%');
            })
            ->update(['requires_contract' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('authorities', 'requires_contract')) {
            Schema::table('authorities', function (Blueprint $table): void {
                $table->dropColumn('requires_contract');
            });
        }
    }
};
