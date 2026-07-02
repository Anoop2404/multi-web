<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_stages')) {
            Schema::create('fest_stages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('event_id');
                $table->unsignedBigInteger('venue_id')->nullable();
                $table->string('name');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
                $table->foreign('venue_id')->references('id')->on('fest_venues')->nullOnDelete();
                $table->unique(['event_id', 'name']);
            });
        }

        if (Schema::hasTable('fest_schedules') && ! Schema::hasColumn('fest_schedules', 'stage_id')) {
            Schema::table('fest_schedules', function (Blueprint $table) {
                $table->unsignedBigInteger('stage_id')->nullable()->after('stage');
                $table->foreign('stage_id')->references('id')->on('fest_stages')->nullOnDelete();
            });
        }

        if (Schema::hasTable('fest_event_staff')) {
            Schema::table('fest_event_staff', function (Blueprint $table) {
                if (! Schema::hasColumn('fest_event_staff', 'stage_id')) {
                    $table->unsignedBigInteger('stage_id')->nullable()->after('duty');
                }
                if (! Schema::hasColumn('fest_event_staff', 'venue_id')) {
                    $table->unsignedBigInteger('venue_id')->nullable()->after('stage_id');
                }
            });

            if (Schema::hasColumn('fest_event_staff', 'stage_id')) {
                Schema::table('fest_event_staff', function (Blueprint $table) {
                    $table->foreign('stage_id')->references('id')->on('fest_stages')->nullOnDelete();
                });
            }

            if (Schema::hasColumn('fest_event_staff', 'venue_id')) {
                Schema::table('fest_event_staff', function (Blueprint $table) {
                    $table->foreign('venue_id')->references('id')->on('fest_venues')->nullOnDelete();
                });
            }

            try {
                Schema::table('fest_event_staff', function (Blueprint $table) {
                    $table->dropUnique(['event_id', 'user_id', 'duty']);
                });
            } catch (\Throwable) {
                // already migrated
            }

            try {
                Schema::table('fest_event_staff', function (Blueprint $table) {
                    $table->unique(['event_id', 'user_id', 'duty', 'stage_id'], 'fest_event_staff_assignment_unique');
                });
            } catch (\Throwable) {
                // already migrated
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fest_event_staff')) {
            Schema::table('fest_event_staff', function (Blueprint $table) {
                $table->dropUnique('fest_event_staff_assignment_unique');
                $table->unique(['event_id', 'user_id', 'duty']);
            });

            Schema::table('fest_event_staff', function (Blueprint $table) {
                if (Schema::hasColumn('fest_event_staff', 'venue_id')) {
                    $table->dropConstrainedForeignId('venue_id');
                }
                if (Schema::hasColumn('fest_event_staff', 'stage_id')) {
                    $table->dropConstrainedForeignId('stage_id');
                }
            });
        }

        if (Schema::hasTable('fest_schedules') && Schema::hasColumn('fest_schedules', 'stage_id')) {
            Schema::table('fest_schedules', function (Blueprint $table) {
                $table->dropConstrainedForeignId('stage_id');
            });
        }

        Schema::dropIfExists('fest_stages');
    }
};
