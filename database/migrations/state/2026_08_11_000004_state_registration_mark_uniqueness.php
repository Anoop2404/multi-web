<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('state.connection', 'state');
        $schema = Schema::connection($connection);

        if ($schema->hasTable('state_fest_registrations')) {
            $schema->table('state_fest_registrations', function (Blueprint $table) {
                $table->unique('qualifier_entry_id', 'state_registration_qualifier_unique');
            });
        }

        if ($schema->hasTable('state_fest_marks')) {
            $schema->table('state_fest_marks', function (Blueprint $table) {
                $table->unique(['state_event_id', 'participant_id'], 'state_mark_event_participant_unique');
            });
        }
    }

    public function down(): void
    {
        $connection = config('state.connection', 'state');
        $schema = Schema::connection($connection);

        if ($schema->hasTable('state_fest_marks')) {
            $schema->table('state_fest_marks', function (Blueprint $table) {
                $table->dropUnique('state_mark_event_participant_unique');
            });
        }

        if ($schema->hasTable('state_fest_registrations')) {
            $schema->table('state_fest_registrations', function (Blueprint $table) {
                $table->dropUnique('state_registration_qualifier_unique');
            });
        }
    }
};
