<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('photo');
            }
            if (! Schema::hasColumn('students', 'verified_by_user_id')) {
                $table->unsignedBigInteger('verified_by_user_id')->nullable()->after('verified_at');
            }
        });

        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'verified_at') && ! $this->indexExists('students', 'students_verified_at_idx')) {
                $table->index('verified_at', 'students_verified_at_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'verified_by_user_id')) {
                $table->dropColumn('verified_by_user_id');
            }
            if ($this->indexExists('students', 'students_verified_at_idx')) {
                $table->dropIndex('students_verified_at_idx');
            }
            if (Schema::hasColumn('students', 'verified_at')) {
                $table->dropColumn('verified_at');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'pgsql') {
            return count($connection->select(
                'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?',
                [$table, $index]
            )) > 0;
        }

        if ($driver === 'mysql') {
            return count($connection->select(
                'SHOW INDEX FROM '.$table.' WHERE Key_name = ?',
                [$index]
            )) > 0;
        }

        return false;
    }
};
