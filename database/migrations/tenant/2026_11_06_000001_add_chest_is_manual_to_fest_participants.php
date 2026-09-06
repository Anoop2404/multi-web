<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_participants', function (Blueprint $table) {
            // Distinguishes an organizer-typed chest number from one FestNumberingService
            // auto-assigned. Bulk "assign missing" already only fills null chest_no rows,
            // but a manual entry must additionally block direct overwrite (via the same
            // "set" endpoint) until cleared -- otherwise two organizers, or an auto-assign
            // running against a differently-scoped item, could silently replace a number a
            // judge already has on a printed sheet.
            $table->boolean('chest_is_manual')->default(false)->after('chest_no');
        });
    }

    public function down(): void
    {
        Schema::table('fest_participants', function (Blueprint $table) {
            $table->dropColumn('chest_is_manual');
        });
    }
};
