<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'school_prefix')) {
                $table->string('school_prefix', 10)->nullable()->after('parent_id');
            }
            if (! Schema::hasColumn('tenants', 'membership_status')) {
                $table->enum('membership_status', ['pending', 'approved', 'rejected'])->default('approved')->after('parent_id');
            }
            if (! Schema::hasColumn('tenants', 'application_payload')) {
                $table->json('application_payload')->nullable()->after('parent_id');
            }
            if (! Schema::hasColumn('tenants', 'prefixes_locked')) {
                $table->boolean('prefixes_locked')->default(false)->after('parent_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('tenants', 'school_prefix') ? 'school_prefix' : null,
                Schema::hasColumn('tenants', 'membership_status') ? 'membership_status' : null,
                Schema::hasColumn('tenants', 'application_payload') ? 'application_payload' : null,
                Schema::hasColumn('tenants', 'prefixes_locked') ? 'prefixes_locked' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
