<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = Schema::getConnection();
        if (! Schema::hasTable('fest_registrations') || ! in_array($connection->getDriverName(), ['pgsql', 'sqlite'], true)) {
            return;
        }
        // The original migration only installed this index on PostgreSQL. Do not
        // introduce a new constraint in databases that never had the old one.
        if (! Schema::hasIndex('fest_registrations', 'fest_reg_active_unique')) {
            return;
        }

        // Each wildcard needs its own registration and origin school. The shared
        // synthetic Appeal School is not a school's single normal item entry.
        $connection->transaction(function () use ($connection) {
            $connection->statement('DROP INDEX IF EXISTS fest_reg_active_unique');
            $connection->statement(<<<'SQL'
CREATE UNIQUE INDEX fest_reg_active_unique
    ON fest_registrations (event_id, school_id, item_id)
    WHERE status NOT IN ('withdrawn', 'rejected')
      AND school_id NOT LIKE 'appeal-school-%'
SQL);
        });
    }

    public function down(): void
    {
        $connection = Schema::getConnection();
        if (! Schema::hasTable('fest_registrations') || ! in_array($connection->getDriverName(), ['pgsql', 'sqlite'], true)) {
            return;
        }
        if (! Schema::hasIndex('fest_registrations', 'fest_reg_active_unique')) {
            return;
        }

        // Refuse a rollback that would require deleting legitimate wildcard entries.
        if ($connection->table('fest_registrations')->whereNotIn('status', ['withdrawn', 'rejected'])
            ->groupBy('event_id', 'school_id', 'item_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Cannot restore the old index while multiple active wildcard registrations exist. No registrations were removed.');
        }
        $connection->transaction(function () use ($connection) {
            $connection->statement('DROP INDEX IF EXISTS fest_reg_active_unique');
            $connection->statement(<<<'SQL'
CREATE UNIQUE INDEX fest_reg_active_unique
    ON fest_registrations (event_id, school_id, item_id)
    WHERE status NOT IN ('withdrawn', 'rejected')
SQL);
        });
    }
};
