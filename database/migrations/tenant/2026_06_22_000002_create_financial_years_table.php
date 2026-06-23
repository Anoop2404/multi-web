<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_years', function (Blueprint $table) {
            $table->id();
            $table->string('label', 10);    // e.g. 2025-26
            $table->date('start_date');     // April 1
            $table->date('end_date');       // March 31
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique('label');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_years');
    }
};
