<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scanned/photographed copies of the physically-signed judge mark sheet,
 * attached per item as an audit-trail record. No automatic data extraction —
 * this is purely a stored document, distinct from the digital mark entry
 * (FestMark) and criteria scores (FestMarkCriterionScore).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fest_mark_sheet_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('fest_events')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('fest_event_items')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_mark_sheet_uploads');
    }
};
