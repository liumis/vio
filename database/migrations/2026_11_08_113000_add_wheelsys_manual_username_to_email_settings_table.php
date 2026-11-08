<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_settings')) {
            return;
        }

        Schema::table('email_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('email_settings', 'wheelsys_manual_username')) {
                $table->string('wheelsys_manual_username')->nullable()->after('wheelsys_password');
            }
        });

        // Temporary reminder: current interactive login is Liudas.
        if (Schema::hasColumn('email_settings', 'wheelsys_manual_username')) {
            DB::table('email_settings')
                ->where('id', 1)
                ->where(function ($q): void {
                    $q->whereNull('wheelsys_manual_username')
                        ->orWhere('wheelsys_manual_username', '');
                })
                ->update(['wheelsys_manual_username' => 'Liudas']);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('email_settings')) {
            return;
        }

        Schema::table('email_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('email_settings', 'wheelsys_manual_username')) {
                $table->dropColumn('wheelsys_manual_username');
            }
        });
    }
};
