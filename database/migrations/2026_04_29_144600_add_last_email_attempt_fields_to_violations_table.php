<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('violations', function (Blueprint $table): void {
            if (! Schema::hasColumn('violations', 'last_email_subject')) {
                $table->string('last_email_subject')->nullable()->after('send_error');
            }

            if (! Schema::hasColumn('violations', 'last_email_body')) {
                $table->longText('last_email_body')->nullable()->after('last_email_subject');
            }

            if (! Schema::hasColumn('violations', 'last_email_to')) {
                $table->string('last_email_to')->nullable()->after('last_email_body');
            }

            if (! Schema::hasColumn('violations', 'last_email_from')) {
                $table->string('last_email_from')->nullable()->after('last_email_to');
            }

            if (! Schema::hasColumn('violations', 'last_email_reply_to')) {
                $table->string('last_email_reply_to')->nullable()->after('last_email_from');
            }

            if (! Schema::hasColumn('violations', 'last_email_status')) {
                $table->string('last_email_status')->nullable()->after('last_email_reply_to');
            }

            if (! Schema::hasColumn('violations', 'last_email_error')) {
                $table->text('last_email_error')->nullable()->after('last_email_status');
            }

            if (! Schema::hasColumn('violations', 'last_email_attempted_at')) {
                $table->timestamp('last_email_attempted_at')->nullable()->after('last_email_error');
            }
        });
    }

    public function down(): void
    {
        Schema::table('violations', function (Blueprint $table): void {
            $columns = [
                'last_email_subject',
                'last_email_body',
                'last_email_to',
                'last_email_from',
                'last_email_reply_to',
                'last_email_status',
                'last_email_error',
                'last_email_attempted_at',
            ];

            $existingColumns = array_values(array_filter(
                $columns,
                fn (string $column): bool => Schema::hasColumn('violations', $column)
            ));

            if ($existingColumns !== []) {
                $table->dropColumn($existingColumns);
            }
        });
    }
};
