<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('state_fest_participants', function (Blueprint $table) {
            $table->unsignedBigInteger('state_event_id')->nullable()->after('id');
            $table->foreign('state_event_id')->references('id')->on('state_fest_events')->cascadeOnDelete();
        });

        DB::table('state_fest_participants')
            ->orderBy('id')
            ->each(function ($participant) {
                $eventId = DB::table('state_fest_registrations')
                    ->where('id', $participant->registration_id)
                    ->value('state_event_id');

                if ($eventId) {
                    DB::table('state_fest_participants')
                        ->where('id', $participant->id)
                        ->update(['state_event_id' => $eventId]);
                }
            });

        Schema::table('state_fest_participants', function (Blueprint $table) {
            $table->unique(['state_event_id', 'chest_number'], 'state_participant_event_chest_unique');
            $table->index(['state_event_id', 'registration_id']);
        });
    }

    public function down(): void
    {
        Schema::table('state_fest_participants', function (Blueprint $table) {
            $table->dropUnique('state_participant_event_chest_unique');
            $table->dropIndex(['state_event_id', 'registration_id']);
            $table->dropForeign(['state_event_id']);
            $table->dropColumn('state_event_id');
        });
    }
};
