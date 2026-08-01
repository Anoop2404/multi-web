<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sahodaya-wide toggles for topper reports (Toppers hub):
 * - use_common_ranking: one shared Top-N/tie-mode/rank-style config (the "overall" scope
 *   row in topper_count_configs) applies to every stream and subject, bypassing per-scope
 *   overrides. See TopperCountService::resolveCap/resolveTieMode/resolveRankStyle.
 * - no_rank: reports drop rank numbers entirely and just list students ordered by
 *   percentage descending. See SahodayaTopperSelectionService/SubjectMeritRegisterService.
 *
 * One row per sahodaya (mirrors ApiConfig's singleton-per-sahodaya pattern).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topper_ranking_settings', function (Blueprint $table) {
            $table->id();
            $table->string('sahodaya_id')->unique();
            $table->boolean('use_common_ranking')->default(false);
            $table->boolean('no_rank')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topper_ranking_settings');
    }
};
