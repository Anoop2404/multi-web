<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_batches', function (Blueprint $table) {
            if (! Schema::hasColumn('certificate_batches', 'training_program_id')) {
                $table->unsignedBigInteger('training_program_id')->nullable()->after('event_id');
                $table->foreign('training_program_id')->references('id')->on('training_programs')->nullOnDelete();
            }

            // Training's frozen-scope list is registration ids, not certificate ids — not
            // every registration has a Certificate row yet at dispatch time, unlike Fest's
            // certificate_ids_json which always refers to already-existing rows.
            if (! Schema::hasColumn('certificate_batches', 'registration_ids_json')) {
                $table->json('registration_ids_json')->nullable()->after('certificate_ids_json');
            }
        });

        if (! $this->hasIndex('certificate_batches', 'certificate_batches_training_program_id_status_index')) {
            Schema::table('certificate_batches', function (Blueprint $table) {
                $table->index(['training_program_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('certificate_batches', 'certificate_batches_training_program_id_status_index')) {
            Schema::table('certificate_batches', function (Blueprint $table) {
                $table->dropIndex('certificate_batches_training_program_id_status_index');
            });
        }

        Schema::table('certificate_batches', function (Blueprint $table) {
            if (Schema::hasColumn('certificate_batches', 'training_program_id')) {
                $table->dropForeign(['training_program_id']);
                $table->dropColumn('training_program_id');
            }
            if (Schema::hasColumn('certificate_batches', 'registration_ids_json')) {
                $table->dropColumn('registration_ids_json');
            }
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))->pluck('name')->contains($indexName);
    }
};
