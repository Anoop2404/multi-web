<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('mcq_exams')
            ->where(function ($q) {
                $q->whereNull('fee_type')
                    ->orWhere('fee_type', 'none');
            })
            ->update(['fee_type' => 'flat']);

        if (\Illuminate\Support\Facades\Schema::hasColumn('mcq_registrations', 'approval_status')) {
            DB::table('mcq_registrations')
                ->where('approval_status', 'pending_approval')
                ->update(['approval_status' => 'pending_payment']);
        }
    }

    public function down(): void
    {
        // Irreversible business rule change.
    }
};
