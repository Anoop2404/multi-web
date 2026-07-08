<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fest_state_submission_outbox')) {
            return;
        }

        Schema::create('fest_state_submission_outbox', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('state_program_id');
            $table->unsignedBigInteger('source_event_id');
            $table->string('submission_type', 40)->default('qualifier_batch');
            $table->string('idempotency_key', 128)->unique();
            $table->json('payload');
            $table->string('payload_hash', 64)->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->string('state_response_id')->nullable();
            $table->json('state_response')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['state_program_id', 'status']);
            $table->index(['source_event_id', 'submission_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_state_submission_outbox');
    }
};
