<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual "certificates handed over / downloaded" tick per school and certificate type, so
 * the certificates page can show which schools' bundles have already been collected. It is
 * purely a checklist the Sahodaya admin sets by hand -- downloading a ZIP does not touch it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fest_certificate_school_marks')) {
            return;
        }

        Schema::create('fest_certificate_school_marks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->string('school_id', 64);
            $table->string('cert_type', 32);
            $table->unsignedBigInteger('marked_by_user_id')->nullable();
            $table->timestamp('marked_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'school_id', 'cert_type'], 'fest_cert_school_marks_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_certificate_school_marks');
    }
};
