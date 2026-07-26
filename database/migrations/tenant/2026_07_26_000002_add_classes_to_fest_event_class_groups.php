<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fest_event_class_groups') && ! Schema::hasColumn('fest_event_class_groups', 'classes')) {
            Schema::table('fest_event_class_groups', function (Blueprint $table) {
                // Class numbers (1-12) assigned to this category, e.g. [5,6,7] for a
                // "Junior" category covering classes 5-7. Matched against
                // FestStudentClassResolver::classNumberFromStudent() for eligibility.
                $table->json('classes')->nullable()->after('label');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fest_event_class_groups', 'classes')) {
            Schema::table('fest_event_class_groups', function (Blueprint $table) {
                $table->dropColumn('classes');
            });
        }
    }
};
