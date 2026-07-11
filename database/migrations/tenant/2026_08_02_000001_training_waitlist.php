<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_registrations')) {
            return;
        }

        if (! Schema::hasColumn('training_registrations', 'waitlist_position')) {
            Schema::table('training_registrations', function (Blueprint $table) {
                $table->unsignedInteger('waitlist_position')->nullable()->after('status');
            });
        }

        $this->allowStatus(
            ['registered', 'confirmed', 'cancelled', 'completed', 'waitlisted', 'rejected'],
            'registered',
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('training_registrations')) {
            return;
        }

        DB::table('training_registrations')
            ->where('status', 'waitlisted')
            ->update(['status' => 'cancelled', 'waitlist_position' => null]);

        DB::table('training_registrations')
            ->where('status', 'rejected')
            ->update(['status' => 'cancelled']);

        $this->allowStatus(
            ['registered', 'confirmed', 'cancelled', 'completed'],
            'registered',
        );

        if (Schema::hasColumn('training_registrations', 'waitlist_position')) {
            Schema::table('training_registrations', function (Blueprint $table) {
                $table->dropColumn('waitlist_position');
            });
        }
    }

    /** @param  list<string>  $states */
    private function allowStatus(array $states, string $default): void
    {
        $driver = DB::getDriverName();
        $list = "'".implode("','", $states)."'";

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE training_registrations MODIFY status ENUM({$list}) NOT NULL DEFAULT '{$default}'");
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE training_registrations DROP CONSTRAINT IF EXISTS training_registrations_status_check');
            DB::statement("ALTER TABLE training_registrations ADD CONSTRAINT training_registrations_status_check CHECK (status IN ({$list}))");
        } else {
            Schema::table('training_registrations', function (Blueprint $table) use ($states, $default) {
                $table->enum('status', $states)->default($default)->change();
            });
        }
    }
};
