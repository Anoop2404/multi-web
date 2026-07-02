<?php

namespace App\Services\Events;

use App\Models\FeeReceipt;
use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Models\LedgerTransaction;
use App\Services\Ledger\LedgerPostingService;
use App\Support\LedgerAccountCatalog;

class FestFeeLedgerService
{
    public function postApprovedReceipt(FeeReceipt $receipt, bool $forceRepost = false): ?LedgerTransaction
    {
        if ($receipt->status !== 'approved') {
            return null;
        }

        [$sahodayaId, $description, $eventId] = match ($receipt->feeable_type) {
            FestSchoolEventFee::class => $this->schoolEventFeeContext($receipt),
            FestRegistration::class => $this->registrationContext($receipt),
            default => [null, null, null],
        };

        if (! $sahodayaId || ! $eventId) {
            return null;
        }

        $event = FestEvent::find($eventId);
        if (! $event) {
            return null;
        }

        $incomeCode = LedgerAccountCatalog::festIncomeCode($event);
        app(LedgerPostingService::class)->ensureHead(
            $sahodayaId,
            $incomeCode,
            LedgerAccountCatalog::festIncomeHeadName($event),
            LedgerAccountCatalog::festIncomeCategory($event),
            $event->id,
        );

        $rows = app(LedgerPostingService::class)->postIncomeReceipt(
            $receipt,
            $sahodayaId,
            $incomeCode,
            $description ?? 'Event fee receipt',
            $forceRepost
        );

        return $rows[1] ?? $rows[0] ?? null;
    }

    /** @return array{0: ?string, 1: ?string, 2: ?string} */
    private function schoolEventFeeContext(FeeReceipt $receipt): array
    {
        $fee = FestSchoolEventFee::with('event', 'school')->find($receipt->feeable_id);
        if (! $fee?->event) {
            return [null, null, null];
        }

        return [
            $fee->event->tenant_id,
            "Event fee — {$fee->school?->name} — {$fee->event->title}",
            $fee->event->id,
        ];
    }

    /** @return array{0: ?string, 1: ?string, 2: ?string} */
    private function registrationContext(FeeReceipt $receipt): array
    {
        $registration = FestRegistration::with('event')->find($receipt->feeable_id);
        if (! $registration?->event) {
            return [null, null, null];
        }

        return [
            $registration->event->tenant_id,
            "Event fee — registration #{$registration->id}",
            $registration->event->id,
        ];
    }
}
