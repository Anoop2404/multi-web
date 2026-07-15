<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fest_competition_types')) {
            DB::table('fest_competition_types')
                ->where('type_key', 'sports')
                ->update(['is_singleton' => false]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fest_competition_types')) {
            DB::table('fest_competition_types')
                ->where('type_key', 'sports')
                ->update(['is_singleton' => true]);
        }
    }
};
