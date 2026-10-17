<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('fest_participants', ['order_no'], 'fest_participants_order_no_idx');
        $this->addIndex('fest_groups', ['order_no'], 'fest_groups_order_no_idx');
    }

    public function down(): void
    {
        $this->dropIndex('fest_participants', 'fest_participants_order_no_idx');
        $this->dropIndex('fest_groups', 'fest_groups_order_no_idx');
    }

    /** @param list<string> $columns */
    private function addIndex(string $table, array $columns, string $name): void
    {
        if (Schema::hasTable($table) && ! Schema::hasIndex($table, $name)) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
                $blueprint->index($columns, $name);
            });
        }
    }

    private function dropIndex(string $table, string $name): void
    {
        if (Schema::hasTable($table) && Schema::hasIndex($table, $name)) {
            Schema::table($table, function (Blueprint $blueprint) use ($name) {
                $blueprint->dropIndex($name);
            });
        }
    }
};
