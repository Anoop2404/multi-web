<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_classes', function (Blueprint $table) {
            $table->dropForeign(['class_category_id']);
        });

        Schema::rename('class_categories', 'school_class_groups');

        Schema::table('school_classes', function (Blueprint $table) {
            $table->renameColumn('class_category_id', 'school_class_group_id');
        });

        Schema::table('school_classes', function (Blueprint $table) {
            $table->foreign('school_class_group_id')
                ->references('id')
                ->on('school_class_groups')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('school_classes', function (Blueprint $table) {
            $table->dropForeign(['school_class_group_id']);
        });

        Schema::table('school_classes', function (Blueprint $table) {
            $table->renameColumn('school_class_group_id', 'class_category_id');
        });

        Schema::rename('school_class_groups', 'class_categories');

        Schema::table('school_classes', function (Blueprint $table) {
            $table->foreign('class_category_id')
                ->references('id')
                ->on('class_categories')
                ->nullOnDelete();
        });
    }
};
