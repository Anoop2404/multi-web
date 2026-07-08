<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! $this->indexExists('users', 'users_tenant_id_idx')) {
                $table->index('tenant_id', 'users_tenant_id_idx');
            }
            if (! $this->indexExists('users', 'users_tenant_email_idx')) {
                $table->index(['tenant_id', 'email'], 'users_tenant_email_idx');
            }
        });

        $connection = Schema::getConnection();
        if ($connection->getDriverName() === 'pgsql' && ! $this->indexExists('users', 'users_tenant_username_idx')) {
            $connection->statement(
                'CREATE INDEX users_tenant_username_idx ON users (tenant_id, username) WHERE username IS NOT NULL'
            );
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            if (! $this->indexExists('audit_logs', 'audit_logs_created_at_idx')) {
                $table->index('created_at', 'audit_logs_created_at_idx');
            }
            if (! $this->indexExists('audit_logs', 'audit_logs_user_created_idx')) {
                $table->index(['user_id', 'created_at'], 'audit_logs_user_created_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            foreach (['audit_logs_created_at_idx', 'audit_logs_user_created_idx'] as $idx) {
                if ($this->indexExists('audit_logs', $idx)) {
                    $table->dropIndex($idx);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            foreach (['users_tenant_id_idx', 'users_tenant_email_idx', 'users_tenant_username_idx'] as $idx) {
                if ($this->indexExists('users', $idx)) {
                    $table->dropIndex($idx);
                }
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'pgsql') {
            $result = $connection->select(
                'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?',
                [$table, $index]
            );

            return count($result) > 0;
        }

        if ($driver === 'mysql') {
            $result = $connection->select(
                'SHOW INDEX FROM '.$table.' WHERE Key_name = ?',
                [$index]
            );

            return count($result) > 0;
        }

        if ($driver === 'sqlite') {
            $result = $connection->select(
                "SELECT 1 FROM sqlite_master WHERE type = 'index' AND name = ?",
                [$index]
            );

            return count($result) > 0;
        }

        return false;
    }
};
