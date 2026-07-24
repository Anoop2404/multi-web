<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mcq_registrations', function (Blueprint $table) {
            $table->string('rejection_reason', 500)->nullable()->after('approval_status');
        });

        Schema::table('training_registrations', function (Blueprint $table) {
            $table->string('rejection_reason', 500)->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mcq_and_training_registrations', function (Blueprint $table) {
            //
        });
    }
};
