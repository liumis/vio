<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('violations', function (Blueprint $table): void {
            $table->string('birth_date')->nullable()->after('customer_email');
            $table->string('driver_telephone')->nullable()->after('birth_date');
            $table->string('licence_issue_date')->nullable()->after('driver_telephone');
            $table->string('customer_paid')->nullable()->after('licence_issue_date');
            $table->string('licence_issue_place')->nullable()->after('customer_paid');
        });
    }

    public function down(): void
    {
        Schema::table('violations', function (Blueprint $table): void {
            $table->dropColumn([
                'birth_date',
                'driver_telephone',
                'licence_issue_date',
                'customer_paid',
                'licence_issue_place',
            ]);
        });
    }
};
