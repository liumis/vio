<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('violations', 'last_email_sent_at')) {
            Schema::table('violations', function (Blueprint $table): void {
                $table->timestamp('last_email_sent_at')->nullable()->after('last_email_attempted_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('violations', 'last_email_sent_at')) {
            Schema::table('violations', function (Blueprint $table): void {
                $table->dropColumn('last_email_sent_at');
            });
        }
    }
};
