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
            if (! Schema::hasColumn('fine_process_logs', 'wheels_export_file_name')) {
                $table->string('wheels_export_file_name', 255)->nullable()->after('wheels_export_batch');
            }
        });

        // Backfill from known batch ids.
        if (Schema::hasColumn('fine_process_logs', 'wheels_export_file_name')) {
            DB::table('fine_process_logs')
                ->whereNotNull('wheels_export_batch')
                ->where('wheels_export_batch', '!=', '')
                ->where(function ($q): void {
                    $q->whereNull('wheels_export_file_name')
                        ->orWhere('wheels_export_file_name', '');
                })
                ->orderBy('id')
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table('fine_process_logs')
                            ->where('id', $row->id)
                            ->update([
                                'wheels_export_file_name' => 'wheels-export-'.$row->wheels_export_batch.'.xlsx',
                            ]);
                    }
                });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fine_process_logs')) {
            return;
        }

        Schema::table('fine_process_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('fine_process_logs', 'wheels_export_file_name')) {
                $table->dropColumn('wheels_export_file_name');
            }
        });
    }
};
