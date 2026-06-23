<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circular_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('circular_id');
            $table->foreign('circular_id')->references('id')->on('circulars')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('school_id')->nullable();
            $table->timestamp('acknowledged_at');
            $table->timestamps();

            $table->unique(['circular_id', 'user_id']);
        });

        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('body_template');
            $table->json('channels_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('in_app_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->text('body');
            $table->string('action_url')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });

        Schema::create('user_fcm_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('token');
            $table->string('device_type')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_fcm_tokens');
        Schema::dropIfExists('in_app_notifications');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('circular_acknowledgements');
    }
};
