<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('authorities', 'email')) {
            Schema::table('authorities', function (Blueprint $table): void {
                $table->string('email')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Intentionally left as a no-op: reverting to NOT NULL could fail
        // when legacy rows contain null emails.
    }
};
