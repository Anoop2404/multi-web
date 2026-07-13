<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('domains')) {
            return;
        }

        Schema::table('domains', function (Blueprint $table) {
            if (! Schema::hasColumn('domains', 'is_primary')) {
                $table->boolean('is_primary')->default(false)->after('tenant_id');
            }
            if (! Schema::hasColumn('domains', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('is_primary');
            }
            if (! Schema::hasColumn('domains', 'dns_token')) {
                $table->string('dns_token', 64)->nullable()->after('verified_at');
            }
            if (! Schema::hasColumn('domains', 'ssl_status')) {
                $table->string('ssl_status', 30)->nullable()->after('dns_token');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('domains')) {
            return;
        }

        Schema::table('domains', function (Blueprint $table) {
            foreach (['is_primary', 'verified_at', 'dns_token', 'ssl_status'] as $col) {
                if (Schema::hasColumn('domains', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
