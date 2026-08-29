<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sahodaya_id')->nullable()->index();
            $table->string('recipient_email')->index();
            $table->string('recipient_name')->nullable();
            $table->string('subject');
            $table->string('mail_type')->default('view');
            $table->string('mail_view')->nullable();
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->string('status')->default('pending')->index();
            $table->integer('attempts')->default(1);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_email_logs');
    }
};
