<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('state_remittance_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('state_remittance_id');
            $table->foreign('state_remittance_id')->references('id')->on('state_remittances')->cascadeOnDelete();
            $table->string('line_type', 40); // 'sahodaya_registration' | 'item_fee'
            $table->string('label');
            $table->uuid('state_program_item_id')->nullable();
            $table->foreign('state_program_item_id')->references('id')->on('fest_state_program_items')->nullOnDelete();
            $table->string('item_code', 64)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_amount', 10, 2)->default(0);
            $table->decimal('amount', 10, 2)->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('state_remittance_id');
            $table->index('state_program_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('state_remittance_lines');
    }
};
