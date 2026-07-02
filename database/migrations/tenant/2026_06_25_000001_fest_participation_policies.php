<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fest_participation_policies', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->enum('scope', ['sahodaya_default', 'event'])->default('event');
            $table->unsignedBigInteger('event_id')->nullable();
            $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
            $table->enum('level_round', ['state', 'sahodaya', 'school'])->default('sahodaya');
            $table->string('class_group', 20)->nullable();
            $table->string('preset_key', 60)->nullable();
            $table->unsignedSmallInteger('max_onstage_per_school')->nullable();
            $table->unsignedSmallInteger('max_offstage_per_school')->nullable();
            $table->unsignedSmallInteger('max_group_per_school')->nullable();
            $table->unsignedSmallInteger('max_onstage_per_student')->nullable();
            $table->unsignedSmallInteger('max_offstage_per_student')->nullable();
            $table->unsignedSmallInteger('max_group_per_student')->nullable();
            $table->unsignedSmallInteger('max_total_per_student')->nullable();
            $table->boolean('one_entry_per_item_per_school')->default(true);
            $table->boolean('count_submitted_registrations')->default(true);
            $table->boolean('exclude_standbys_from_limits')->default(true);
            $table->boolean('require_fee_before_approval')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['event_id', 'class_group'], 'fest_part_policy_event_class_unique');
            $table->index(['tenant_id', 'scope', 'level_round']);
        });

        if (Schema::hasTable('fest_participation_rules')) {
            $rules = DB::table('fest_participation_rules')->get();
            foreach ($rules as $rule) {
                DB::table('fest_participation_policies')->insert([
                    'scope' => 'event',
                    'event_id' => $rule->event_id,
                    'level_round' => 'sahodaya',
                    'class_group' => $rule->class_group,
                    'max_onstage_per_school' => $rule->max_onstage,
                    'max_offstage_per_school' => $rule->max_offstage,
                    'max_group_per_school' => $rule->max_group_events,
                    'max_total_per_student' => $rule->max_events_per_student,
                    'one_entry_per_item_per_school' => true,
                    'count_submitted_registrations' => true,
                    'is_active' => (bool) $rule->is_active,
                    'created_at' => $rule->created_at,
                    'updated_at' => $rule->updated_at,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_participation_policies');
    }
};
