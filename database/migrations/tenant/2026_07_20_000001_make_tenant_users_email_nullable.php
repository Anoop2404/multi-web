<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Event-admin accounts may be created without an email address (login by
     * username only). Postgres treats multiple NULLs in a unique index as
     * distinct, so relaxing the NOT NULL constraint is safe for the existing
     * unique('email') index.
     */
    public function up(): void
    {
        if (! config('tenancy.database_per_sahodaya', true)) {
            return;
        }

        if (! Schema::hasTable('users')) {
            return;
        }

        DB::statement('ALTER TABLE users ALTER COLUMN email DROP NOT NULL');
    }

    public function down(): void
    {
        if (! config('tenancy.database_per_sahodaya', true)) {
            return;
        }

        if (! Schema::hasTable('users')) {
            return;
        }

        DB::statement("UPDATE users SET email = CONCAT('user-', id, '@placeholder.local') WHERE email IS NULL");
        DB::statement('ALTER TABLE users ALTER COLUMN email SET NOT NULL');
    }
};
