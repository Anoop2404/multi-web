<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('state')
            ->table('state_fest_marks')
            ->whereNotNull('registration_id')
            ->select('state_event_id', 'registration_id')
            ->groupBy('state_event_id', 'registration_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(function ($duplicate) {
                $ids = DB::connection('state')
                    ->table('state_fest_marks')
                    ->where('state_event_id', $duplicate->state_event_id)
                    ->where('registration_id', $duplicate->registration_id)
                    ->orderBy('id')
                    ->pluck('id');

                DB::connection('state')
                    ->table('state_fest_marks')
                    ->whereIn('id', $ids->slice(1))
                    ->delete();
            });

        Schema::table('state_fest_marks', function (Blueprint $table) {
            $table->unique(['state_event_id', 'registration_id'], 'state_mark_event_registration_unique');
        });
    }

    public function down(): void
    {
        Schema::table('state_fest_marks', function (Blueprint $table) {
            $table->dropUnique('state_mark_event_registration_unique');
        });
    }
};
