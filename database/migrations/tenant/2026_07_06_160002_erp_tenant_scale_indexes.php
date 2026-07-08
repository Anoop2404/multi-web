<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fest_registrations')) {
            Schema::table('fest_registrations', function (Blueprint $table) {
                if (! $this->indexExists('fest_registrations', 'fest_reg_event_school_item_status_idx')) {
                    $table->index(
                        ['event_id', 'school_id', 'item_id', 'status'],
                        'fest_reg_event_school_item_status_idx'
                    );
                }
                if (! $this->indexExists('fest_registrations', 'fest_reg_status_idx')) {
                    $table->index('status', 'fest_reg_status_idx');
                }
            });

            $connection = Schema::getConnection();
            if ($connection->getDriverName() === 'pgsql' && ! $this->indexExists('fest_registrations', 'fest_reg_active_unique')) {
                $connection->statement(<<<'SQL'
CREATE UNIQUE INDEX fest_reg_active_unique
    ON fest_registrations (event_id, school_id, item_id)
    WHERE status NOT IN ('withdrawn', 'rejected')
SQL);
            }
        }

        if (Schema::hasTable('fest_participants')) {
            Schema::table('fest_participants', function (Blueprint $table) {
                if (! $this->indexExists('fest_participants', 'fest_participants_student_id_idx')) {
                    $table->index('student_id', 'fest_participants_student_id_idx');
                }
            });
        }

        if (Schema::hasTable('teachers')) {
            Schema::table('teachers', function (Blueprint $table) {
                if (! $this->indexExists('teachers', 'teachers_tenant_status_idx')) {
                    $table->index(['tenant_id', 'status'], 'teachers_tenant_status_idx');
                }
                if (Schema::hasColumn('teachers', 'verified_at') && ! $this->indexExists('teachers', 'teachers_verified_at_idx')) {
                    $table->index('verified_at', 'teachers_verified_at_idx');
                }
                if (! $this->indexExists('teachers', 'teachers_name_idx')) {
                    $table->index(['tenant_id', 'name'], 'teachers_name_idx');
                }
            });
        }

        if (Schema::hasTable('fee_receipts')) {
            Schema::table('fee_receipts', function (Blueprint $table) {
                if (! $this->indexExists('fee_receipts', 'fee_receipts_status_reviewed_idx')) {
                    $table->index(['status', 'reviewed_at'], 'fee_receipts_status_reviewed_idx');
                }
            });
        }

        if (Schema::hasTable('ledger_transactions')) {
            Schema::table('ledger_transactions', function (Blueprint $table) {
                if (! $this->indexExists('ledger_transactions', 'ledger_transactions_account_date_idx')) {
                    $table->index(
                        ['account_head_id', 'transaction_date'],
                        'ledger_transactions_account_date_idx'
                    );
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ledger_transactions')) {
            Schema::table('ledger_transactions', function (Blueprint $table) {
                if ($this->indexExists('ledger_transactions', 'ledger_transactions_account_date_idx')) {
                    $table->dropIndex('ledger_transactions_account_date_idx');
                }
            });
        }

        if (Schema::hasTable('fee_receipts')) {
            Schema::table('fee_receipts', function (Blueprint $table) {
                if ($this->indexExists('fee_receipts', 'fee_receipts_status_reviewed_idx')) {
                    $table->dropIndex('fee_receipts_status_reviewed_idx');
                }
            });
        }

        if (Schema::hasTable('teachers')) {
            Schema::table('teachers', function (Blueprint $table) {
                foreach (['teachers_tenant_status_idx', 'teachers_verified_at_idx', 'teachers_name_idx'] as $idx) {
                    if ($this->indexExists('teachers', $idx)) {
                        $table->dropIndex($idx);
                    }
                }
            });
        }

        if (Schema::hasTable('fest_participants')) {
            Schema::table('fest_participants', function (Blueprint $table) {
                if ($this->indexExists('fest_participants', 'fest_participants_student_id_idx')) {
                    $table->dropIndex('fest_participants_student_id_idx');
                }
            });
        }

        if (Schema::hasTable('fest_registrations')) {
            Schema::table('fest_registrations', function (Blueprint $table) {
                foreach (['fest_reg_event_school_item_status_idx', 'fest_reg_status_idx', 'fest_reg_active_unique'] as $idx) {
                    if ($this->indexExists('fest_registrations', $idx)) {
                        $table->dropIndex($idx);
                    }
                }
            });
        }
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
