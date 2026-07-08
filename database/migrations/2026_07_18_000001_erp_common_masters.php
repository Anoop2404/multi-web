<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('sahodaya_id')->nullable();
            $table->foreign('sahodaya_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('label');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['sahodaya_id', 'code']);
        });

        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->string('sahodaya_id')->nullable();
            $table->foreign('sahodaya_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('label');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['sahodaya_id', 'code']);
        });

        Schema::create('age_categories', function (Blueprint $table) {
            $table->id();
            $table->string('sahodaya_id')->nullable();
            $table->foreign('sahodaya_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('label');
            $table->unsignedTinyInteger('max_age');
            $table->string('cutoff_date', 10)->default('12-31');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['sahodaya_id', 'code']);
        });

        Schema::table('teaching_types', function (Blueprint $table) {
            if (! Schema::hasColumn('teaching_types', 'min_class')) {
                $table->unsignedTinyInteger('min_class')->nullable()->after('label');
            }
            if (! Schema::hasColumn('teaching_types', 'max_class')) {
                $table->unsignedTinyInteger('max_class')->nullable()->after('min_class');
            }
        });
    }

    public function down(): void
    {
        Schema::table('teaching_types', function (Blueprint $table) {
            foreach (['min_class', 'max_class'] as $col) {
                if (Schema::hasColumn('teaching_types', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('age_categories');
        Schema::dropIfExists('designations');
        Schema::dropIfExists('subjects');
    }
};
