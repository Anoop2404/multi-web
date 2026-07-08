<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mcq_registrations')) {
            return;
        }

        Schema::table('mcq_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('mcq_registrations', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('submitted_at');
            }
            if (! Schema::hasColumn('mcq_registrations', 'cancelled_by_user_id')) {
                $table->unsignedBigInteger('cancelled_by_user_id')->nullable()->after('cancelled_at');
            }
        });

        $states = "'registered','started','submitted','absent','cancelled'";
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE mcq_registrations MODIFY status ENUM({$states}) NOT NULL DEFAULT 'registered'");
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE mcq_registrations DROP CONSTRAINT IF EXISTS mcq_registrations_status_check');
            DB::statement("ALTER TABLE mcq_registrations ADD CONSTRAINT mcq_registrations_status_check CHECK (status IN ({$states}))");
        } else {
            // SQLite: rebuild the column so the enum CHECK constraint accepts 'cancelled'.
            Schema::table('mcq_registrations', function (Blueprint $table) {
                $table->enum('status', ['registered', 'started', 'submitted', 'absent', 'cancelled'])
                    ->default('registered')
                    ->change();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('mcq_registrations')) {
            return;
        }

        Schema::table('mcq_registrations', function (Blueprint $table) {
            foreach (['cancelled_by_user_id', 'cancelled_at'] as $col) {
                if (Schema::hasColumn('mcq_registrations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
