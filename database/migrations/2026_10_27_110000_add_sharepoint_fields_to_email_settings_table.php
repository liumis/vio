<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('email_settings', 'sharepoint_site_url')) {
                $table->string('sharepoint_site_url')->nullable()->after('smtp_password');
            }
            if (! Schema::hasColumn('email_settings', 'sharepoint_folder_path')) {
                $table->string('sharepoint_folder_path')->nullable()->after('sharepoint_site_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('email_settings', function (Blueprint $table): void {
            foreach (['sharepoint_site_url', 'sharepoint_folder_path'] as $column) {
                if (Schema::hasColumn('email_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
