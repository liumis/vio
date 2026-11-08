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
            if (! Schema::hasColumn('fine_process_logs', 'wheels_violation_id')) {
                $table->string('wheels_violation_id', 64)->nullable()->after('wheels_imported_by');
            }
            if (! Schema::hasColumn('fine_process_logs', 'wheels_verify_detail')) {
                $table->string('wheels_verify_detail', 500)->nullable()->after('wheels_violation_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fine_process_logs')) {
            return;
        }

        Schema::table('fine_process_logs', function (Blueprint $table): void {
            foreach (['wheels_verify_detail', 'wheels_violation_id'] as $column) {
                if (Schema::hasColumn('fine_process_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
