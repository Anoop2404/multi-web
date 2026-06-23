<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('label', 10);               // e.g. 2025-26
            $table->date('start_date');                // June 1 of start year
            $table->date('end_date');                  // May 31 of end year
            $table->enum('status', ['upcoming', 'active', 'closed'])->default('upcoming');
            $table->unsignedBigInteger('opened_by')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique('label');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};
