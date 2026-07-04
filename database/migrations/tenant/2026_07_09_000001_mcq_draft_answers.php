<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mcq_registrations', function (Blueprint $table) {
            $table->json('draft_answers')->nullable()->after('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('mcq_registrations', function (Blueprint $table) {
            $table->dropColumn('draft_answers');
        });
    }
};
