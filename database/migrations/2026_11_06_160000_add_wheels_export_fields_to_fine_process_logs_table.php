<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fine_process_logs')) {
            return;
        }

        Schema::table('fine_process_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('fine_process_logs', 'wheels_export_status')) {
                // pending = ready for Excel; exported = included in a batch; skipped = not for Wheels
                $table->string('wheels_export_status', 32)->nullable()->after('workflow');
            }
            if (! Schema::hasColumn('fine_process_logs', 'wheels_export_batch')) {
                $table->string('wheels_export_batch', 64)->nullable()->after('wheels_export_status');
            }
            if (! Schema::hasColumn('fine_process_logs', 'wheels_exported_at')) {
                $table->timestamp('wheels_exported_at')->nullable()->after('wheels_export_batch');
            }
            if (! Schema::hasColumn('fine_process_logs', 'wheels_exported_by')) {
                $table->foreignId('wheels_exported_by')->nullable()->after('wheels_exported_at')
                    ->constrained('users')->nullOnDelete();
            }
        });

        // Backfill: already-processed fines with a plate become pending Excel rows.
        if (Schema::hasColumn('fine_process_logs', 'wheels_export_status')) {
            DB::table('fine_process_logs')
                ->whereNull('wheels_export_status')
                ->whereNotNull('vehicle')
                ->where('vehicle', '!=', '')
                ->update(['wheels_export_status' => 'pending']);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fine_process_logs')) {
            return;
        }

        Schema::table('fine_process_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('fine_process_logs', 'wheels_exported_by')) {
                $table->dropConstrainedForeignId('wheels_exported_by');
            }
            foreach (['wheels_exported_at', 'wheels_export_batch', 'wheels_export_status'] as $column) {
                if (Schema::hasColumn('fine_process_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
