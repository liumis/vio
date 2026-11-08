<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fine_process_logs')) {
            return;
        }

        Schema::create('fine_process_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('graph_message_id', 512)->unique();
            $table->string('mailbox')->nullable();
            $table->string('from_email')->nullable();
            $table->string('subject')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('authority_id')->nullable()->constrained('authorities')->nullOnDelete();
            $table->string('authority_name')->nullable();
            $table->string('status')->default('processed');
            $table->unsignedInteger('attachments_uploaded')->default(0);
            $table->json('sharepoint_urls')->nullable();
            $table->string('email_to')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->boolean('moved_to_processed')->default(false);
            $table->string('processed_folder')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fine_process_logs');
    }
};
