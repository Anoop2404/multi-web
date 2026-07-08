<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cumulative amount paid so far (sum of approved receipts). Balance = total_due - amount_paid.
        foreach (['mcq_school_fees', 'fest_school_event_fees', 'training_registrations'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'amount_paid')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->decimal('amount_paid', 10, 2)->default(0);
                });
            }
        }

        // Training registrations carry their fee state on the linked receipt today; add a
        // denormalised fee_status so partial/pending is queryable without joining receipts.
        if (Schema::hasTable('training_registrations') && ! Schema::hasColumn('training_registrations', 'fee_status')) {
            Schema::table('training_registrations', function (Blueprint $t) {
                $t->string('fee_status', 32)->nullable();
            });
        }

        $this->allowStatus('fest_school_event_fees', 'fest_school_event_fees_status_check',
            ['pending', 'proof_uploaded', 'partial', 'approved', 'rejected'], 'pending');

        // fee_receipts already uses 'superseded' in code but it was never added to the enum.
        $this->allowStatus('fee_receipts', 'fee_receipts_status_check',
            ['uploaded', 'approved', 'rejected', 'superseded'], 'uploaded');
    }

    public function down(): void
    {
        foreach (['mcq_school_fees', 'fest_school_event_fees', 'training_registrations'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'amount_paid')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('amount_paid');
                });
            }
        }

        if (Schema::hasTable('training_registrations') && Schema::hasColumn('training_registrations', 'fee_status')) {
            Schema::table('training_registrations', function (Blueprint $t) {
                $t->dropColumn('fee_status');
            });
        }
    }

    /** @param  list<string>  $states */
    private function allowStatus(string $table, string $constraint, array $states, string $default): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'status')) {
            return;
        }

        $driver = DB::getDriverName();
        $list = "'".implode("','", $states)."'";

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE {$table} MODIFY status ENUM({$list}) NOT NULL DEFAULT '{$default}'");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK (status IN ({$list}))");
        } else {
            Schema::table($table, function (Blueprint $t) use ($states, $default) {
                $t->enum('status', $states)->default($default)->change();
            });
        }
    }
};
