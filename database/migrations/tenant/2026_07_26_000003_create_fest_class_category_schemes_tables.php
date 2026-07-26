<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A named, Sahodaya-wide (not per-event) class category setup — e.g. "CBSE Kerala
        // (Category I-IV)", "English Fest". Events pick one of these by id instead of the old
        // fixed cbse/sahodaya/cluster/custom choices. See App\Support\FestClassGroupScheme.
        if (! Schema::hasTable('fest_class_category_schemes')) {
            Schema::create('fest_class_category_schemes', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->string('name');
                $table->text('description')->nullable();
                // Which scheme an event falls back to when it has no explicit override —
                // mirrors the role SahodayaProfile::fest_class_group_scheme used to play for
                // the old config-based schemes.
                $table->boolean('is_default')->default(false);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['tenant_id', 'name']);
            });
        }

        // Categories within a scheme (e.g. "Junior", "Senior") — same shape as the per-event
        // fest_event_class_groups table this supersedes, just keyed by scheme_id instead of
        // event_id so the same named category list can be reused across many events.
        if (! Schema::hasTable('fest_class_category_scheme_groups')) {
            Schema::create('fest_class_category_scheme_groups', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->unsignedBigInteger('scheme_id');
                $table->string('key');
                $table->string('label');
                $table->text('description')->nullable();
                $table->json('classes')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->foreign('scheme_id')->references('id')->on('fest_class_category_schemes')->cascadeOnDelete();
                $table->unique(['scheme_id', 'key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_class_category_scheme_groups');
        Schema::dropIfExists('fest_class_category_schemes');
    }
};
