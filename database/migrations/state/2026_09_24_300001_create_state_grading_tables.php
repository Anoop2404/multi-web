<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grade bands, point rules and class categories for a State event.
 *
 * Until now State scoring read `scoring_preset` and nothing else, so an event with no preset — which
 * is every event created through the module, since nothing sets one — scored every mark at zero
 * points and derived no grade from any score. These tables make the rules the State's own, per event,
 * seeded from the same Kalotsavam manual tables the Sahodaya side loads
 * (config/fest_default_kalotsav_grading.php and config/fest_confed_kalotsav_scoring.php, whose own
 * docblock says the manual applies at every level).
 *
 * Stored per event rather than read from config at scoring time, for the reason the tenant side
 * already learned: a manual is revised between seasons, and results computed last year must keep
 * computing the same way. Config is the seed; the event's rows are the truth.
 */
return new class extends Migration
{
    public function getConnection(): string
    {
        return 'state';
    }

    public function up(): void
    {
        Schema::connection('state')->create('state_grade_bands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // state_fest_events.id is a bigint, not a uuid.
            $table->unsignedBigInteger('state_event_id');
            $table->uuid('state_id')->nullable()->index();

            // Null item_id is the event-wide band. An item with its own bands overrides them
            // entirely — a half-override would silently mix two scales.
            $table->uuid('item_id')->nullable();

            $table->string('grade', 20);
            $table->decimal('min_score', 6, 2);
            $table->decimal('max_score', 6, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['state_event_id', 'item_id', 'grade']);
            $table->index(['state_event_id', 'item_id']);
        });

        Schema::connection('state')->create('state_point_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('state_event_id');
            $table->uuid('state_id')->nullable()->index();

            // Null grade or position means "any", so a table can say "3rd place, any grade" without a
            // row per grade. A more specific rule wins — see StateGradePointService.
            $table->string('grade', 20)->nullable();
            $table->unsignedSmallInteger('position')->nullable();
            $table->boolean('is_group')->default(false);
            $table->integer('points');
            $table->timestamps();

            $table->unique(['state_event_id', 'grade', 'position', 'is_group'], 'state_point_rules_unique');
        });

        Schema::connection('state')->create('state_class_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('state_event_id');
            $table->uuid('state_id')->nullable()->index();

            // Matches FestStateProgramItem.class_group — category_1..category_5 as the State
            // Kalotsav items are already seeded.
            $table->string('code', 40);
            $table->string('label');
            // Null bounds mean the category does not restrict class at all (an open group category).
            $table->unsignedSmallInteger('min_class')->nullable();
            $table->unsignedSmallInteger('max_class')->nullable();
            // A category for group items only, where the participants' own classes vary.
            $table->boolean('is_open')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['state_event_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::connection('state')->dropIfExists('state_class_categories');
        Schema::connection('state')->dropIfExists('state_point_rules');
        Schema::connection('state')->dropIfExists('state_grade_bands');
    }
};
