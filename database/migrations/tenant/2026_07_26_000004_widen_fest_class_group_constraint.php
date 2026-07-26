<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026_07_10_000001_expand_fest_class_group_constraint.php allowed class_group to be one of
 * the fixed lp/up/hs/hss/open values or a cluster key (cc_<id>), but the new named
 * FestClassCategoryScheme categories (and the older per-event FestEventClassGroup "custom"
 * categories) use free-form, admin-chosen keys like "junior" or "category_i" — validated in
 * PHP as alpha_dash, max 60 chars (see FestEventSettingsController::storeClassCategorySchemeGroup()
 * and storeClassGroup()). Saving an item tagged with one of those keys hit two problems this
 * migration fixes: the column itself is only VARCHAR(20) (a 21+ char key would be truncated/
 * rejected outright), and the CHECK constraint rejects any key outside the old fixed set —
 * which is exactly the "violates check constraint fest_event_items_class_group_check" error
 * seen tagging an item with a category from a newly-created scheme.
 */
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
                $this->widenOnPostgres($table);
            } elseif ($driver === 'mysql') {
                $default = $table === 'fest_event_items' ? "NOT NULL DEFAULT 'open'" : 'NULL';
                DB::statement("ALTER TABLE {$table} MODIFY class_group VARCHAR(60) {$default}");
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

            $this->restorePreviousOnPostgres($table);
        }
    }

    private function widenOnPostgres(string $table): void
    {
        $constraint = "{$table}_class_group_check";

        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}");
        DB::statement("ALTER TABLE {$table} ALTER COLUMN class_group TYPE VARCHAR(60) USING class_group::text");

        if ($table === 'fest_event_items') {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN class_group SET DEFAULT 'open'");
        }

        $legacy = implode("', '", self::LEGACY_GROUPS);
        // - fixed legacy keys (lp/up/hs/hss/open)
        // - cluster keys (cc_<class-category-id>)
        // - free-form custom/named-scheme keys — same shape PHP's alpha_dash validation
        //   allows: letters, digits, dashes, underscores, 1-60 chars (matches the widened
        //   column so a validation-passing key can never be rejected by this constraint)
        DB::statement(<<<SQL
            ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK (
                class_group IS NULL
                OR class_group IN ('{$legacy}')
                OR class_group ~ '^cc_[0-9]+\$'
                OR class_group ~ '^[A-Za-z0-9_-]{1,60}\$'
            )
        SQL);
    }

    private function restorePreviousOnPostgres(string $table): void
    {
        $constraint = "{$table}_class_group_check";

        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}");
        DB::statement("ALTER TABLE {$table} ALTER COLUMN class_group TYPE VARCHAR(20) USING class_group::text");

        $legacy = implode("', '", self::LEGACY_GROUPS);
        DB::statement(<<<SQL
            ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK (
                class_group IS NULL
                OR class_group IN ('{$legacy}')
                OR class_group ~ '^cc_[0-9]+\$'
            )
        SQL);
    }
};
