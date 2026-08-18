<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FRD-13 §9 maintenance mode + announcements, sharing one table. `artisan down` was
 * ruled out deliberately — it would take down public tenant sites too. A "maintenance"
 * notice is just an announcement with type=maintenance: informational only, shown via
 * the same HandleInertiaRequests shared-prop mechanism as the impersonation banner,
 * not an actual request-blocking mechanism.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->enum('type', ['info', 'warning', 'critical', 'maintenance'])->default('info');
            $table->enum('audience', ['all', 'superadmin', 'state_admin', 'sahodaya_admin', 'school_admin'])->default('all');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_announcements');
    }
};
