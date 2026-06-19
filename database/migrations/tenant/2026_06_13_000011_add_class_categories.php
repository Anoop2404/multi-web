<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_categories', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('name');
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::table('school_classes', function (Blueprint $table) {
            $table->unsignedBigInteger('class_category_id')->nullable()->after('tenant_id');
            $table->foreign('class_category_id')->references('id')->on('class_categories')->nullOnDelete();
        });

        $tenantIds = DB::table('school_classes')->distinct()->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            $categoryId = DB::table('class_categories')->insertGetId([
                'tenant_id'     => $tenantId,
                'name'          => 'General',
                'display_order' => 0,
                'is_active'     => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            DB::table('school_classes')
                ->where('tenant_id', $tenantId)
                ->whereNull('class_category_id')
                ->update(['class_category_id' => $categoryId]);
        }
    }

    public function down(): void
    {
        Schema::table('school_classes', function (Blueprint $table) {
            $table->dropForeign(['class_category_id']);
            $table->dropColumn('class_category_id');
        });

        Schema::dropIfExists('class_categories');
    }
};
