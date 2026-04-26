<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('violations', 'driver_city')) {
            Schema::table('violations', function (Blueprint $table): void {
                $table->string('driver_city')->nullable()->after('agent');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('violations', 'driver_city')) {
            Schema::table('violations', function (Blueprint $table): void {
                $table->dropColumn('driver_city');
            });
        }
    }
};
