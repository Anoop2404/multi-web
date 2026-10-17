<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Online exam engine — Phase 1 (docs/ONLINE_EXAM_ENGINE_PLAN_2026_10_15.md §6.1, §7):
 *  - question types (single / multiple / numeric) with a type-specific answer key,
 *  - Sahodaya-authored banks (no owning school),
 *  - paper sections on the exam ↔ bank link (pick all / first N / random N),
 *  - per-registration exam passcodes (encrypted for printing + a lookup hash for uniqueness).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mcq_questions', function (Blueprint $table) {
            if (! Schema::hasColumn('mcq_questions', 'type')) {
                $table->string('type', 16)->default('single')->after('bank_id');
            }
            if (! Schema::hasColumn('mcq_questions', 'answer_json')) {
                $table->json('answer_json')->nullable()->after('correct_option_key');
            }
            if (! Schema::hasColumn('mcq_questions', 'explanation')) {
                $table->text('explanation')->nullable()->after('negative_mark');
            }
            if (! Schema::hasColumn('mcq_questions', 'difficulty')) {
                $table->string('difficulty', 16)->nullable()->after('explanation');
            }
        });

        Schema::table('mcq_question_banks', function (Blueprint $table) {
            $table->string('school_id')->nullable()->change();
        });

        Schema::table('mcq_exam_question_banks', function (Blueprint $table) {
            if (! Schema::hasColumn('mcq_exam_question_banks', 'section_title')) {
                $table->string('section_title', 120)->nullable()->after('bank_id');
            }
            if (! Schema::hasColumn('mcq_exam_question_banks', 'pick_mode')) {
                $table->string('pick_mode', 10)->default('all')->after('section_title');
            }
            if (! Schema::hasColumn('mcq_exam_question_banks', 'pick_count')) {
                $table->unsignedSmallInteger('pick_count')->nullable()->after('pick_mode');
            }
            if (! Schema::hasColumn('mcq_exam_question_banks', 'display_order')) {
                $table->unsignedSmallInteger('display_order')->default(0)->after('pick_count');
            }
        });

        Schema::table('mcq_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('mcq_registrations', 'exam_passcode')) {
                $table->text('exam_passcode')->nullable()->after('seat_no');
            }
            if (! Schema::hasColumn('mcq_registrations', 'exam_passcode_lookup')) {
                $table->string('exam_passcode_lookup', 64)->nullable()->after('exam_passcode');
                $table->unique(['exam_id', 'exam_passcode_lookup'], 'mcq_registrations_exam_passcode_lookup_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mcq_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('mcq_registrations', 'exam_passcode_lookup')) {
                $table->dropUnique('mcq_registrations_exam_passcode_lookup_unique');
                $table->dropColumn('exam_passcode_lookup');
            }
            if (Schema::hasColumn('mcq_registrations', 'exam_passcode')) {
                $table->dropColumn('exam_passcode');
            }
        });

        Schema::table('mcq_exam_question_banks', function (Blueprint $table) {
            foreach (['display_order', 'pick_count', 'pick_mode', 'section_title'] as $col) {
                if (Schema::hasColumn('mcq_exam_question_banks', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('mcq_questions', function (Blueprint $table) {
            foreach (['difficulty', 'explanation', 'answer_json', 'type'] as $col) {
                if (Schema::hasColumn('mcq_questions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
