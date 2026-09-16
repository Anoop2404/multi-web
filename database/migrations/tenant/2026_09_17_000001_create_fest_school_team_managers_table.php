<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fest_school_team_managers')) {
            return;
        }

        Schema::create('fest_school_team_managers', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('event_id');
            $table->string('school_id');

            $table->string('manager_name_1');
            $table->string('manager_phone_1', 40);
            $table->string('manager_email_1')->nullable();
            $table->string('manager_role_1')->nullable();

            $table->string('manager_name_2')->nullable();
            $table->string('manager_phone_2', 40)->nullable();
            $table->string('manager_email_2')->nullable();
            $table->string('manager_role_2')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'school_id']);
            $table->index(['tenant_id', 'event_id']);
            $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_school_team_managers');
    }
};
