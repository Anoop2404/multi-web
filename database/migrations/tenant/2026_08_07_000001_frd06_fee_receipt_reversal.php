<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->allowFeeReceiptStatuses([
            'uploaded', 'approved', 'rejected', 'superseded', 'reversed',
        ]);

        if (Schema::hasTable('fee_receipts')) {
            Schema::table('fee_receipts', function (Blueprint $table) {
                if (! Schema::hasColumn('fee_receipts', 'reversed_at')) {
                    $table->timestamp('reversed_at')->nullable()->after('reviewed_at');
                }
                if (! Schema::hasColumn('fee_receipts', 'reversed_by')) {
                    $table->unsignedBigInteger('reversed_by')->nullable()->after('reversed_at');
                }
                if (! Schema::hasColumn('fee_receipts', 'reversal_reason')) {
                    $table->text('reversal_reason')->nullable()->after('reversed_by');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fee_receipts')) {
            Schema::table('fee_receipts', function (Blueprint $table) {
                foreach (['reversal_reason', 'reversed_by', 'reversed_at'] as $col) {
                    if (Schema::hasColumn('fee_receipts', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        $this->allowFeeReceiptStatuses(['uploaded', 'approved', 'rejected', 'superseded']);
    }

    /** @param  list<string>  $states */
    private function allowFeeReceiptStatuses(array $states): void
    {
        if (! Schema::hasTable('fee_receipts') || ! Schema::hasColumn('fee_receipts', 'status')) {
            return;
        }

        $driver = DB::getDriverName();
        $list = "'".implode("','", $states)."'";

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE fee_receipts MODIFY status ENUM({$list}) NOT NULL DEFAULT 'uploaded'");
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE fee_receipts DROP CONSTRAINT IF EXISTS fee_receipts_status_check');
            DB::statement("ALTER TABLE fee_receipts ADD CONSTRAINT fee_receipts_status_check CHECK (status IN ({$list}))");
        } else {
            // SQLite / others: recreate enum CHECK via doctrine change().
            Schema::table('fee_receipts', function (Blueprint $table) use ($states) {
                $table->enum('status', $states)->default('uploaded')->change();
            });
        }
    }
};
