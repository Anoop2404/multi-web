<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_categories')) {
            Schema::create('training_categories', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->string('code', 64);
                $table->string('label');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('display_order')->default(0);
                $table->timestamps();

                $table->unique(['tenant_id', 'code']);
                $table->index(['tenant_id', 'is_active', 'display_order']);
            });
        }

        if (Schema::hasTable('training_programs') && ! Schema::hasColumn('training_programs', 'category_id')) {
            Schema::table('training_programs', function (Blueprint $table) {
                $table->foreignId('category_id')
                    ->nullable()
                    ->after('academic_year_id')
                    ->constrained('training_categories')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('training_programs') && Schema::hasColumn('training_programs', 'category_id')) {
            Schema::table('training_programs', function (Blueprint $table) {
                $table->dropConstrainedForeignId('category_id');
            });
        }

        Schema::dropIfExists('training_categories');
    }
};
