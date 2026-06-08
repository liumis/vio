<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('imports', 'birth_date_file_path')) {
            Schema::table('imports', function (Blueprint $table) {
                $table->string('birth_date_file_path')->nullable()->after('file_path');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('imports', 'birth_date_file_path')) {
            Schema::table('imports', function (Blueprint $table) {
                $table->dropColumn('birth_date_file_path');
            });
        }
    }
};
