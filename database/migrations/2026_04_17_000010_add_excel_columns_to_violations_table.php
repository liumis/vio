<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('violations', function (Blueprint $table) {
            $table->string('station')->nullable()->after('row_number');
            $table->string('vehicle')->nullable()->after('station');
            $table->string('total_charge')->nullable()->after('vehicle');
            $table->string('issuing_authority')->nullable()->after('total_charge');
            $table->string('ticket_date')->nullable()->after('issuing_authority');
            $table->string('ticket_number')->nullable()->after('ticket_date');
            $table->string('entry_date')->nullable()->after('ticket_number');
            $table->string('agr_no')->nullable()->after('entry_date');
            $table->string('issued_by')->nullable()->after('agr_no');
            $table->text('remarks')->nullable()->after('issued_by');
            $table->string('driver_code')->nullable()->after('remarks');
            $table->string('driver')->nullable()->after('driver_code');
            $table->string('corporate_code')->nullable()->after('driver');
            $table->string('corporate')->nullable()->after('corporate_code');
            $table->string('tax_id')->nullable()->after('corporate');
            $table->string('tax_office')->nullable()->after('tax_id');
            $table->string('violation_type')->nullable()->after('tax_office');
            $table->string('hearing_date')->nullable()->after('violation_type');
            $table->text('address')->nullable()->after('hearing_date');
            $table->string('city')->nullable()->after('address');
            $table->string('zip_code')->nullable()->after('city');
            $table->string('driver_license')->nullable()->after('zip_code');
            $table->text('customer_address')->nullable()->after('driver_license');
            $table->string('customer_city')->nullable()->after('customer_address');
            $table->string('customer_zip_code')->nullable()->after('customer_city');
            $table->string('customer_country')->nullable()->after('customer_zip_code');
            $table->string('payment_date')->nullable()->after('customer_country');
            $table->string('invoice')->nullable()->after('payment_date');
            $table->string('fine_received_date')->nullable()->after('invoice');
            $table->string('invoice_date')->nullable()->after('fine_received_date');
            $table->string('driver_tax_id')->nullable()->after('invoice_date');
            $table->string('driver_id_number')->nullable()->after('driver_tax_id');
            $table->text('driver_address')->nullable()->after('driver_id_number');
            $table->string('driver_city')->nullable()->after('driver_address');
            $table->string('driver_zip_code')->nullable()->after('driver_city');
            $table->string('driver_country')->nullable()->after('driver_zip_code');
            $table->string('driver_state')->nullable()->after('driver_country');
            $table->string('check_out')->nullable()->after('driver_state');
            $table->string('check_in')->nullable()->after('check_out');
            $table->string('check_out_time')->nullable()->after('check_in');
            $table->string('check_in_time')->nullable()->after('check_out_time');
            $table->string('traffic_charge')->nullable()->after('check_in_time');
            $table->string('admin_fee')->nullable()->after('traffic_charge');
            $table->string('contract_start_date')->nullable()->after('admin_fee');
            $table->string('customer_email')->nullable()->after('contract_start_date');
        });
    }

    public function down(): void
    {
        Schema::table('violations', function (Blueprint $table) {
            $table->dropColumn([
                'station',
                'vehicle',
                'total_charge',
                'issuing_authority',
                'ticket_date',
                'ticket_number',
                'entry_date',
                'agr_no',
                'issued_by',
                'remarks',
                'driver_code',
                'driver',
                'corporate_code',
                'corporate',
                'tax_id',
                'tax_office',
                'violation_type',
                'hearing_date',
                'address',
                'city',
                'zip_code',
                'driver_license',
                'customer_address',
                'customer_city',
                'customer_zip_code',
                'customer_country',
                'payment_date',
                'invoice',
                'fine_received_date',
                'invoice_date',
                'driver_tax_id',
                'driver_id_number',
                'driver_address',
                'driver_city',
                'driver_zip_code',
                'driver_country',
                'driver_state',
                'check_out',
                'check_in',
                'check_out_time',
                'check_in_time',
                'traffic_charge',
                'admin_fee',
                'contract_start_date',
                'customer_email',
            ]);
        });
    }
};
