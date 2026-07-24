<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a `category` column to the central `subjects` master table so subjects can be grouped
 * the way CBSE actually groups them: language (Category I — every student picks 2), a stream
 * elective (Category II — science/commerce/humanities, student picks 3 from their stream's
 * pool), or skill (Category III — fully optional additional subject). Purely additive/nullable
 * — existing rows and every reader of this table keep working unchanged; SahodayaMasterDataSeeder
 * is updated separately to populate it going forward.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('category', 20)->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
