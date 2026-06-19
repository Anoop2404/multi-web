<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'admission_number']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('admission_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('admission_number')->nullable(false)->change();
            $table->unique(['tenant_id', 'admission_number']);
        });
    }
};
