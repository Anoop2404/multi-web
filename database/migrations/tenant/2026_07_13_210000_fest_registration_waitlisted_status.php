<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_registrations')) {
            return;
        }

        $this->allowStatus(
            ['draft', 'submitted', 'pending_approval', 'approved', 'rejected', 'withdrawn', 'waitlisted'],
            'draft',
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('fest_registrations')) {
            return;
        }

        DB::table('fest_registrations')->where('status', 'waitlisted')->update(['status' => 'withdrawn']);

        $this->allowStatus(
            ['draft', 'submitted', 'pending_approval', 'approved', 'rejected', 'withdrawn'],
            'draft',
        );
    }

    /** @param  list<string>  $states */
    private function allowStatus(array $states, string $default): void
    {
        $driver = DB::getDriverName();
        $list = "'".implode("','", $states)."'";

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE fest_registrations MODIFY status ENUM({$list}) NOT NULL DEFAULT '{$default}'");
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE fest_registrations DROP CONSTRAINT IF EXISTS fest_registrations_status_check');
            DB::statement("ALTER TABLE fest_registrations ADD CONSTRAINT fest_registrations_status_check CHECK (status IN ({$list}))");
        } else {
            Schema::table('fest_registrations', function (Blueprint $table) use ($states, $default) {
                $table->enum('status', $states)->default($default)->change();
            });
        }
    }
};
