<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_settings')) {
            return;
        }

        Schema::table('email_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('email_settings', 'mail_signature')) {
                $table->text('mail_signature')->nullable()->after('subject');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('email_settings')) {
            return;
        }

        Schema::table('email_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('email_settings', 'mail_signature')) {
                $table->dropColumn('mail_signature');
            }
        });
    }
};
