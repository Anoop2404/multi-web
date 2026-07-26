<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_event_class_groups')) {
            Schema::create('fest_event_class_groups', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->unsignedBigInteger('event_id');
                // Machine key used as the class_group value stored on items/registrations —
                // e.g. "cat_a". Free-form (not the fixed lp/up/hs/hss/open set) since this
                // is a Sahodaya-defined custom category, not one of the built-in schemes.
                $table->string('key');
                $table->string('label');
                $table->text('description')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
                $table->unique(['event_id', 'key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_event_class_groups');
    }
};
