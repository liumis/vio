<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('violations', 'licence_issue_place')) {
            Schema::table('violations', function (Blueprint $table): void {
                $table->string('licence_issue_place')->nullable()->after('customer_paid');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('violations', 'licence_issue_place')) {
            Schema::table('violations', function (Blueprint $table): void {
                $table->dropColumn('licence_issue_place');
            });
        }
    }
};
