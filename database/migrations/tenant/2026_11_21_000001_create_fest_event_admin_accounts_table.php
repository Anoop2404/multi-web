<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('event_finance_restricted')->default(false);
        });
        Schema::create('fest_event_admin_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained('fest_events')->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->text('temporary_password')->nullable();
            $table->string('password_fingerprint', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_event_admin_accounts');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('event_finance_restricted');
        });
    }
};
