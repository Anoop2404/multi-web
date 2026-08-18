<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FRD-13 §4 platform dashboard KPIs. Students/teachers live in per-Sahodaya databases,
 * so counting them platform-wide means looping every Sahodaya's database — too slow to
 * do live on every dashboard request. A scheduled command computes one row here instead;
 * the dashboard just reads the latest row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_dashboard_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('total_students')->default(0);
            $table->unsignedInteger('total_teachers')->default(0);
            $table->decimal('revenue_this_month_inr', 12, 2)->default(0);
            $table->unsignedInteger('sahodayas_included')->default(0);
            $table->unsignedInteger('sahodayas_total')->default(0);
            $table->timestamp('computed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_dashboard_snapshots');
    }
};
