<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_registrations')) {
            return;
        }

        if (Schema::hasColumn('training_registrations', 'school_id')) {
            Schema::table('training_registrations', function (Blueprint $table) {
                $table->string('school_id')->nullable()->change();
            });
        }

        // Clear Sahodaya-as-school placeholder used for pending-school QR rows.
        if (! Schema::hasTable('training_programs')) {
            return;
        }

        $pairs = DB::table('training_registrations as r')
            ->join('training_programs as p', 'p.id', '=', 'r.program_id')
            ->whereNotNull('r.pending_school_id')
            ->whereColumn('r.school_id', 'p.tenant_id')
            ->pluck('r.id');

        if ($pairs->isNotEmpty()) {
            DB::table('training_registrations')
                ->whereIn('id', $pairs)
                ->update(['school_id' => null]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('training_registrations') || ! Schema::hasColumn('training_registrations', 'school_id')) {
            return;
        }

        Schema::table('training_registrations', function (Blueprint $table) {
            $table->string('school_id')->nullable(false)->change();
        });
    }
};
