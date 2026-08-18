<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('states', function (Blueprint $table) {
            $table->string('contact_name')->nullable()->after('name');
            $table->string('contact_email')->nullable()->after('contact_name');
            $table->string('contact_phone', 30)->nullable()->after('contact_email');
            $table->json('branding')->nullable()->after('contact_phone');
            $table->string('default_academic_year', 20)->nullable()->after('branding');
            $table->string('financial_year_start_month', 20)->nullable()->after('default_academic_year');
        });
    }

    public function down(): void
    {
        Schema::table('states', function (Blueprint $table) {
            $table->dropColumn([
                'contact_name', 'contact_email', 'contact_phone',
                'branding', 'default_academic_year', 'financial_year_start_month',
            ]);
        });
    }
};
