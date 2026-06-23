<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('feeable_type');
            $table->unsignedBigInteger('feeable_id');
            $table->string('file_path');
            $table->string('transaction_ref')->nullable();
            $table->string('bank_name')->nullable();
            $table->date('payment_date')->nullable();
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['uploaded', 'approved', 'rejected'])->default('uploaded');
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['feeable_type', 'feeable_id']);
            $table->index('status');
        });

        Schema::table('membership_fee_slabs', function (Blueprint $table) {
            if (! Schema::hasColumn('membership_fee_slabs', 'academic_year_id')) {
                $table->unsignedBigInteger('academic_year_id')->nullable()->after('academic_year');
                $table->foreign('academic_year_id')->references('id')->on('academic_years')->nullOnDelete();
            }
            if (! Schema::hasColumn('membership_fee_slabs', 'due_date')) {
                $table->date('due_date')->nullable()->after('amount');
            }
        });

        Schema::table('sahodaya_registration_windows', function (Blueprint $table) {
            if (! Schema::hasColumn('sahodaya_registration_windows', 'academic_year_id')) {
                $table->unsignedBigInteger('academic_year_id')->nullable()->after('academic_year');
                $table->foreign('academic_year_id')->references('id')->on('academic_years')->nullOnDelete();
            }
        });

        Schema::table('membership_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('membership_payments', 'fee_receipt_id')) {
                $table->unsignedBigInteger('fee_receipt_id')->nullable()->after('registration_id');
                $table->foreign('fee_receipt_id')->references('id')->on('fee_receipts')->nullOnDelete();
            }
        });

        $this->backfillAcademicYearIds();
        $this->backfillFeeReceipts();
    }

    public function down(): void
    {
        Schema::table('membership_payments', function (Blueprint $table) {
            if (Schema::hasColumn('membership_payments', 'fee_receipt_id')) {
                $table->dropForeign(['fee_receipt_id']);
                $table->dropColumn('fee_receipt_id');
            }
        });

        Schema::table('sahodaya_registration_windows', function (Blueprint $table) {
            if (Schema::hasColumn('sahodaya_registration_windows', 'academic_year_id')) {
                $table->dropForeign(['academic_year_id']);
                $table->dropColumn('academic_year_id');
            }
        });

        Schema::table('membership_fee_slabs', function (Blueprint $table) {
            if (Schema::hasColumn('membership_fee_slabs', 'academic_year_id')) {
                $table->dropForeign(['academic_year_id']);
                $table->dropColumn('academic_year_id');
            }
            if (Schema::hasColumn('membership_fee_slabs', 'due_date')) {
                $table->dropColumn('due_date');
            }
        });

        Schema::dropIfExists('fee_receipts');
    }

    private function backfillAcademicYearIds(): void
    {
        if (! Schema::hasTable('academic_years')) {
            return;
        }

        foreach (DB::table('membership_fee_slabs')->whereNull('academic_year_id')->get() as $slab) {
            $yearId = DB::table('academic_years')->where('label', $slab->academic_year)->value('id');
            if ($yearId) {
                DB::table('membership_fee_slabs')->where('id', $slab->id)->update(['academic_year_id' => $yearId]);
            }
        }

        foreach (DB::table('sahodaya_registration_windows')->whereNull('academic_year_id')->get() as $window) {
            $yearId = DB::table('academic_years')->where('label', $window->academic_year)->value('id');
            if ($yearId) {
                DB::table('sahodaya_registration_windows')->where('id', $window->id)->update(['academic_year_id' => $yearId]);
            }
        }
    }

    private function backfillFeeReceipts(): void
    {
        if (! Schema::hasTable('membership_payments') || ! Schema::hasTable('fee_receipts')) {
            return;
        }

        $morphType = 'App\\Models\\MembershipPayment';

        foreach (DB::table('membership_payments')->whereNull('fee_receipt_id')->get() as $payment) {
            $status = match ($payment->status) {
                'verified' => 'approved',
                'rejected' => 'rejected',
                default    => 'uploaded',
            };

            $receiptId = DB::table('fee_receipts')->insertGetId([
                'feeable_type'        => $morphType,
                'feeable_id'          => $payment->id,
                'file_path'           => $payment->payment_proof_path,
                'transaction_ref'     => $payment->transaction_ref,
                'bank_name'           => null,
                'payment_date'        => $payment->created_at ? date('Y-m-d', strtotime($payment->created_at)) : null,
                'amount'              => $payment->amount,
                'status'              => $status,
                'rejection_reason'    => $payment->rejection_reason,
                'uploaded_by_user_id' => $payment->uploaded_by_user_id,
                'reviewed_by'         => $payment->verified_by_user_id,
                'reviewed_at'         => $payment->verified_at,
                'created_at'          => $payment->created_at ?? now(),
                'updated_at'          => $payment->updated_at ?? now(),
            ]);

            DB::table('membership_payments')->where('id', $payment->id)->update(['fee_receipt_id' => $receiptId]);
        }
    }
};
