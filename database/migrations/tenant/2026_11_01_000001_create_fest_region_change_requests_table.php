<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_region_change_requests')) {
            Schema::create('fest_region_change_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('event_id');
                $table->unsignedBigInteger('phase_id');
                $table->uuid('school_id');
                $table->unsignedBigInteger('current_region_id')->nullable();
                $table->unsignedBigInteger('requested_region_id');
                $table->text('reason');
                $table->string('status', 20)->default('pending');
                $table->text('resolution_note')->nullable();
                $table->unsignedBigInteger('requested_by_user_id')->nullable();
                $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['event_id', 'phase_id', 'school_id', 'status'], 'fest_region_change_req_lookup');
                $table->index(['event_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_region_change_requests');
    }
};
