<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('email_settings', 'debt_tracker_url')) {
                $table->string('debt_tracker_url', 1000)->nullable();
            }
            if (! Schema::hasColumn('email_settings', 'debt_tracker_google_url')) {
                $table->string('debt_tracker_google_url', 1000)->nullable();
            }
            if (! Schema::hasColumn('email_settings', 'debt_tracker_sheet')) {
                $table->string('debt_tracker_sheet', 64)->nullable();
            }
            if (! Schema::hasColumn('email_settings', 'debt_tracker_file_name')) {
                $table->string('debt_tracker_file_name', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('email_settings', function (Blueprint $table) {
            foreach (['debt_tracker_url', 'debt_tracker_google_url', 'debt_tracker_sheet', 'debt_tracker_file_name'] as $column) {
                if (Schema::hasColumn('email_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
