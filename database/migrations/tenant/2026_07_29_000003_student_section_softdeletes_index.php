<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('students')) {
            return;
        }

        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        if (! Schema::hasIndex('students', 'students_tenant_class_status_index')) {
            Schema::table('students', function (Blueprint $table) {
                $table->index(['tenant_id', 'school_class_id', 'status'], 'students_tenant_class_status_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('students')) {
            return;
        }

        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasIndex('students', 'students_tenant_class_status_index')) {
                $table->dropIndex('students_tenant_class_status_index');
            }
            if (Schema::hasColumn('students', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
