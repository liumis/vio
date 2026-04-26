<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authorities', function (Blueprint $table): void {
            $table->string('from_email')->nullable()->after('name');
            $table->string('main_email')->nullable()->after('from_email');
            $table->string('to_email')->nullable()->after('main_email');
            $table->text('mail_template')->nullable()->after('to_email');
        });

        DB::table('authorities')->update([
            'from_email' => DB::raw('email'),
            'main_email' => DB::raw('email'),
            'to_email' => DB::raw('email'),
        ]);
    }

    public function down(): void
    {
        Schema::table('authorities', function (Blueprint $table): void {
            $table->dropColumn(['from_email', 'main_email', 'to_email', 'mail_template']);
        });
    }
};
