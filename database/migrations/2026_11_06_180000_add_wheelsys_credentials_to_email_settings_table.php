<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_settings')) {
            return;
        }

        Schema::table('email_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('email_settings', 'wheelsys_username')) {
                $table->string('wheelsys_username')->nullable()->after('sharepoint_folder_path');
            }
            if (! Schema::hasColumn('email_settings', 'wheelsys_password')) {
                $table->text('wheelsys_password')->nullable()->after('wheelsys_username');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('email_settings')) {
            return;
        }

        Schema::table('email_settings', function (Blueprint $table): void {
            foreach (['wheelsys_password', 'wheelsys_username'] as $column) {
                if (Schema::hasColumn('email_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
