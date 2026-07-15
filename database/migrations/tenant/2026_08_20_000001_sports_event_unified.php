<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sports Head = Event unification: copy FestItemHead fee/policy/schedule fields
 * onto fest_events so each sport (Athletics, Chess, …) is a single FestEvent.
 *
 * Kalotsav continues to use fest_item_heads; these columns are nullable and only
 * populated for sports discipline / sport-event rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_events')) {
            return;
        }

        Schema::table('fest_events', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_events', 'catalog_key')) {
                $table->string('catalog_key', 80)->nullable()->after('sport_discipline');
            }
            if (! Schema::hasColumn('fest_events', 'is_team_heading')) {
                $table->boolean('is_team_heading')->default(true)->after('catalog_key');
            }
            if (! Schema::hasColumn('fest_events', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_team_heading');
            }

            if (! Schema::hasColumn('fest_events', 'default_item_fee')) {
                $table->decimal('default_item_fee', 10, 2)->nullable()->after('fee_settings');
            }
            if (! Schema::hasColumn('fest_events', 'extra_item_fee')) {
                $table->decimal('extra_item_fee', 10, 2)->nullable()->after('default_item_fee');
            }
            if (! Schema::hasColumn('fest_events', 'school_registration_fee')) {
                $table->decimal('school_registration_fee', 10, 2)->nullable()->after('extra_item_fee');
            }
            if (! Schema::hasColumn('fest_events', 'student_registration_fee')) {
                $table->decimal('student_registration_fee', 10, 2)->nullable()->after('school_registration_fee');
            }
            if (! Schema::hasColumn('fest_events', 'team_registration_fee')) {
                $table->decimal('team_registration_fee', 10, 2)->nullable()->after('student_registration_fee');
            }
            if (! Schema::hasColumn('fest_events', 'included_items_per_student')) {
                $table->unsignedSmallInteger('included_items_per_student')->default(0)->after('team_registration_fee');
            }
            if (! Schema::hasColumn('fest_events', 'included_teams')) {
                $table->unsignedSmallInteger('included_teams')->default(0)->after('included_items_per_student');
            }

            if (! Schema::hasColumn('fest_events', 'verification_policy')) {
                $table->string('verification_policy', 20)->default('all_students')->after('included_teams');
            }
            if (! Schema::hasColumn('fest_events', 'approval_policy')) {
                $table->string('approval_policy', 20)->default('auto')->after('verification_policy');
            }
            if (! Schema::hasColumn('fest_events', 'max_participants')) {
                $table->unsignedInteger('max_participants')->nullable()->after('approval_policy');
            }
            if (! Schema::hasColumn('fest_events', 'max_teams')) {
                $table->unsignedInteger('max_teams')->nullable()->after('max_participants');
            }

            if (! Schema::hasColumn('fest_events', 'reg_start')) {
                $table->date('reg_start')->nullable()->after('registration_close');
            }
            if (! Schema::hasColumn('fest_events', 'reg_end')) {
                $table->date('reg_end')->nullable()->after('reg_start');
            }
            if (! Schema::hasColumn('fest_events', 'competition_start')) {
                $table->date('competition_start')->nullable()->after('reg_end');
            }
            if (! Schema::hasColumn('fest_events', 'competition_end')) {
                $table->date('competition_end')->nullable()->after('competition_start');
            }
            if (! Schema::hasColumn('fest_events', 'schedule_mode')) {
                $table->string('schedule_mode', 20)->nullable()->after('competition_end');
            }
            if (! Schema::hasColumn('fest_events', 'competition_time')) {
                $table->time('competition_time')->nullable()->after('schedule_mode');
            }

            if (! Schema::hasColumn('fest_events', 'notification_settings')) {
                $table->json('notification_settings')->nullable()->after('numbering_settings');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fest_events')) {
            return;
        }

        Schema::table('fest_events', function (Blueprint $table) {
            foreach ([
                'catalog_key', 'is_team_heading', 'sort_order',
                'default_item_fee', 'extra_item_fee',
                'school_registration_fee', 'student_registration_fee', 'team_registration_fee',
                'included_items_per_student', 'included_teams',
                'verification_policy', 'approval_policy',
                'max_participants', 'max_teams',
                'reg_start', 'reg_end', 'competition_start', 'competition_end',
                'schedule_mode', 'competition_time',
                'notification_settings',
            ] as $col) {
                if (Schema::hasColumn('fest_events', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
