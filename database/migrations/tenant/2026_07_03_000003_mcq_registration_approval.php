<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mcq_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('mcq_registrations', 'approval_status')) {
                $table->enum('approval_status', [
                    'pending_payment',
                    'pending_approval',
                    'approved',
                    'rejected',
                ])->default('pending_payment')->after('status');
            }
            if (! Schema::hasColumn('mcq_registrations', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approval_status');
            }
            if (! Schema::hasColumn('mcq_registrations', 'approved_by_user_id')) {
                $table->unsignedBigInteger('approved_by_user_id')->nullable()->after('approved_at');
            }
        });

        if (Schema::hasColumn('mcq_registrations', 'approval_status')) {
            DB::table('mcq_registrations')
                ->whereNotNull('hall_ticket_no')
                ->update(['approval_status' => 'approved']);

            $noFeeExamIds = DB::table('mcq_exams')
                ->where(function ($q) {
                    $q->where('fee_type', 'none')
                        ->orWhereNull('fee_amount')
                        ->orWhere('fee_amount', '<=', 0);
                })
                ->pluck('id');

            if ($noFeeExamIds->isNotEmpty()) {
                DB::table('mcq_registrations')
                    ->whereNull('hall_ticket_no')
                    ->whereIn('exam_id', $noFeeExamIds)
                    ->update(['approval_status' => 'pending_approval']);
            }
        }
    }

    public function down(): void
    {
        Schema::table('mcq_registrations', function (Blueprint $table) {
            foreach (['approved_by_user_id', 'approved_at', 'approval_status'] as $col) {
                if (Schema::hasColumn('mcq_registrations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
