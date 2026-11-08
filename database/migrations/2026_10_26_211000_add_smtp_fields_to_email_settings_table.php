<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('email_settings', 'smtp_host')) {
                $table->string('smtp_host')->nullable()->after('inbox_mailbox');
            }
            if (! Schema::hasColumn('email_settings', 'smtp_port')) {
                $table->unsignedInteger('smtp_port')->nullable()->after('smtp_host');
            }
            if (! Schema::hasColumn('email_settings', 'smtp_encryption')) {
                $table->string('smtp_encryption')->nullable()->after('smtp_port');
            }
            if (! Schema::hasColumn('email_settings', 'smtp_username')) {
                $table->string('smtp_username')->nullable()->after('smtp_encryption');
            }
            if (! Schema::hasColumn('email_settings', 'smtp_password')) {
                $table->text('smtp_password')->nullable()->after('smtp_username');
            }
        });
    }

    public function down(): void
    {
        Schema::table('email_settings', function (Blueprint $table): void {
            foreach (['smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password'] as $column) {
                if (Schema::hasColumn('email_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
