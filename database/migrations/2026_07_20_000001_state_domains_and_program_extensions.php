<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('state_domains')) {
            Schema::create('state_domains', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('tenant_id')->nullable();
                $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
                $table->string('name');
                $table->string('domain')->nullable();
                $table->string('api_base_url')->nullable();
                $table->string('api_client_id', 64)->nullable();
                $table->string('api_client_secret_hash', 128)->nullable();
                $table->string('status', 20)->default('active');
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('fest_state_programs', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_state_programs', 'state_domain_id')) {
                $table->uuid('state_domain_id')->nullable()->after('status');
            }
            if (! Schema::hasColumn('fest_state_programs', 'state_flow_mode')) {
                $table->string('state_flow_mode', 30)->default('state_domain_event')->after('state_domain_id');
            }
            if (! Schema::hasColumn('fest_state_programs', 'qualifier_policy')) {
                $table->json('qualifier_policy')->nullable()->after('state_flow_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fest_state_programs', function (Blueprint $table) {
            foreach (['qualifier_policy', 'state_flow_mode', 'state_domain_id'] as $col) {
                if (Schema::hasColumn('fest_state_programs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('state_domains');
    }
};
