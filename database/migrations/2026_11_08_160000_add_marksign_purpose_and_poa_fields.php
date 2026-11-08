<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fine_process_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('fine_process_logs', 'marksign_purpose')) {
                $table->string('marksign_purpose', 64)->nullable()->after('marksign_uuid');
            }
        });

        Schema::table('email_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('email_settings', 'power_of_attorney_pdf_path')) {
                $table->string('power_of_attorney_pdf_path', 500)->nullable();
            }
            if (! Schema::hasColumn('email_settings', 'power_of_attorney_signed_path')) {
                $table->string('power_of_attorney_signed_path', 500)->nullable();
            }
            if (! Schema::hasColumn('email_settings', 'power_of_attorney_valid_until')) {
                $table->date('power_of_attorney_valid_until')->nullable();
            }
            if (! Schema::hasColumn('email_settings', 'power_of_attorney_marksign_uuid')) {
                $table->string('power_of_attorney_marksign_uuid', 120)->nullable();
            }
            if (! Schema::hasColumn('email_settings', 'power_of_attorney_signing_url')) {
                $table->string('power_of_attorney_signing_url', 1000)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('fine_process_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('fine_process_logs', 'marksign_purpose')) {
                $table->dropColumn('marksign_purpose');
            }
        });

        Schema::table('email_settings', function (Blueprint $table): void {
            foreach ([
                'power_of_attorney_pdf_path',
                'power_of_attorney_signed_path',
                'power_of_attorney_valid_until',
                'power_of_attorney_marksign_uuid',
                'power_of_attorney_signing_url',
            ] as $column) {
                if (Schema::hasColumn('email_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
