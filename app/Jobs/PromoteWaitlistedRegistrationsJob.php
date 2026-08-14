<?php

namespace App\Jobs;

use App\Models\FestEvent;
use App\Services\Events\FestRegistrationApprovalService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs FestRegistrationApprovalService::promoteAllEligibleWaitlisted() and, where
 * approval is automatic, approveSchoolEvent() for a school across a set of events.
 *
 * This previously ran inline on every GET to the school registration page
 * (FestRegistrationController::index()/eventRegistration()) — a GET request
 * performing writes on every page view. It could not simply be deleted:
 * promoteAllEligibleWaitlisted() has no other call site anywhere in the codebase
 * (verified 2026-08-13), so "check on page view" is the only existing trigger for
 * waitlist promotion — removing it outright would silently stop waitlisted students
 * from ever being promoted. This job preserves that trigger (the page load still
 * causes the check to run) while moving the actual write work off the request path.
 *
 * With QUEUE_CONNECTION=sync (the current default — see
 * PERFORMANCE_FIX_PLAN_2026_08_13.md Phase 0), dispatch() still runs this inline
 * before the page renders, identical to the old behavior. Once the queue driver is
 * switched to something async, it runs after the response is sent instead — the
 * registration list rendered on that specific page load may then not reflect a
 * promotion/approval that happened in this same request until the next reload. That
 * eventual-consistency tradeoff is the intended effect of queuing this, not a bug.
 */
class PromoteWaitlistedRegistrationsJob implements ShouldQueue
{
    use Queueable;

    /** @param list<int> $eventIds */
    public function __construct(
        public array $eventIds,
        public string $schoolId,
    ) {}

    public function handle(FestRegistrationApprovalService $approvalService): void
    {
        $events = FestEvent::whereIn('id', $this->eventIds)->get();

        foreach ($events as $event) {
            $approvalService->promoteAllEligibleWaitlisted($event);
            if (! $event->requiresManualApproval()) {
                $approvalService->approveSchoolEvent($event, $this->schoolId);
            }
        }
    }
}
