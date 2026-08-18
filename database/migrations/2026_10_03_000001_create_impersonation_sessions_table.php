<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impersonation_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_platform_user_id');
            $table->unsignedBigInteger('target_user_id');
            $table->string('target_tenant_id');
            $table->foreign('target_tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->text('reason');
            $table->string('consume_token', 64)->nullable()->unique();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['actor_platform_user_id', 'created_at']);
            $table->index(['target_tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonation_sessions');
    }
};
