<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Removes the manual-chest-number-entry feature: organizers asked to drop it in
        // favor of always using auto-generation/clear, no custom typed-in numbers. A new
        // migration rather than deleting 2026_11_06_000001's ADD migration, since some
        // environments may have already run that one — this cleanly undoes it wherever
        // it landed instead of leaving the column behind.
        Schema::table('fest_participants', function (Blueprint $table) {
            if (Schema::hasColumn('fest_participants', 'chest_is_manual')) {
                $table->dropColumn('chest_is_manual');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fest_participants', function (Blueprint $table) {
            $table->boolean('chest_is_manual')->default(false)->after('chest_no');
        });
    }
};
