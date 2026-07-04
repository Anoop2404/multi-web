<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_item_heads')) {
            Schema::create('fest_item_heads', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->unsignedBigInteger('event_id')->nullable();
                $table->string('event_type', 40)->nullable();
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->string('name');
                $table->string('slug');
                $table->string('sport_discipline', 60)->nullable();
                $table->string('catalog_key', 120)->nullable();
                $table->boolean('is_team_heading')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['tenant_id', 'event_id']);
                $table->index(['tenant_id', 'event_type']);
            });
        }

        if (Schema::hasTable('fest_events')) {
            Schema::table('fest_events', function (Blueprint $table) {
                if (! Schema::hasColumn('fest_events', 'require_event_registration')) {
                    $table->boolean('require_event_registration')->default(false)->after('registration_locked');
                }
                if (! Schema::hasColumn('fest_events', 'event_reg_start')) {
                    $table->date('event_reg_start')->nullable()->after('require_event_registration');
                }
                if (! Schema::hasColumn('fest_events', 'event_reg_end')) {
                    $table->date('event_reg_end')->nullable()->after('event_reg_start');
                }
                if (! Schema::hasColumn('fest_events', 'allow_student_self_register')) {
                    $table->boolean('allow_student_self_register')->default(false)->after('event_reg_end');
                }
                if (! Schema::hasColumn('fest_events', 'numbering_settings')) {
                    $table->json('numbering_settings')->nullable()->after('fee_settings');
                }
            });
        }

        if (Schema::hasTable('fest_event_items')) {
            Schema::table('fest_event_items', function (Blueprint $table) {
                if (! Schema::hasColumn('fest_event_items', 'head_id')) {
                    $table->unsignedBigInteger('head_id')->nullable()->after('event_id');
                }
                if (! Schema::hasColumn('fest_event_items', 'reg_start')) {
                    $table->date('reg_start')->nullable()->after('is_enabled');
                }
                if (! Schema::hasColumn('fest_event_items', 'reg_end')) {
                    $table->date('reg_end')->nullable()->after('reg_start');
                }
                if (! Schema::hasColumn('fest_event_items', 'results_published_at')) {
                    $table->timestamp('results_published_at')->nullable()->after('reg_end');
                }
                if (! Schema::hasColumn('fest_event_items', 'item_reg_id_start')) {
                    $table->unsignedInteger('item_reg_id_start')->nullable()->after('results_published_at');
                }
            });
        }

        if (Schema::hasTable('fest_event_staff')) {
            Schema::table('fest_event_staff', function (Blueprint $table) {
                if (! Schema::hasColumn('fest_event_staff', 'head_id')) {
                    $table->unsignedBigInteger('head_id')->nullable()->after('venue_id');
                }
            });
        }

        if (Schema::hasTable('fest_school_event_fees')) {
            Schema::table('fest_school_event_fees', function (Blueprint $table) {
                if (! Schema::hasColumn('fest_school_event_fees', 'student_registration_fee')) {
                    $table->decimal('student_registration_fee', 10, 2)->default(0)->after('school_registration_fee');
                }
                if (! Schema::hasColumn('fest_school_event_fees', 'extra_item_fee')) {
                    $table->decimal('extra_item_fee', 10, 2)->default(0)->after('participation_fee');
                }
            });
        }

        if (! Schema::hasTable('fest_school_event_fee_lines')) {
            Schema::create('fest_school_event_fee_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('fest_school_event_fee_id');
                $table->string('line_type', 40);
                $table->string('label');
                $table->unsignedInteger('quantity')->default(1);
                $table->decimal('unit_amount', 10, 2)->default(0);
                $table->decimal('amount', 10, 2)->default(0);
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index('fest_school_event_fee_id');
            });
        }

        if (Schema::hasTable('fest_level_registrations')) {
            Schema::table('fest_level_registrations', function (Blueprint $table) {
                if (! Schema::hasColumn('fest_level_registrations', 'school_id')) {
                    $table->string('school_id')->nullable()->after('student_id');
                }
                if (! Schema::hasColumn('fest_level_registrations', 'status')) {
                    $table->string('status', 20)->default('active')->after('registration_number');
                }
                if (! Schema::hasColumn('fest_level_registrations', 'registered_at')) {
                    $table->timestamp('registered_at')->nullable()->after('status');
                }
            });
        }

        if (Schema::hasTable('fest_participants')) {
            Schema::table('fest_participants', function (Blueprint $table) {
                if (! Schema::hasColumn('fest_participants', 'item_registration_number')) {
                    $table->string('item_registration_number', 40)->nullable()->after('level_registration_number');
                }
            });
        }

        if (Schema::hasTable('fest_catalog_items')) {
            Schema::table('fest_catalog_items', function (Blueprint $table) {
                if (! Schema::hasColumn('fest_catalog_items', 'head_key')) {
                    $table->string('head_key', 120)->nullable()->after('catalog_key');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_school_event_fee_lines');
        Schema::dropIfExists('fest_item_heads');

        foreach ([
            'fest_events' => ['require_event_registration', 'event_reg_start', 'event_reg_end', 'allow_student_self_register', 'numbering_settings'],
            'fest_event_items' => ['head_id', 'reg_start', 'reg_end', 'results_published_at', 'item_reg_id_start'],
            'fest_event_staff' => ['head_id'],
            'fest_school_event_fees' => ['student_registration_fee', 'extra_item_fee'],
            'fest_level_registrations' => ['school_id', 'status', 'registered_at'],
            'fest_participants' => ['item_registration_number'],
            'fest_catalog_items' => ['head_key'],
        ] as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns) {
                foreach ($columns as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $blueprint->dropColumn($column);
                    }
                }
            });
        }
    }
};
