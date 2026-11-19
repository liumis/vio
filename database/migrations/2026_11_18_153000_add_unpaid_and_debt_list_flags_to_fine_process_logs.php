<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unpaid menu: track payment + manual debtors-list selection (no auto LT debt write).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fine_process_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('fine_process_logs', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('chargeback_marked_by');
            }
            if (! Schema::hasColumn('fine_process_logs', 'paid_marked_by')) {
                $table->foreignId('paid_marked_by')->nullable()->after('paid_at')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('fine_process_logs', 'debt_list_marked_at')) {
                $table->timestamp('debt_list_marked_at')->nullable()->after('paid_marked_by');
            }
            if (! Schema::hasColumn('fine_process_logs', 'debt_list_marked_by')) {
                $table->foreignId('debt_list_marked_by')->nullable()->after('debt_list_marked_at')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('fine_process_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('fine_process_logs', 'debt_list_marked_by')) {
                $table->dropConstrainedForeignId('debt_list_marked_by');
            }
            if (Schema::hasColumn('fine_process_logs', 'debt_list_marked_at')) {
                $table->dropColumn('debt_list_marked_at');
            }
            if (Schema::hasColumn('fine_process_logs', 'paid_marked_by')) {
                $table->dropConstrainedForeignId('paid_marked_by');
            }
            if (Schema::hasColumn('fine_process_logs', 'paid_at')) {
                $table->dropColumn('paid_at');
            }
        });
    }
};
