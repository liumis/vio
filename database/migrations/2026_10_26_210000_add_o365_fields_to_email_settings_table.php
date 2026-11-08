<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('email_settings', 'o365_enabled')) {
                $table->boolean('o365_enabled')->default(false)->after('subject');
            }
            if (! Schema::hasColumn('email_settings', 'o365_tenant_id')) {
                $table->string('o365_tenant_id')->nullable()->after('o365_enabled');
            }
            if (! Schema::hasColumn('email_settings', 'o365_client_id')) {
                $table->string('o365_client_id')->nullable()->after('o365_tenant_id');
            }
            if (! Schema::hasColumn('email_settings', 'o365_client_secret')) {
                $table->text('o365_client_secret')->nullable()->after('o365_client_id');
            }
            if (! Schema::hasColumn('email_settings', 'inbox_mailbox')) {
                $table->string('inbox_mailbox')->nullable()->after('o365_client_secret');
            }
        });
    }

    public function down(): void
    {
        Schema::table('email_settings', function (Blueprint $table): void {
            foreach (['o365_enabled', 'o365_tenant_id', 'o365_client_id', 'o365_client_secret', 'inbox_mailbox'] as $column) {
                if (Schema::hasColumn('email_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
