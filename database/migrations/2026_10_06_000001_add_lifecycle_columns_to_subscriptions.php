<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->unsignedSmallInteger('grace_period_days')->default(14)->after('billing_period');
        });

        Schema::table('tenant_subscriptions', function (Blueprint $table) {
            $table->boolean('auto_renew')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_subscriptions', function (Blueprint $table) {
            $table->dropColumn('auto_renew');
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('grace_period_days');
        });
    }
};
