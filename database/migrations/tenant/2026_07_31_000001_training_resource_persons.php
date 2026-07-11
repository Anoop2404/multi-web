<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_resource_persons')) {
            Schema::create('training_resource_persons', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('mobile', 32)->nullable();
                $table->string('designation')->nullable();
                $table->text('bio')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('training_program_resource_person')) {
            Schema::create('training_program_resource_person', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('program_id');
                $table->foreign('program_id')->references('id')->on('training_programs')->cascadeOnDelete();
                $table->unsignedBigInteger('resource_person_id');
                $table->foreign('resource_person_id')->references('id')->on('training_resource_persons')->cascadeOnDelete();
                $table->decimal('honorarium', 12, 2)->nullable();
                $table->string('role')->nullable();
                $table->timestamps();

                $table->unique(['program_id', 'resource_person_id'], 'trn_prog_rp_unique');
            });
        }

        if (Schema::hasTable('training_sessions') && ! Schema::hasColumn('training_sessions', 'resource_person_id')) {
            Schema::table('training_sessions', function (Blueprint $table) {
                $table->unsignedBigInteger('resource_person_id')->nullable()->after('duration_minutes');
                $table->foreign('resource_person_id')
                    ->references('id')
                    ->on('training_resource_persons')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('training_sessions') && Schema::hasColumn('training_sessions', 'resource_person_id')) {
            Schema::table('training_sessions', function (Blueprint $table) {
                $table->dropForeign(['resource_person_id']);
                $table->dropColumn('resource_person_id');
            });
        }

        Schema::dropIfExists('training_program_resource_person');
        Schema::dropIfExists('training_resource_persons');
    }
};
