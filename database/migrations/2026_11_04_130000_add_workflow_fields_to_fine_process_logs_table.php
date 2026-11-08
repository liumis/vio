<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fine_process_logs')) {
            return;
        }

        Schema::table('fine_process_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('fine_process_logs', 'vehicle')) {
                $table->string('vehicle', 32)->nullable()->after('authority_name');
            }
            if (! Schema::hasColumn('fine_process_logs', 'reference')) {
                $table->string('reference', 64)->nullable()->after('vehicle');
            }
            if (! Schema::hasColumn('fine_process_logs', 'ticket_date')) {
                $table->date('ticket_date')->nullable()->after('reference');
            }
            if (! Schema::hasColumn('fine_process_logs', 'ticket_time')) {
                $table->string('ticket_time', 8)->nullable()->after('ticket_date');
            }
            if (! Schema::hasColumn('fine_process_logs', 'agr_no')) {
                $table->string('agr_no', 64)->nullable()->after('ticket_time');
            }
            if (! Schema::hasColumn('fine_process_logs', 'rental_id')) {
                $table->unsignedBigInteger('rental_id')->nullable()->after('agr_no');
            }
            if (! Schema::hasColumn('fine_process_logs', 'driver_name')) {
                $table->string('driver_name')->nullable()->after('rental_id');
            }
            if (! Schema::hasColumn('fine_process_logs', 'local_attachment_dir')) {
                $table->string('local_attachment_dir')->nullable()->after('sharepoint_urls');
            }
            if (! Schema::hasColumn('fine_process_logs', 'workflow')) {
                $table->json('workflow')->nullable()->after('local_attachment_dir');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fine_process_logs')) {
            return;
        }

        Schema::table('fine_process_logs', function (Blueprint $table): void {
            foreach ([
                'vehicle',
                'reference',
                'ticket_date',
                'ticket_time',
                'agr_no',
                'rental_id',
                'driver_name',
                'local_attachment_dir',
                'workflow',
            ] as $column) {
                if (Schema::hasColumn('fine_process_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
