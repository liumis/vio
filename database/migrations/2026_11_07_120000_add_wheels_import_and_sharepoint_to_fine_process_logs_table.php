<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fine_process_logs')) {
            return;
        }

        Schema::table('fine_process_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('fine_process_logs', 'wheels_export_sharepoint_url')) {
                $table->string('wheels_export_sharepoint_url', 2000)->nullable()->after('wheels_exported_by');
            }
            if (! Schema::hasColumn('fine_process_logs', 'wheels_import_status')) {
                // awaiting = Excel generated, waiting for Wheels Import Data; imported = human confirmed
                $table->string('wheels_import_status', 32)->nullable()->after('wheels_export_sharepoint_url');
            }
            if (! Schema::hasColumn('fine_process_logs', 'wheels_imported_at')) {
                $table->timestamp('wheels_imported_at')->nullable()->after('wheels_import_status');
            }
            if (! Schema::hasColumn('fine_process_logs', 'wheels_imported_by')) {
                $table->foreignId('wheels_imported_by')->nullable()->after('wheels_imported_at')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fine_process_logs')) {
            return;
        }

        Schema::table('fine_process_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('fine_process_logs', 'wheels_imported_by')) {
                $table->dropConstrainedForeignId('wheels_imported_by');
            }
            foreach (['wheels_imported_at', 'wheels_import_status', 'wheels_export_sharepoint_url'] as $column) {
                if (Schema::hasColumn('fine_process_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
