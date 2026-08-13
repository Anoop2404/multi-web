<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('state.connection', 'state');
        $schema = Schema::connection($connection);

        if (! $schema->hasTable('state_fest_marks')) {
            return;
        }

        DB::connection($connection)
            ->table('state_fest_marks')
            ->whereNotNull('registration_id')
            ->select('state_event_id', 'registration_id')
            ->groupBy('state_event_id', 'registration_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(function ($duplicate) use ($connection) {
                $ids = DB::connection($connection)
                    ->table('state_fest_marks')
                    ->where('state_event_id', $duplicate->state_event_id)
                    ->where('registration_id', $duplicate->registration_id)
                    ->orderBy('id')
                    ->pluck('id');

                DB::connection($connection)
                    ->table('state_fest_marks')
                    ->whereIn('id', $ids->slice(1))
                    ->delete();
            });

        $schema->table('state_fest_marks', function (Blueprint $table) {
            $table->unique(['state_event_id', 'registration_id'], 'state_mark_event_registration_unique');
        });
    }

    public function down(): void
    {
        $connection = config('state.connection', 'state');
        $schema = Schema::connection($connection);

        if ($schema->hasTable('state_fest_marks')) {
            $schema->table('state_fest_marks', function (Blueprint $table) {
                $table->dropUnique('state_mark_event_registration_unique');
            });
        }
    }
};
