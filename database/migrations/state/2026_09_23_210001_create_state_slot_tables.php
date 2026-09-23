<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 of the State Kalotsav module — per-Sahodaya slot overrides and their history.
 *
 * The State's rule is "each Sahodaya gets N slots per item", carried today by the item itself
 * (qualify_count, with max_per_school as an item-wide override). What has been missing is the third
 * level the spec calls for: a slot count for ONE Sahodaya on ONE item — a Sahodaya granted an extra
 * place, or held to fewer after a ruling.
 *
 * Overrides are a small, high-consequence dial: they decide who may compete. So every change is
 * appended to state_slot_audit_entries with who changed it, from what, to what and why, and the
 * audit table is never updated or deleted from — a quota that moved and left no trace is exactly
 * the thing an appeal will ask about.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('state_sahodaya_item_slots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('state_program_id');
            $table->uuid('state_id')->nullable()->index();
            $table->uuid('item_id');
            // The canonical directory id from Phase 1, never a raw submission key — an override must
            // survive the Sahodaya being promoted.
            $table->uuid('sahodaya_id');
            $table->unsignedSmallInteger('slots');
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('set_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['state_program_id', 'item_id', 'sahodaya_id']);
            $table->index(['state_program_id', 'sahodaya_id']);
        });

        Schema::create('state_slot_audit_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('state_program_id');
            $table->uuid('state_id')->nullable()->index();
            $table->uuid('item_id')->nullable();
            // Null means the change was made at item level and applies to every Sahodaya.
            $table->uuid('sahodaya_id')->nullable();
            $table->string('scope', 20); // item | sahodaya
            $table->unsignedSmallInteger('slots_from')->nullable();
            $table->unsignedSmallInteger('slots_to')->nullable();
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('changed_by_user_id')->nullable();
            $table->string('changed_by_name')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['state_program_id', 'item_id']);
            $table->index(['state_program_id', 'sahodaya_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('state_slot_audit_entries');
        Schema::dropIfExists('state_sahodaya_item_slots');
    }
};
