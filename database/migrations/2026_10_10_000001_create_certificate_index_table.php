<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_index', function (Blueprint $table) {
            $table->id();
            $table->uuid('verification_uuid')->unique();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            // 'certificates' | 'mcq_certificates' — which tenant-scoped table this uuid
            // actually lives in, so PublicCertificateController can dispatch without a
            // second lookup.
            $table->string('source_table', 32);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_index');
    }
};
