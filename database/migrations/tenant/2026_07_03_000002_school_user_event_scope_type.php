<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('school_user_event_scopes') && ! Schema::hasColumn('school_user_event_scopes', 'scope_type')) {
            Schema::table('school_user_event_scopes', function (Blueprint $table) {
                $table->enum('scope_type', ['program', 'fest_event', 'mcq_exam', 'training_program'])
                    ->default('program')
                    ->after('program_slug');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('school_user_event_scopes') && Schema::hasColumn('school_user_event_scopes', 'scope_type')) {
            Schema::table('school_user_event_scopes', function (Blueprint $table) {
                $table->dropColumn('scope_type');
            });
        }
    }
};
