<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_heads', function (Blueprint $table) {
            if (! Schema::hasColumn('account_heads', 'category')) {
                $table->string('category', 32)->nullable()->after('type');
                $table->index(['tenant_id', 'category']);
            }
            if (! Schema::hasColumn('account_heads', 'event_id')) {
                $table->unsignedBigInteger('event_id')->nullable()->after('category');
                $table->foreign('event_id')->references('id')->on('fest_events')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('account_heads', function (Blueprint $table) {
            if (Schema::hasColumn('account_heads', 'event_id')) {
                $table->dropIndex(['event_id']);
                $table->dropColumn('event_id');
            }
            if (Schema::hasColumn('account_heads', 'category')) {
                $table->dropIndex(['tenant_id', 'category']);
                $table->dropColumn('category');
            }
        });
    }
};
