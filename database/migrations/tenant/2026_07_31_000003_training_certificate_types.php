<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('training_programs') && ! Schema::hasColumn('training_programs', 'certificate_type')) {
            Schema::table('training_programs', function (Blueprint $table) {
                $table->string('certificate_type', 50)->default('participation')->after('min_attendance_percent');
            });
        }

        $this->widenStringColumn('certificate_templates', 'certificate_type', 50, 'winner');
        $this->widenStringColumn('certificates', 'cert_type', 50, 'winner');
    }

    public function down(): void
    {
        if (Schema::hasTable('training_programs') && Schema::hasColumn('training_programs', 'certificate_type')) {
            Schema::table('training_programs', function (Blueprint $table) {
                $table->dropColumn('certificate_type');
            });
        }
    }

    private function widenStringColumn(string $table, string $column, int $length, string $default): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE VARCHAR({$length})");

            return;
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE {$table} MODIFY {$column} VARCHAR({$length}) NOT NULL DEFAULT '{$default}'");

            return;
        }

        // SQLite: enum columns use CHECK constraints — rebuild as free string.
        if ($driver === 'sqlite') {
            $tmp = "{$column}_widen_tmp";
            Schema::table($table, function (Blueprint $blueprint) use ($tmp, $length) {
                $blueprint->string($tmp, $length)->nullable();
            });
            DB::table($table)->update([$tmp => DB::raw($column)]);
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropColumn($column);
            });
            Schema::table($table, function (Blueprint $blueprint) use ($column, $length, $default) {
                $blueprint->string($column, $length)->default($default)->nullable();
            });
            DB::table($table)->update([$column => DB::raw($tmp)]);
            Schema::table($table, function (Blueprint $blueprint) use ($tmp) {
                $blueprint->dropColumn($tmp);
            });
        }
    }
};
