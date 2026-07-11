<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_streams', function (Blueprint $table) {
            $table->id();
            $table->string('sahodaya_id')->nullable()->index();
            $table->string('code', 64);
            $table->string('label');
            $table->string('examination_type', 16)->default('AISSCE');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->jsonb('default_subjects')->nullable();
            $table->timestamps();
            $table->unique(['sahodaya_id', 'code'], 'exam_streams_sahodaya_code_unique');
        });

        // Seed canonical Class XII streams (global rows: sahodaya_id null).
        $now = now();
        $streams = [
            ['code' => 'bio_science', 'label' => 'Bio Science (PCB)', 'sort_order' => 10, 'default_subjects' => ['English Core', 'Physics', 'Chemistry', 'Biology', 'Mathematics']],
            ['code' => 'computer_science', 'label' => 'Computer Science (PCM + CS)', 'sort_order' => 20, 'default_subjects' => ['English Core', 'Physics', 'Chemistry', 'Mathematics', 'Computer Science']],
            ['code' => 'commerce', 'label' => 'Commerce', 'sort_order' => 30, 'default_subjects' => ['English Core', 'Accountancy', 'Business Studies', 'Economics', 'Mathematics']],
            ['code' => 'humanities', 'label' => 'Humanities / Arts', 'sort_order' => 40, 'default_subjects' => ['English Core', 'History', 'Geography', 'Political Science', 'Economics']],
            ['code' => 'other', 'label' => 'Other / Mixed', 'sort_order' => 90, 'default_subjects' => ['English Core', 'Mathematics', 'Physics', 'Chemistry', 'Biology']],
        ];
        foreach ($streams as $stream) {
            DB::table('exam_streams')->insert([
                'sahodaya_id' => null,
                'code' => $stream['code'],
                'label' => $stream['label'],
                'examination_type' => 'AISSCE',
                'sort_order' => $stream['sort_order'],
                'is_active' => true,
                'default_subjects' => json_encode($stream['default_subjects']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::create('topper_count_configs', function (Blueprint $table) {
            $table->id();
            $table->string('sahodaya_id')->index();
            $table->unsignedTinyInteger('class')->nullable(); // 10, 12, or null = all
            $table->string('scope', 32)->default('overall'); // overall | stream | subject
            $table->unsignedBigInteger('stream_id')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable(); // central Subject.id (no cross-DB FK)
            $table->unsignedTinyInteger('top_n')->default(3);
            $table->timestamps();
            $table->unique(
                ['sahodaya_id', 'class', 'scope', 'stream_id', 'subject_id'],
                'topper_count_configs_unique'
            );
        });

        Schema::create('topper_subject_marks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('topper_id');
            $table->foreign('topper_id')->references('id')->on('toppers')->cascadeOnDelete();
            $table->unsignedBigInteger('subject_id')->nullable()->index(); // central Subject.id
            $table->string('subject_label');
            $table->decimal('marks', 6, 2);
            $table->timestamps();
            $table->unique(['topper_id', 'subject_label'], 'topper_subject_marks_unique');
            $table->index(['subject_id', 'marks']);
        });

        Schema::create('api_configs', function (Blueprint $table) {
            $table->id();
            $table->string('sahodaya_id')->unique();
            $table->decimal('weight_pass_percent', 5, 2)->default(40);
            $table->decimal('weight_distinctions', 5, 2)->default(20);
            $table->decimal('weight_highest_mark', 5, 2)->default(20);
            $table->decimal('weight_toppers', 5, 2)->default(20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('academic_performance_scores', function (Blueprint $table) {
            $table->id();
            $table->string('sahodaya_id')->index();
            $table->string('tenant_id')->index();
            $table->string('academic_year');
            $table->unsignedBigInteger('academic_year_id')->nullable()->index();
            $table->string('examination_type', 16)->nullable();
            $table->unsignedTinyInteger('class')->nullable();
            $table->unsignedBigInteger('board_result_id')->nullable();
            $table->decimal('score', 10, 4);
            $table->jsonb('components')->nullable();
            $table->timestamps();
            $table->unique(
                ['sahodaya_id', 'tenant_id', 'academic_year', 'examination_type', 'class'],
                'academic_performance_scores_unique'
            );
            $table->index(['sahodaya_id', 'academic_year', 'score'], 'academic_performance_scores_rank');
        });

        Schema::create('academic_awards', function (Blueprint $table) {
            $table->id();
            $table->string('sahodaya_id')->index();
            $table->string('tenant_id')->nullable()->index();
            $table->string('academic_year');
            $table->unsignedBigInteger('academic_year_id')->nullable()->index();
            $table->string('award_type', 64);
            $table->string('title');
            $table->decimal('score', 10, 4)->nullable();
            $table->unsignedBigInteger('board_result_id')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();
            $table->unique(
                ['sahodaya_id', 'academic_year', 'award_type'],
                'academic_awards_unique'
            );
            $table->index(['sahodaya_id', 'academic_year', 'award_type']);
        });

        Schema::table('board_results', function (Blueprint $table) {
            $table->unsignedBigInteger('academic_year_id')->nullable()->after('academic_year')->index();
        });

        Schema::table('toppers', function (Blueprint $table) {
            $table->unsignedBigInteger('stream_id')->nullable()->after('stream')->index();
        });

        // Best-effort backfill of stream_id from free-text stream labels / keys.
        $streamMap = DB::table('exam_streams')->whereNull('sahodaya_id')->pluck('id', 'code');
        $labelMap = DB::table('exam_streams')->whereNull('sahodaya_id')->pluck('id', 'label');
        foreach (DB::table('toppers')->whereNotNull('stream')->whereNull('stream_id')->cursor() as $topper) {
            $raw = strtolower(str_replace([' ', '-'], '_', (string) $topper->stream));
            $id = $streamMap[$raw] ?? null;
            if (! $id) {
                foreach ($labelMap as $label => $streamId) {
                    if (strcasecmp((string) $label, (string) $topper->stream) === 0) {
                        $id = $streamId;
                        break;
                    }
                }
            }
            if ($id) {
                DB::table('toppers')->where('id', $topper->id)->update(['stream_id' => $id]);
            }
        }

        // Bridge existing subject_marks JSON into topper_subject_marks.
        foreach (DB::table('toppers')->whereNotNull('subject_marks')->cursor() as $topper) {
            $marks = json_decode((string) $topper->subject_marks, true);
            if (! is_array($marks)) {
                continue;
            }
            foreach ($marks as $subject => $value) {
                if ($value === null || $value === '' || ! is_numeric($value)) {
                    continue;
                }
                DB::table('topper_subject_marks')->insertOrIgnore([
                    'topper_id' => $topper->id,
                    'subject_id' => null,
                    'subject_label' => trim((string) $subject),
                    'marks' => (float) $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('toppers', function (Blueprint $table) {
            $table->dropColumn('stream_id');
        });

        Schema::table('board_results', function (Blueprint $table) {
            $table->dropColumn('academic_year_id');
        });

        Schema::dropIfExists('academic_awards');
        Schema::dropIfExists('academic_performance_scores');
        Schema::dropIfExists('api_configs');
        Schema::dropIfExists('topper_subject_marks');
        Schema::dropIfExists('topper_count_configs');
        Schema::dropIfExists('exam_streams');
    }
};
