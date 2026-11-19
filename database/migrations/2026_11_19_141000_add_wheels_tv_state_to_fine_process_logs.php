<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fine_process_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('fine_process_logs', 'wheels_tv_state')) {
                $table->string('wheels_tv_state', 32)->nullable()->after('wheels_verify_detail');
            }
            if (! Schema::hasColumn('fine_process_logs', 'wheels_paid_date')) {
                $table->date('wheels_paid_date')->nullable()->after('wheels_tv_state');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fine_process_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('fine_process_logs', 'wheels_paid_date')) {
                $table->dropColumn('wheels_paid_date');
            }
            if (Schema::hasColumn('fine_process_logs', 'wheels_tv_state')) {
                $table->dropColumn('wheels_tv_state');
            }
        });
    }
};
