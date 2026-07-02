<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_school_event_fees') || ! Schema::hasTable('fest_registrations')) {
            return;
        }

        $groups = DB::table('fest_registrations')
            ->whereNotNull('fee_receipt_id')
            ->select('event_id', 'school_id')
            ->distinct()
            ->get();

        foreach ($groups as $group) {
            $exists = DB::table('fest_school_event_fees')
                ->where('event_id', $group->event_id)
                ->where('school_id', $group->school_id)
                ->exists();

            if ($exists) {
                continue;
            }

            $regs = DB::table('fest_registrations')
                ->where('event_id', $group->event_id)
                ->where('school_id', $group->school_id)
                ->whereIn('status', ['submitted', 'approved'])
                ->count();

            $receiptId = DB::table('fest_registrations')
                ->where('event_id', $group->event_id)
                ->where('school_id', $group->school_id)
                ->whereNotNull('fee_receipt_id')
                ->value('fee_receipt_id');

            $amount = $receiptId
                ? (float) DB::table('fee_receipts')->where('id', $receiptId)->value('amount')
                : 0;

            $status = 'pending';
            if ($receiptId) {
                $receiptStatus = DB::table('fee_receipts')->where('id', $receiptId)->value('status');
                $status = match ($receiptStatus) {
                    'approved' => 'approved',
                    'uploaded' => 'proof_uploaded',
                    'rejected' => 'rejected',
                    default => 'pending',
                };
            }

            $schoolFeeId = DB::table('fest_school_event_fees')->insertGetId([
                'event_id' => $group->event_id,
                'school_id' => $group->school_id,
                'school_registration_fee' => 0,
                'participation_item_count' => $regs,
                'participation_fee' => $amount,
                'total_due' => $amount,
                'fee_receipt_id' => $receiptId,
                'status' => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($receiptId) {
                DB::table('fee_receipts')->where('id', $receiptId)->update([
                    'feeable_type' => 'App\\Models\\FestSchoolEventFee',
                    'feeable_id' => $schoolFeeId,
                ]);
            }
        }
    }

    public function down(): void
    {
        // non-destructive
    }
};
