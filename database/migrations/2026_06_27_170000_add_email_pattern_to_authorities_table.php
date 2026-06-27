<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('authorities', 'email_pattern')) {
            Schema::table('authorities', function (Blueprint $table): void {
                $table->string('email_pattern')->nullable()->after('to_email');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('authorities', 'email_pattern')) {
            Schema::table('authorities', function (Blueprint $table): void {
                $table->dropColumn('email_pattern');
            });
        }
    }
};
