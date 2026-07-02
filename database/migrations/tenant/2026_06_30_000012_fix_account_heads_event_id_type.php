<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('account_heads', 'event_id')) {
            Schema::table('account_heads', function (Blueprint $table) {
                $table->unsignedBigInteger('event_id')->nullable()->after('category');
                $table->foreign('event_id')->references('id')->on('fest_events')->nullOnDelete();
            });

            return;
        }

        if ($this->eventIdColumnIsBigInt()) {
            return;
        }

        Schema::table('account_heads', function (Blueprint $table) {
            $this->dropEventIdIndexIfPresent($table);
            $table->dropColumn('event_id');
        });

        Schema::table('account_heads', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable()->after('category');
            $table->foreign('event_id')->references('id')->on('fest_events')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('account_heads', 'event_id')) {
            return;
        }

        Schema::table('account_heads', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
            $table->dropColumn('event_id');
        });

        Schema::table('account_heads', function (Blueprint $table) {
            $table->uuid('event_id')->nullable()->after('category');
            $table->index('event_id');
        });
    }

    private function eventIdColumnIsBigInt(): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $row = DB::selectOne(
                'SELECT data_type FROM information_schema.columns
                 WHERE table_schema = current_schema()
                   AND table_name = ?
                   AND column_name = ?',
                ['account_heads', 'event_id']
            );

            return ($row->data_type ?? null) === 'bigint';
        }

        if ($driver === 'sqlite') {
            foreach (DB::select('PRAGMA table_info(account_heads)') as $column) {
                if ($column->name === 'event_id') {
                    return in_array(strtolower((string) $column->type), ['integer', 'bigint'], true);
                }
            }
        }

        return false;
    }

    private function dropEventIdIndexIfPresent(Blueprint $table): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $index = DB::selectOne(
                "SELECT indexname FROM pg_indexes
                 WHERE schemaname = current_schema()
                   AND tablename = 'account_heads'
                   AND indexdef LIKE '%(event_id)%'"
            );

            if ($index?->indexname) {
                DB::statement('DROP INDEX IF EXISTS "'.$index->indexname.'"');
            }

            return;
        }

        if ($driver === 'sqlite') {
            try {
                $table->dropIndex(['event_id']);
            } catch (\Throwable) {
                // Index may not exist on fresh SQLite installs.
            }
        }
    }
};
