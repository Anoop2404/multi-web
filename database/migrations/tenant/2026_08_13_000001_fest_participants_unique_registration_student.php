<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * STATE_SAHODAYA_RULE_BOUNDARY_FIX_PLAN — Set 2, Item 8
 *
 * Add a UNIQUE index on fest_participants (registration_id, student_id),
 * preventing duplicate participant rows for the same (registration, student) pair.
 *
 * Duplicate rows cause FestStateNominationService::candidatePool() to
 * double-count students in the State-nomination pool, corrupting selections.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_participants')) {
            return;
        }

        // Deduplicate: keep earliest inserted row per (registration_id, student_id)
        $duplicateGroups = DB::table('fest_participants')
            ->select('registration_id', 'student_id')
            ->whereNotNull('student_id')
            ->groupBy('registration_id', 'student_id')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicateGroups as $group) {
            $duplicateIds = DB::table('fest_participants')
                ->where('registration_id', $group->registration_id)
                ->where('student_id', $group->student_id)
                ->orderBy('id')
                ->pluck('id')
                ->slice(1);

            if ($duplicateIds->isNotEmpty()) {
                DB::table('fest_participants')
                    ->whereIn('id', $duplicateIds)
                    ->delete();
            }
        }

        if (! Schema::hasIndex('fest_participants', 'fest_participants_registration_student_unique')) {
            Schema::table('fest_participants', function (Blueprint $table) {
                $table->unique(
                    ['registration_id', 'student_id'],
                    'fest_participants_registration_student_unique'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fest_participants')) {
            Schema::table('fest_participants', function (Blueprint $table) {
                if (Schema::hasIndex('fest_participants', 'fest_participants_registration_student_unique')) {
                    $table->dropUnique('fest_participants_registration_student_unique');
                }
            });
        }
    }
};
