<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_result_marks_configs', function (Blueprint $table) {
            $table->id();
            $table->string('sahodaya_id')->index();
            $table->unsignedTinyInteger('class'); // 10 or 12
            // null for Class X (no streams); a specific exam_streams.id for each Class XII stream.
            $table->unsignedBigInteger('stream_id')->nullable();
            $table->unsignedInteger('total_marks')->default(500);
            $table->timestamps();
            $table->unique(['sahodaya_id', 'class', 'stream_id'], 'board_result_marks_configs_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_result_marks_configs');
    }
};
