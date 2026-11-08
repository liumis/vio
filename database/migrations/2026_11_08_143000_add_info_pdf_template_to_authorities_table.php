<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authorities', function (Blueprint $table) {
            if (! Schema::hasColumn('authorities', 'info_pdf_template')) {
                $table->string('info_pdf_template', 32)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('authorities', function (Blueprint $table) {
            if (Schema::hasColumn('authorities', 'info_pdf_template')) {
                $table->dropColumn('info_pdf_template');
            }
        });
    }
};
