<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestFeeCredit;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FestEventStatusService
{
    public function __construct(
        private FestEventNotifier $notifier,
        private \App\Services\Audit\PlatformAuditLogger $audit
    ) {}

    public function transitionToCancelled(FestEvent $event, bool $confirmCreditAll = false): void
    {
        $paidFees = FestSchoolEventFee::where('event_id', $event->id)
            ->where('amount_paid', '>', 0)
            ->get();

        if ($paidFees->isNotEmpty() && !$confirmCreditAll) {
            $count = $paidFees->count();
            $total = $paidFees->sum('amount_paid');

            throw ValidationException::withMessages([
                'status' => "This event has {$count} school(s) with approved payments totaling ₹{$total}. To proceed with cancellation and issue credits, you must confirm 'credit_all'.",
            ]);
        }

        DB::transaction(function () use ($event, $paidFees) {
            $registrations = FestRegistration::whereIn('event_id', $event->reportableEventIds())
                ->whereIn('status', FestRegistration::ACTIVE_STATUSES)
                ->get();

            if ($registrations->isNotEmpty()) {
                FestRegistration::whereIn('id', $registrations->pluck('id'))->update([
                    'status' => 'withdrawn',
                ]);
            }

            foreach ($paidFees as $fee) {
                app(FestSchoolEventFeeService::class)->recalculate($event, $fee->school_id);
            }

            $event->update(['status' => 'cancelled']);

            $this->notifier->eventCancelled($event, $issuedCredits);

            $this->audit->festEvent(
                $event,
                \App\Support\Enums\FestPageActivity::OVERVIEW,
                'fest.event.cancelled',
                "Event cancelled: {$event->title}",
                ['status' => 'cancelled']
            );
        });
    }
}
