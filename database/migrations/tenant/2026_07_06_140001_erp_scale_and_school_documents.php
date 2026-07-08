<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (! $this->indexExists('students', 'students_tenant_class_idx')) {
                $table->index(['tenant_id', 'school_class_id'], 'students_tenant_class_idx');
            }
            if (! $this->indexExists('students', 'students_tenant_status_idx')) {
                $table->index(['tenant_id', 'status'], 'students_tenant_status_idx');
            }
            if (! $this->indexExists('students', 'students_verified_at_idx')) {
                $table->index(['verified_at'], 'students_verified_at_idx');
            }
            if (! $this->indexExists('students', 'students_name_idx')) {
                $table->index(['tenant_id', 'name'], 'students_name_idx');
            }
        });

        if (! Schema::hasTable('school_document_types')) {
            Schema::create('school_document_types', function (Blueprint $table) {
                $table->id();
                $table->string('sahodaya_id');
                $table->string('code', 40);
                $table->string('name');
                $table->boolean('is_required')->default(true);
                $table->unsignedSmallInteger('validity_months')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['sahodaya_id', 'code']);
            });
        }

        if (! Schema::hasTable('school_documents')) {
            Schema::create('school_documents', function (Blueprint $table) {
                $table->id();
                $table->string('school_id');
                $table->unsignedBigInteger('document_type_id');
                $table->string('file_path');
                $table->string('file_name')->nullable();
                $table->date('valid_from')->nullable();
                $table->date('valid_to')->nullable();
                $table->string('status', 20)->default('pending');
                $table->text('rejection_reason')->nullable();
                $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
                $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['school_id', 'status']);
                $table->foreign('document_type_id')->references('id')->on('school_document_types')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('school_documents');
        Schema::dropIfExists('school_document_types');

        Schema::table('students', function (Blueprint $table) {
            foreach (['students_tenant_class_idx', 'students_tenant_status_idx', 'students_verified_at_idx', 'students_name_idx'] as $idx) {
                if ($this->indexExists('students', $idx)) {
                    $table->dropIndex($idx);
                }
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'pgsql') {
            $result = $connection->select(
                'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?',
                [$table, $index]
            );

            return count($result) > 0;
        }

        if ($driver === 'mysql') {
            $result = $connection->select(
                'SHOW INDEX FROM '.$table.' WHERE Key_name = ?',
                [$index]
            );

            return count($result) > 0;
        }

        return false;
    }
};
