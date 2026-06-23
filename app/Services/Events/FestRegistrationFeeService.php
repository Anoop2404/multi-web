<?php

namespace App\Services\Events;

use App\Models\FeeReceipt;
use App\Models\FestEvent;
use App\Models\FestRegistration;
use Illuminate\Http\UploadedFile;
use App\Support\TenantStorage;

class FestRegistrationFeeService
{
    public function amountDue(FestEvent $event, FestRegistration $registration): float
    {
        if ($event->fee_type === 'none' || ! $event->fee_amount) {
            return 0;
        }

        $count = max(1, $registration->participants()->count());

        return match ($event->fee_type) {
            'flat_school'      => (float) $event->fee_amount,
            'per_participant'  => (float) $event->fee_amount * $count,
            'per_item'         => (float) $event->fee_amount,
            default            => 0,
        };
    }

    public function feeRequired(FestEvent $event): bool
    {
        return $event->fee_type !== 'none' && (float) $event->fee_amount > 0;
    }

    public function attachPayment(
        FestRegistration $registration,
        UploadedFile $proof,
        string $schoolTenantId,
        int $userId,
        ?string $transactionRef = null,
        ?string $bankName = null,
    ): FeeReceipt {
        $event = $registration->event;
        $amount = $this->amountDue($event, $registration);

        $path = TenantStorage::storeUploadedFile($proof, "fest-payments/{$schoolTenantId}");

        $receipt = FeeReceipt::create([
            'feeable_type'        => $registration->getMorphClass(),
            'feeable_id'          => $registration->id,
            'file_path'           => $path,
            'transaction_ref'     => $transactionRef,
            'bank_name'           => $bankName,
            'payment_date'        => now()->toDateString(),
            'amount'              => $amount,
            'status'              => 'uploaded',
            'uploaded_by_user_id' => $userId,
        ]);

        $registration->update(['fee_receipt_id' => $receipt->id]);

        return $receipt;
    }

    public function isPaid(FestRegistration $registration): bool
    {
        if (! $registration->fee_receipt_id) {
            return false;
        }

        return $registration->feeReceipt?->status === 'approved';
    }
}
