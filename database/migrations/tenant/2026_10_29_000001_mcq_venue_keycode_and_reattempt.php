<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mcq_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('mcq_registrations', 'venue_keycode')) {
                $table->string('venue_keycode', 16)->nullable()->unique();
            }
            if (! Schema::hasColumn('mcq_registrations', 'attempt_number')) {
                $table->unsignedSmallInteger('attempt_number')->default(1);
            }
            if (! Schema::hasColumn('mcq_registrations', 'attempt_history')) {
                $table->json('attempt_history')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('mcq_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('mcq_registrations', 'venue_keycode')) {
                $table->dropUnique(['venue_keycode']);
                $table->dropColumn('venue_keycode');
            }
            if (Schema::hasColumn('mcq_registrations', 'attempt_number')) {
                $table->dropColumn('attempt_number');
            }
            if (Schema::hasColumn('mcq_registrations', 'attempt_history')) {
                $table->dropColumn('attempt_history');
            }
        });
    }
};
