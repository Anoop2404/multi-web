<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! config('tenancy.database_per_sahodaya', true)) {
            return;
        }

        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'plain_password')) {
                $table->string('plain_password')->nullable()->after('password');
            }
        });
    }

    public function down(): void
    {
        if (! config('tenancy.database_per_sahodaya', true)) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'plain_password')) {
                $table->dropColumn('plain_password');
            }
        });
    }
};
