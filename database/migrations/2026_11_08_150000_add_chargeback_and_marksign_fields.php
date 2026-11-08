<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fine_process_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('fine_process_logs', 'chargeback_status')) {
                $table->string('chargeback_status', 40)->nullable()->index();
            }
            if (! Schema::hasColumn('fine_process_logs', 'chargeback_data')) {
                $table->json('chargeback_data')->nullable();
            }
            if (! Schema::hasColumn('fine_process_logs', 'chargeback_pdf_path')) {
                $table->string('chargeback_pdf_path')->nullable();
            }
            if (! Schema::hasColumn('fine_process_logs', 'marksign_uuid')) {
                $table->string('marksign_uuid', 64)->nullable()->index();
            }
            if (! Schema::hasColumn('fine_process_logs', 'marksign_signing_url')) {
                $table->text('marksign_signing_url')->nullable();
            }
            if (! Schema::hasColumn('fine_process_logs', 'marksign_signed_pdf_path')) {
                $table->string('marksign_signed_pdf_path')->nullable();
            }
            if (! Schema::hasColumn('fine_process_logs', 'chargeback_marked_at')) {
                $table->timestamp('chargeback_marked_at')->nullable();
            }
            if (! Schema::hasColumn('fine_process_logs', 'chargeback_marked_by')) {
                $table->foreignId('chargeback_marked_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('email_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('email_settings', 'marksign_token')) {
                $table->text('marksign_token')->nullable();
            }
            if (! Schema::hasColumn('email_settings', 'marksign_signer_email')) {
                $table->string('marksign_signer_email')->nullable();
            }
            if (! Schema::hasColumn('email_settings', 'marksign_signer_name')) {
                $table->string('marksign_signer_name')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('fine_process_logs', function (Blueprint $table) {
            foreach ([
                'chargeback_marked_by',
                'chargeback_marked_at',
                'marksign_signed_pdf_path',
                'marksign_signing_url',
                'marksign_uuid',
                'chargeback_pdf_path',
                'chargeback_data',
                'chargeback_status',
            ] as $column) {
                if (Schema::hasColumn('fine_process_logs', $column)) {
                    if ($column === 'chargeback_marked_by') {
                        $table->dropConstrainedForeignId('chargeback_marked_by');
                    } else {
                        $table->dropColumn($column);
                    }
                }
            }
        });

        Schema::table('email_settings', function (Blueprint $table) {
            foreach (['marksign_token', 'marksign_signer_email', 'marksign_signer_name'] as $column) {
                if (Schema::hasColumn('email_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
