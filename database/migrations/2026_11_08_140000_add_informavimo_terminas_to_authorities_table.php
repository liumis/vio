<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authorities', function (Blueprint $table) {
            if (! Schema::hasColumn('authorities', 'informavimo_terminas')) {
                $table->unsignedSmallInteger('informavimo_terminas')->nullable();
            }
            if (! Schema::hasColumn('authorities', 'informavimo_terminas_tipas')) {
                $table->string('informavimo_terminas_tipas', 16)->default('working');
            }
        });
    }

    public function down(): void
    {
        Schema::table('authorities', function (Blueprint $table) {
            if (Schema::hasColumn('authorities', 'informavimo_terminas_tipas')) {
                $table->dropColumn('informavimo_terminas_tipas');
            }
            if (Schema::hasColumn('authorities', 'informavimo_terminas')) {
                $table->dropColumn('informavimo_terminas');
            }
        });
    }
};
