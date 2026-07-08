<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('submission_teachers', 'subject_ids')) {
            Schema::table('submission_teachers', function (Blueprint $table) {
                $table->json('subject_ids')->nullable()->after('subject');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('submission_teachers', 'subject_ids')) {
            Schema::table('submission_teachers', function (Blueprint $table) {
                $table->dropColumn('subject_ids');
            });
        }
    }
};
