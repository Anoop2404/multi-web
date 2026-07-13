<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FRD-08 Phase 2: Competition Areas (optional subdivisions under a fest event).
 * Distinct from FestItemHead — sports keep Event Heads; custom types use areas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_competition_areas')) {
            Schema::create('fest_competition_areas', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->unsignedBigInteger('event_id');
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->string('name');
                $table->string('slug', 80);
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->date('reg_start')->nullable();
                $table->date('reg_end')->nullable();
                $table->date('competition_start')->nullable();
                $table->date('competition_end')->nullable();
                $table->string('competition_time', 8)->nullable();
                $table->decimal('school_registration_fee', 10, 2)->nullable();
                $table->decimal('student_registration_fee', 10, 2)->nullable();
                $table->decimal('team_registration_fee', 10, 2)->nullable();
                $table->unsignedInteger('included_items_per_student')->nullable();
                $table->unsignedInteger('included_teams')->nullable();
                $table->decimal('default_item_fee', 10, 2)->nullable();
                $table->decimal('extra_item_fee', 10, 2)->nullable();
                $table->string('verification_policy', 40)->nullable();
                $table->string('approval_policy', 40)->nullable();
                $table->unsignedInteger('max_participants')->nullable();
                $table->unsignedInteger('max_teams')->nullable();
                $table->string('venue')->nullable();
                $table->timestamps();

                $table->unique(['event_id', 'slug']);
                $table->index(['tenant_id', 'event_id', 'is_active']);
                $table->index(['parent_id']);
            });
        }

        if (Schema::hasTable('fest_event_items') && ! Schema::hasColumn('fest_event_items', 'area_id')) {
            Schema::table('fest_event_items', function (Blueprint $table) {
                $table->unsignedBigInteger('area_id')->nullable()->after('head_id');
                $table->index('area_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fest_event_items') && Schema::hasColumn('fest_event_items', 'area_id')) {
            Schema::table('fest_event_items', function (Blueprint $table) {
                $table->dropIndex(['area_id']);
                $table->dropColumn('area_id');
            });
        }

        Schema::dropIfExists('fest_competition_areas');
    }
};
