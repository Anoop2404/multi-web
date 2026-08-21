<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_rank_point_templates')) {
            Schema::create('fest_rank_point_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('event_id');
                $table->string('name');
                // e.g. ["individual","pair","trio"] — which participant_type values this
                // template governs. A type belongs to at most one template per event,
                // enforced in FestRankPointService::assignTypes(), not here.
                $table->json('participant_types')->default('[]');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
            });
        }

        if (! Schema::hasColumn('fest_rank_points', 'template_id')) {
            Schema::table('fest_rank_points', function (Blueprint $table) {
                $table->unsignedBigInteger('template_id')->nullable()->after('event_id');
                $table->foreign('template_id')->references('id')->on('fest_rank_point_templates')->cascadeOnDelete();
            });
        }

        // Backfill: one template per (event, is_group) bucket that actually has rows,
        // preserving today's real resolution — every pointsForRank() call site today
        // derives is_group via in_array($type, ['group','team'], true), so pair/trio
        // currently draw from the is_group=false ("individual") rows, not a group one.
        if (Schema::hasColumn('fest_rank_points', 'is_group')) {
            $eventIds = DB::table('fest_rank_points')->distinct()->pluck('event_id');

            foreach ($eventIds as $eventId) {
                foreach ([false, true] as $isGroup) {
                    $hasRows = DB::table('fest_rank_points')
                        ->where('event_id', $eventId)
                        ->where('is_group', $isGroup)
                        ->exists();

                    if (! $hasRows) {
                        continue;
                    }

                    $templateId = DB::table('fest_rank_point_templates')->insertGetId([
                        'event_id' => $eventId,
                        'name' => $isGroup ? 'Group / Team' : 'Individual',
                        'participant_types' => json_encode($isGroup ? ['group', 'team'] : ['individual', 'pair', 'trio']),
                        'sort_order' => $isGroup ? 1 : 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('fest_rank_points')
                        ->where('event_id', $eventId)
                        ->where('is_group', $isGroup)
                        ->update(['template_id' => $templateId]);
                }
            }

            // Guards against a stray row outside the loop above (shouldn't happen —
            // every distinct event_id was covered) rather than leaving it orphaned once
            // template_id becomes non-nullable below.
            DB::table('fest_rank_points')->whereNull('template_id')->delete();

            Schema::table('fest_rank_points', function (Blueprint $table) {
                $table->dropUnique(['event_id', 'rank', 'is_group']);
                $table->dropColumn('is_group');
            });
        }

        Schema::table('fest_rank_points', function (Blueprint $table) {
            $table->unsignedBigInteger('template_id')->nullable(false)->change();
            $table->unique(['template_id', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::table('fest_rank_points', function (Blueprint $table) {
            $table->dropUnique(['fest_rank_points_template_id_rank_unique']);
            $table->boolean('is_group')->default(false)->after('event_id');
        });

        // Best-effort restore: re-derive is_group from whichever types the row's
        // template governs (a template split further by hand after the migration
        // can't be perfectly un-split — this restores the pre-migration shape for
        // templates that still match the original Individual/Group-Team backfill).
        DB::table('fest_rank_points as rp')
            ->join('fest_rank_point_templates as t', 't.id', '=', 'rp.template_id')
            ->whereRaw("t.participant_types::text LIKE '%\"group\"%' OR t.participant_types::text LIKE '%\"team\"%'")
            ->update(['rp.is_group' => true]);

        Schema::table('fest_rank_points', function (Blueprint $table) {
            $table->unique(['event_id', 'rank', 'is_group']);
            $table->dropForeign(['template_id']);
            $table->dropColumn('template_id');
        });

        Schema::dropIfExists('fest_rank_point_templates');
    }
};
