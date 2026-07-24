<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a school attach more than one image to a single payment proof upload (e.g. a UTR
 * screenshot + a bank statement page, both evidencing the same payment) without changing
 * what a FeeReceipt *is* — one receipt is still one payment, reviewed/approved/rejected as
 * one unit. `fee_receipts.file_path` stays the primary/first image (every existing reader
 * of that column — receipt PDFs, review pages, proof links — keeps working unchanged);
 * this table holds any additional images for that same receipt.
 *
 * See docs/FLOW_GAP_FIX_PLAN.md — multi-image payment proof upload feature (24 Jul 2026).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_receipt_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_receipt_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_filename')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['fee_receipt_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_receipt_attachments');
    }
};
