<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('violations', 'send_error')) {
            Schema::table('violations', function (Blueprint $table): void {
                $table->text('send_error')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('violations', 'send_error')) {
            Schema::table('violations', function (Blueprint $table): void {
                $table->dropColumn('send_error');
            });
        }
    }
};
