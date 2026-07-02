<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('membership_payments')) {
            return;
        }

        if (! Schema::hasColumn('membership_payments', 'superseded_by_payment_id')) {
            Schema::table('membership_payments', function (Blueprint $table) {
                $table->unsignedBigInteger('superseded_by_payment_id')->nullable()->after('status');
                $table->foreign('superseded_by_payment_id')
                    ->references('id')
                    ->on('membership_payments')
                    ->nullOnDelete();
            });
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE membership_payments MODIFY status ENUM('submitted','verified','rejected','superseded') NOT NULL DEFAULT 'submitted'");
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE membership_payments DROP CONSTRAINT IF EXISTS membership_payments_status_check');
            DB::statement("ALTER TABLE membership_payments ADD CONSTRAINT membership_payments_status_check CHECK (status IN ('submitted','verified','rejected','superseded'))");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('membership_payments')) {
            return;
        }

        if (Schema::hasColumn('membership_payments', 'superseded_by_payment_id')) {
            Schema::table('membership_payments', function (Blueprint $table) {
                $table->dropForeign(['superseded_by_payment_id']);
                $table->dropColumn('superseded_by_payment_id');
            });
        }
    }
};
