<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_event_items', function (Blueprint $table) {
            $table->string('item_code', 20)->nullable()->after('title');
            $table->string('stage_type', 20)->nullable()->after('category'); // on_stage | off_stage
            $table->string('venue_type', 20)->nullable()->after('stage_type'); // indoor | outdoor
            $table->string('competition_format', 30)->nullable()->after('venue_type'); // singles, doubles, mixed_doubles, team, relay, group, individual, board_game
            $table->string('sport_discipline', 40)->nullable()->after('competition_format'); // track, field, team_game, racket, board_game, martial_arts, aquatics
            $table->unsignedSmallInteger('duration_minutes')->nullable()->after('sport_discipline');
            $table->json('criteria_json')->nullable()->after('duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('fest_event_items', function (Blueprint $table) {
            $table->dropColumn([
                'item_code', 'stage_type', 'venue_type', 'competition_format',
                'sport_discipline', 'duration_minutes', 'criteria_json',
            ]);
        });
    }
};
