<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_registration_batches')) {
            Schema::create('fest_registration_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->constrained('fest_events')->cascadeOnDelete();
                $table->string('code', 64);
                $table->string('name');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamp('registration_open')->nullable();
                $table->timestamp('registration_close')->nullable();
                $table->timestamp('payment_due_at')->nullable();
                $table->decimal('school_base_fee', 10, 2)->default(0);
                $table->string('invoice_prefix', 32)->nullable();
                $table->string('status', 20)->default('draft');
                $table->boolean('registration_locked')->default(false);
                $table->timestamps();

                $table->unique(['event_id', 'code'], 'fest_registration_batches_event_code_unique');
                $table->index(['event_id', 'sort_order']);
            });
        }

        Schema::table('fest_events', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_events', 'workflow_mode')) {
                $table->string('workflow_mode', 40)->default('standard')->after('phase_mode_enabled');
            }
            if (! Schema::hasColumn('fest_events', 'source_phase_id')) {
                $table->unsignedBigInteger('source_phase_id')->nullable()->after('root_event_id');
                $table->foreign('source_phase_id')->references('id')->on('fest_event_phases')->nullOnDelete();
            }
            if (! Schema::hasColumn('fest_events', 'registration_batch_id')) {
                $table->unsignedBigInteger('registration_batch_id')->nullable()->after('source_phase_id');
                $table->foreign('registration_batch_id')->references('id')->on('fest_registration_batches')->nullOnDelete();
            }
            if (! Schema::hasColumn('fest_events', 'workflow_leaf_key')) {
                $table->string('workflow_leaf_key', 128)->nullable()->after('registration_batch_id');
                $table->unique(
                    ['parent_event_id', 'workflow_leaf_key'],
                    'fest_events_parent_workflow_leaf_unique'
                );
            }
        });

        Schema::table('fest_event_phases', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_event_phases', 'registration_batch_id')) {
                $table->unsignedBigInteger('registration_batch_id')->nullable()->after('source_phase_id');
                $table->foreign('registration_batch_id')->references('id')->on('fest_registration_batches')->nullOnDelete();
            }
            if (! Schema::hasColumn('fest_event_phases', 'is_regional')) {
                $table->boolean('is_regional')->default(false)->after('registration_batch_id');
            }
            if (! Schema::hasColumn('fest_event_phases', 'result_publish_mode')) {
                $table->string('result_publish_mode', 24)->default('all_regions')->after('is_regional');
            }
        });

        if (! Schema::hasTable('fest_phase_regions')) {
            Schema::create('fest_phase_regions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('phase_id')->constrained('fest_event_phases')->cascadeOnDelete();
                $table->foreignId('region_id')->constrained('regions')->cascadeOnDelete();
                $table->string('venue')->nullable();
                $table->timestamp('conduct_start_at')->nullable();
                $table->timestamp('conduct_end_at')->nullable();
                $table->unsignedInteger('capacity')->nullable();
                $table->boolean('enabled')->default(true);
                $table->timestamps();

                $table->unique(['phase_id', 'region_id'], 'fest_phase_regions_phase_region_unique');
                $table->index(['region_id', 'enabled']);
            });
        }

        if (! Schema::hasTable('fest_school_phase_region_selections')) {
            Schema::create('fest_school_phase_region_selections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->constrained('fest_events')->cascadeOnDelete();
                $table->foreignId('phase_id')->constrained('fest_event_phases')->cascadeOnDelete();
                $table->string('school_id');
                $table->foreignId('region_id')->constrained('regions')->restrictOnDelete();
                $table->timestamp('selected_at')->nullable();
                $table->unsignedBigInteger('selected_by')->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->timestamp('changed_at')->nullable();
                $table->unsignedBigInteger('changed_by')->nullable();
                $table->text('change_reason')->nullable();
                $table->timestamps();

                $table->unique(['event_id', 'phase_id', 'school_id'], 'fest_school_phase_region_unique');
                $table->index(['event_id', 'phase_id', 'region_id'], 'fest_school_phase_region_lookup');
            });
        }

        Schema::table('fest_school_event_fees', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_school_event_fees', 'registration_batch_id')) {
                $table->unsignedBigInteger('registration_batch_id')->nullable()->after('phase_id');
                $table->foreign('registration_batch_id')->references('id')->on('fest_registration_batches')->nullOnDelete();
                $table->index(
                    ['event_id', 'school_id', 'registration_batch_id'],
                    'fest_school_event_fees_batch_lookup'
                );
            }
        });

        Schema::table('fest_event_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_event_invoices', 'registration_batch_id')) {
                $table->unsignedBigInteger('registration_batch_id')->nullable()->after('school_id');
                $table->foreign('registration_batch_id')->references('id')->on('fest_registration_batches')->nullOnDelete();
            }
            if (! Schema::hasColumn('fest_event_invoices', 'billing_key')) {
                $table->string('billing_key', 80)->default('EVENT')->after('registration_batch_id');
            }
        });
        Schema::table('fest_event_invoices', function (Blueprint $table) {
            $table->dropUnique('fest_event_invoices_event_id_school_id_unique');
            $table->unique(['event_id', 'school_id', 'billing_key'], 'fest_event_invoices_billing_key_unique');
        });

        Schema::table('fest_event_staff', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_event_staff', 'source_phase_id')) {
                $table->unsignedBigInteger('source_phase_id')->nullable()->after('region_id');
                $table->foreign('source_phase_id')->references('id')->on('fest_event_phases')->nullOnDelete();
                $table->index(
                    ['event_id', 'source_phase_id', 'region_id'],
                    'fest_event_staff_phase_region_lookup'
                );
            }
        });
        Schema::table('fest_event_staff', function (Blueprint $table) {
            $table->dropUnique('fest_event_staff_assignment_unique');
            $table->index(['event_id', 'user_id', 'duty'], 'fest_event_staff_event_user_duty_lookup');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('fest_event_staff', 'source_phase_id')) {
            Schema::table('fest_event_staff', function (Blueprint $table) {
                $table->dropIndex('fest_event_staff_event_user_duty_lookup');
                $table->dropIndex('fest_event_staff_phase_region_lookup');
                $table->dropForeign(['source_phase_id']);
                $table->dropColumn('source_phase_id');
            });

            $hasDuplicateStaff = \Illuminate\Support\Facades\DB::table('fest_event_staff')
                ->select('event_id', 'user_id', 'duty', 'stage_id')
                ->groupBy('event_id', 'user_id', 'duty', 'stage_id')
                ->havingRaw('count(*) > 1')
                ->exists();
            if (! $hasDuplicateStaff) {
                Schema::table('fest_event_staff', function (Blueprint $table) {
                    $table->unique(
                        ['event_id', 'user_id', 'duty', 'stage_id'],
                        'fest_event_staff_assignment_unique'
                    );
                });
            }
        }

        if (Schema::hasColumn('fest_event_invoices', 'billing_key')) {
            Schema::table('fest_event_invoices', function (Blueprint $table) {
                $table->dropUnique('fest_event_invoices_billing_key_unique');
                $table->unique(['event_id', 'school_id'], 'fest_event_invoices_event_id_school_id_unique');
                if (Schema::hasColumn('fest_event_invoices', 'registration_batch_id')) {
                    $table->dropForeign(['registration_batch_id']);
                }
                $table->dropColumn(array_values(array_filter(
                    ['registration_batch_id', 'billing_key'],
                    fn (string $column) => Schema::hasColumn('fest_event_invoices', $column)
                )));
            });
        }

        if (Schema::hasColumn('fest_school_event_fees', 'registration_batch_id')) {
            Schema::table('fest_school_event_fees', function (Blueprint $table) {
                $table->dropIndex('fest_school_event_fees_batch_lookup');
                $table->dropForeign(['registration_batch_id']);
                $table->dropColumn('registration_batch_id');
            });
        }

        Schema::dropIfExists('fest_school_phase_region_selections');
        Schema::dropIfExists('fest_phase_regions');

        Schema::table('fest_event_phases', function (Blueprint $table) {
            foreach (['registration_batch_id'] as $column) {
                if (Schema::hasColumn('fest_event_phases', $column)) {
                    $table->dropForeign([$column]);
                }
            }
            $columns = array_values(array_filter(
                ['registration_batch_id', 'is_regional', 'result_publish_mode'],
                fn (string $column) => Schema::hasColumn('fest_event_phases', $column)
            ));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('fest_events', function (Blueprint $table) {
            foreach (['source_phase_id', 'registration_batch_id'] as $column) {
                if (Schema::hasColumn('fest_events', $column)) {
                    $table->dropForeign([$column]);
                }
            }
            $columns = array_values(array_filter(
                ['workflow_mode', 'source_phase_id', 'registration_batch_id', 'workflow_leaf_key'],
                fn (string $column) => Schema::hasColumn('fest_events', $column)
            ));
            if (Schema::hasColumn('fest_events', 'workflow_leaf_key')) {
                $table->dropUnique('fest_events_parent_workflow_leaf_unique');
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::dropIfExists('fest_registration_batches');
    }
};
