<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fest_certificate_phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('fest_events')->cascadeOnDelete();
            $table->string('name');
            $table->string('venue');
            $table->date('date_start');
            $table->date('date_end')->nullable();
            $table->timestamps();
        });
        Schema::table('fest_event_items', function (Blueprint $table) {
            $table->foreignId('certificate_phase_id')->nullable()->constrained('fest_certificate_phases')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fest_event_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('certificate_phase_id'));
        Schema::dropIfExists('fest_certificate_phases');
    }
};
