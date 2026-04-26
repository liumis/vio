<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('violations', 'authority_email')) {
            Schema::table('violations', function (Blueprint $table): void {
                $table->string('authority_email')->nullable()->after('agent');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('violations', 'authority_email')) {
            Schema::table('violations', function (Blueprint $table): void {
                $table->dropColumn('authority_email');
            });
        }
    }
};
