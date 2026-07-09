<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const LEGACY_GROUPS = ['lp', 'up', 'hs', 'hss', 'open'];

    /** @var list<string> */
    private const TABLES = ['fest_event_items', 'fest_combination_rules'];

    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'class_group')) {
                continue;
            }

            if ($driver === 'pgsql') {
                $this->expandClassGroupOnPostgres($table);
            } elseif ($driver === 'mysql') {
                $default = $table === 'fest_event_items' ? "NOT NULL DEFAULT 'open'" : 'NULL';
                DB::statement("ALTER TABLE {$table} MODIFY class_group VARCHAR(20) {$default}");
            }
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'class_group')) {
                continue;
            }

            $this->restoreLegacyClassGroupOnPostgres($table);
        }
    }

    private function expandClassGroupOnPostgres(string $table): void
    {
        $constraint = "{$table}_class_group_check";

        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}");
        DB::statement("ALTER TABLE {$table} ALTER COLUMN class_group TYPE VARCHAR(20) USING class_group::text");

        if ($table === 'fest_event_items') {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN class_group SET DEFAULT 'open'");
        }

        $legacy = implode("', '", self::LEGACY_GROUPS);
        DB::statement(<<<SQL
            ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK (
                class_group IS NULL
                OR class_group IN ('{$legacy}')
                OR class_group ~ '^cc_[0-9]+\$'
            )
        SQL);
    }

    private function restoreLegacyClassGroupOnPostgres(string $table): void
    {
        $constraint = "{$table}_class_group_check";

        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}");
        DB::statement("ALTER TABLE {$table} ALTER COLUMN class_group TYPE VARCHAR(20) USING class_group::text");

        $legacy = implode("', '", self::LEGACY_GROUPS);
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK (class_group IN ('{$legacy}'))");
    }
};
