<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestItemHead;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Models\NotificationTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Support\TenancyDatabase;

class FestEventNotifier
{
    /**
     * Resolve a fest template slug with optional per-competition-type, then per-Event-Head,
     * override. Tries fest.{event_type}.{head_slug}.{suffix} (most specific — Phase 3 of the
     * Sports head-first rebuild), then fest.{event_type}.{suffix}, then fest.{suffix}.
     */
    public function resolveTemplateSlug(?FestEvent $event, string $baseSlug): string
    {
        $suffix = str_starts_with($baseSlug, 'fest.')
            ? substr($baseSlug, 5)
            : $baseSlug;

        $type = $event?->event_type;

        if ($type) {
            $head = $this->resolveHeadForEvent($event);
            if ($head?->slug) {
                $headTyped = "fest.{$type}.{$head->slug}.{$suffix}";
                if (NotificationTemplate::where('slug', $headTyped)->where('is_active', true)->exists()) {
                    return $headTyped;
                }
            }

            $typed = "fest.{$type}.{$suffix}";
            if (NotificationTemplate::where('slug', $typed)->where('is_active', true)->exists()) {
                return $typed;
            }
        }

        return "fest.{$suffix}";
    }

    /**
     * Legacy FestItemHead linked to a sport event (dual-read during migration).
     * Prefer FestEvent::notificationEnabledFor() once fees/notifications live on the event.
     */
    private function resolveHeadForEvent(?FestEvent $event): ?FestItemHead
    {
        if (! $event || $event->event_type !== 'sports' || $event->parent_event_id === null) {
            return null;
        }

        if ($event->source_head_id) {
            $head = FestItemHead::find($event->source_head_id);
            if ($head) {
                return $head;
            }
        }

        return FestItemHead::where('discipline_event_id', $event->id)->first();
    }

    /**
     * Fan out to a head's extra recipients (existing platform users only, picked by a
     * Sahodaya admin — never free-text emails). No-op when the event has no owning head
     * or the head has no extra recipients configured.
     */
    private function notifyHeadExtras(?FestItemHead $head, string $template, array $replacements, ?string $url = null): void
    {
        if (! $head) {
            return;
        }

        $ids = $head->extraRecipientUserIds();
        if ($ids === []) {
            return;
        }

        $sahodaya = Tenant::query()->find($head->tenant_id);
        if (! $sahodaya) {
            return;
        }

        TenancyDatabase::withTenantDatabase($sahodaya, function () use ($sahodaya, $ids, $template, $replacements, $url) {
            $service = app(NotificationService::class);
            foreach (User::whereIn('id', $ids)->where('tenant_id', $sahodaya->id)->get() as $user) {
                $service->notifyFromTemplate($user, $template, $replacements, $url);
            }
        });
    }

    public function competitionLabel(FestEvent $event): string
    {
        try {
            $labels = app(FestCompetitionTypeRegistry::class)
                ->forTenant($event->tenant_id)
                ->labels(false);

            return $labels[$event->event_type] ?? $event->event_type ?? 'Competition';
        } catch (\Throwable) {
            return $event->event_type ?? 'Competition';
        }
    }

    public function registrationApproved(FestRegistration $registration): void
    {
        $registration->load(['event', 'item']);
        $head = $this->resolveHeadForEvent($registration->event);
        if ($head && ! $head->notificationEnabledFor('registration_approved')) {
            return;
        }

        $slug = $this->resolveTemplateSlug($registration->event, 'fest.registration.approved');
        $replacements = [
            'event_title' => $registration->event->title,
            'item_title' => $registration->item?->title ?? 'General',
            'competition_label' => $this->competitionLabel($registration->event),
        ];
        $this->notifySchool($registration->school_id, $slug, $replacements);
        $this->notifyHeadExtras($head, $slug, $replacements);
    }

    public function registrationRejected(FestRegistration $registration, string $reason = ''): void
    {
        $registration->load('event');
        $head = $this->resolveHeadForEvent($registration->event);
        if ($head && ! $head->notificationEnabledFor('registration_rejected')) {
            return;
        }

        $slug = $this->resolveTemplateSlug($registration->event, 'fest.registration.rejected');
        $replacements = [
            'event_title'       => $registration->event->title,
            'competition_label' => $this->competitionLabel($registration->event),
            'rejection_reason'  => $reason ?: 'Contact your Sahodaya for details.',
        ];
        $this->notifySchool($registration->school_id, $slug, $replacements);
        $this->notifyHeadExtras($head, $slug, $replacements);
    }

    public function registrationWithdrawn(FestRegistration $registration): void
    {
        $registration->load(['event', 'item']);
        $head = $this->resolveHeadForEvent($registration->event);
        if ($head && ! $head->notificationEnabledFor('registration_withdrawn')) {
            return;
        }

        $slug = $this->resolveTemplateSlug($registration->event, 'fest.registration.withdrawn');
        $replacements = [
            'event_title' => $registration->event->title,
            'item_title' => $registration->item?->title ?? 'General',
            'competition_label' => $this->competitionLabel($registration->event),
        ];
        $this->notifySchool($registration->school_id, $slug, $replacements);
        $this->notifyHeadExtras($head, $slug, $replacements);
    }

    /**
     * Notify Sahodaya/event-coordinator users when a school withdraws one of its own
     * registrations. Fires alongside registrationWithdrawn() (which notifies the school).
     * Uses the existing withSahodayaUsers() helper.
     */
    public function registrationWithdrawnAdmin(FestRegistration $registration): void
    {
        $registration->loadMissing(['event', 'item']);
        $event = $registration->event;
        if (! $event) {
            return;
        }

        $replacements = [
            'event_title'   => $event->title,
            'item_title'    => $registration->item?->title ?? 'General',
            'school_name'   => '', // resolved later per-user context
            'competition_label' => $this->competitionLabel($event),
        ];

        $this->withSahodayaUsers($event->tenant_id, ['sahodaya_admin', 'sahodaya_staff'], function ($users) use ($registration, $replacements, $event) {
            $service = app(NotificationService::class);
            $url = "/sahodaya-admin/{$event->tenant_id}/programs/kalotsav/registration";
            foreach ($users as $user) {
                $service->notifyFromTemplate($user, 'fest.registration.withdrawn_admin', $replacements, $url);
            }
        });
    }

    /**
     * Distinct from registrationWithdrawn() — fired only for FestRegistrationService::
     * cancelWithRefund(), where a Sahodaya admin cancels a registration that already had an
     * approved payment. Carries the admin's required reason and, when a credit was actually
     * issued, the amount — so the school understands why a paid, approved entry disappeared
     * rather than just seeing the generic "cancelled" notice. $creditAmount is null when the
     * cancellation didn't free up any due amount (e.g. nothing was actually paid yet despite
     * being "approved" in an edge case), in which case the credit line is simply omitted.
     */
    public function registrationCancelledWithRefund(FestRegistration $registration, string $reason, ?float $creditAmount = null): void
    {
        $registration->load(['event', 'item']);
        $head = $this->resolveHeadForEvent($registration->event);
        if ($head && ! $head->notificationEnabledFor('registration_withdrawn')) {
            return;
        }

        $slug = $this->resolveTemplateSlug($registration->event, 'fest.registration.cancelled_with_refund');
        $replacements = [
            'event_title' => $registration->event->title,
            'item_title' => $registration->item?->title ?? 'General',
            'competition_label' => $this->competitionLabel($registration->event),
            'reason' => $reason,
            'credit_line' => $creditAmount !== null && $creditAmount > 0
                ? " A fee credit of ₹{$creditAmount} has been recorded and can be applied toward another item in this event."
                : '',
        ];
        $this->notifySchool($registration->school_id, $slug, $replacements);
        $this->notifyHeadExtras($head, $slug, $replacements);
    }

    public function registrationOpened(FestEvent $event): void
    {
        $schoolIds = Tenant::query()
            ->where('parent_id', $event->tenant_id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->pluck('id');

        $slug = $this->resolveTemplateSlug($event, 'fest.registration.open');
        $replacements = [
            'event_title' => $event->title,
            'competition_label' => $this->competitionLabel($event),
            'close_date' => $event->registration_close?->format('d M Y') ?? 'TBA',
        ];

        foreach ($schoolIds as $schoolId) {
            $this->notifySchool($schoolId, $slug, $replacements);
        }
    }

    /** Notify approved schools once an event's status moves to "completed". */
    public function eventCompleted(FestEvent $event): void
    {
        $schoolIds = Tenant::query()
            ->where('parent_id', $event->tenant_id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->pluck('id');

        $slug = $this->resolveTemplateSlug($event, 'fest.event.completed');
        $replacements = [
            'event_title' => $event->title,
            'competition_label' => $this->competitionLabel($event),
        ];

        foreach ($schoolIds as $schoolId) {
            $this->notifySchool($schoolId, $slug, $replacements);
        }
    }

    /** Notify schools when an event is cancelled by the admin. */
    public function eventCancelled(FestEvent $event, \Illuminate\Support\Collection $credits): void
    {
        $schoolIds = FestRegistration::where('event_id', $event->id)
            ->distinct()
            ->pluck('school_id');

        if ($schoolIds->isEmpty()) {
            $schoolIds = Tenant::query()
                ->where('parent_id', $event->tenant_id)
                ->where('type', 'school')
                ->where('membership_status', 'approved')
                ->pluck('id');
        }

        $slug = $this->resolveTemplateSlug($event, 'fest.event.cancelled');
        
        $creditsBySchool = $credits->keyBy(function($c) {
            return $c->fee?->school_id;
        });

        foreach ($schoolIds as $schoolId) {
            $creditForSchool = $creditsBySchool->get($schoolId);
            $replacements = [
                'event_title' => $event->title,
                'competition_label' => $this->competitionLabel($event),
                'credit_line' => $creditForSchool && $creditForSchool->amount > 0
                    ? " A fee credit of ₹{$creditForSchool->amount} has been recorded to your school account."
                    : '',
            ];

            $this->notifySchool($schoolId, $slug, $replacements);
        }
        
        $head = $this->resolveHeadForEvent($event);
        $this->notifyHeadExtras($head, $slug, [
            'event_title' => $event->title,
            'competition_label' => $this->competitionLabel($event),
            'credit_line' => '',
        ]);
    }

    public function paymentPending(FestEvent $event, string $schoolId, float $amount): void
    {
        $head = $this->resolveHeadForEvent($event);
        if ($head && ! $head->notificationEnabledFor('payment_pending')) {
            return;
        }

        $slug = $this->resolveTemplateSlug($event, 'fest.payment.pending');
        $replacements = [
            'event_title' => $event->title,
            'competition_label' => $this->competitionLabel($event),
            'amount' => number_format($amount, 2),
        ];
        $url = "/school-admin/{$schoolId}/fest/{$event->id}/fees";
        $this->notifySchool($schoolId, $slug, $replacements, $url);
        $this->notifyHeadExtras($head, $slug, $replacements, $url);
    }

    public function competitionReminder(FestEvent $event): void
    {
        $head = $this->resolveHeadForEvent($event);
        if ($head && ! $head->notificationEnabledFor('competition_reminder')) {
            return;
        }

        $schoolIds = FestRegistration::where('event_id', $event->id)
            ->distinct()
            ->pluck('school_id');

        if ($schoolIds->isEmpty()) {
            $schoolIds = Tenant::query()
                ->where('parent_id', $event->tenant_id)
                ->where('type', 'school')
                ->where('membership_status', 'approved')
                ->pluck('id');
        }

        $slug = $this->resolveTemplateSlug($event, 'fest.competition.reminder');
        $replacements = [
            'event_title' => $event->title,
            'competition_label' => $this->competitionLabel($event),
            'start_date' => $event->event_start?->format('d M Y') ?? 'TBA',
            'venue' => $event->venue ?: 'TBA',
        ];

        foreach ($schoolIds as $schoolId) {
            $this->notifySchool($schoolId, $slug, $replacements);
            $this->notifyEventParticipants($event, $schoolId, $slug, $replacements);
        }
        $this->notifyHeadExtras($head, $slug, $replacements);
    }

    public function certificatesAvailable(FestEvent $event, int $count): void
    {
        if ($count < 1) {
            return;
        }

        $head = $this->resolveHeadForEvent($event);
        if ($head && ! $head->notificationEnabledFor('certificates_available')) {
            return;
        }

        $schoolIds = FestRegistration::where('event_id', $event->id)
            ->distinct()
            ->pluck('school_id');

        $slug = $this->resolveTemplateSlug($event, 'fest.certificate.available');
        $replacements = [
            'event_title' => $event->title,
            'competition_label' => $this->competitionLabel($event),
            'count' => (string) $count,
        ];

        foreach ($schoolIds as $schoolId) {
            $this->notifySchool($schoolId, $slug, $replacements);
            $this->notifyEventParticipants($event, $schoolId, $slug, $replacements);
        }
        $this->notifyHeadExtras($head, $slug, $replacements);
    }

    /** Notify schools that still have unpaid fest fees (used by scheduled reminder). */
    public function paymentPendingReminders(FestEvent $event): int
    {
        $fees = FestSchoolEventFee::where('event_id', $event->id)
            ->whereNotIn('status', ['approved', 'waived'])
            ->where('total_due', '>', 0)
            ->get();

        $sent = 0;
        foreach ($fees as $fee) {
            $this->paymentPending($event, $fee->school_id, (float) $fee->total_due);
            $sent++;
        }

        return $sent;
    }

    public function resultsPublished(FestEvent $event): void
    {
        $head = $this->resolveHeadForEvent($event);
        if ($head && ! $head->notificationEnabledFor('results_published')) {
            return;
        }

        $schoolIds = FestRegistration::where('event_id', $event->id)
            ->distinct()
            ->pluck('school_id');

        $slug = $this->resolveTemplateSlug($event, 'fest.results.published');
        $replacements = [
            'event_title' => $event->title,
            'competition_label' => $this->competitionLabel($event),
        ];

        foreach ($schoolIds as $schoolId) {
            $this->notifySchool($schoolId, $slug, $replacements);
            $this->notifyEventParticipants($event, $schoolId, $slug, $replacements);
        }
        $this->notifyHeadExtras($head, $slug, $replacements);
    }

    public function schedulePublished(FestEvent $event): void
    {
        $head = $this->resolveHeadForEvent($event);
        if ($head && ! $head->notificationEnabledFor('schedule_published')) {
            return;
        }

        $schoolIds = Tenant::query()
            ->where('parent_id', $event->tenant_id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->pluck('id');

        $slug = $this->resolveTemplateSlug($event, 'fest.schedule.published');
        $replacements = [
            'event_title' => $event->title,
            'competition_label' => $this->competitionLabel($event),
        ];

        foreach ($schoolIds as $schoolId) {
            $this->notifySchool($schoolId, $slug, $replacements);
            $this->notifyEventParticipants($event, $schoolId, $slug, $replacements);
        }
        $this->notifyHeadExtras($head, $slug, $replacements);
    }

    public function sportsWinnersReceived(FestEvent $event, Tenant $school, int $count): void
    {
        $head = $this->resolveHeadForEvent($event);
        if ($head && ! $head->notificationEnabledFor('sports_winners_received')) {
            return;
        }

        $replacements = [
            'event_title' => $event->title,
            'school_name' => $school->name,
            'count' => (string) $count,
            'competition_label' => $this->competitionLabel($event),
        ];
        $url = "/sahodaya-admin/{$event->tenant_id}/events/{$event->id}/registrations";

        $this->withSahodayaUsers($event->tenant_id, ['sahodaya_admin', 'sahodaya_staff', 'event_coordinator'], function ($users) use ($replacements, $url) {
            $service = app(NotificationService::class);
            foreach ($users as $user) {
                $service->notifyFromTemplate($user, 'sports.winners.received', $replacements, $url);
            }
        });
        $this->notifyHeadExtras($head, 'sports.winners.received', $replacements, $url);
    }

    public function notifySchoolForChestReveal(FestEvent $event, string $schoolId, string $participantName): void
    {
        $head = $this->resolveHeadForEvent($event);
        if ($head && ! $head->notificationEnabledFor('chest_reveal')) {
            return;
        }

        $slug = $this->resolveTemplateSlug($event, 'fest.chest_numbers.revealed');
        $replacements = [
            'event_title' => $event->title,
            'participant_name' => $participantName,
            'competition_label' => $this->competitionLabel($event),
        ];
        $url = "/school-admin/{$schoolId}/fest-day/{$event->id}";

        $this->notifySchool($schoolId, $slug, $replacements, $url);
        $this->notifyHeadExtras($head, $slug, $replacements, $url);
    }

    public function appealReceived(FestEvent $event, string $participantName): void
    {
        $head = $this->resolveHeadForEvent($event);
        if ($head && ! $head->notificationEnabledFor('appeal_received')) {
            return;
        }

        $slug = $this->resolveTemplateSlug($event, 'fest.appeal.received');
        $replacements = [
            'event_title' => $event->title,
            'participant_name' => $participantName,
            'competition_label' => $this->competitionLabel($event),
        ];
        $url = "/sahodaya-admin/{$event->tenant_id}/events/{$event->id}/appeals";

        $this->withSahodayaUsers($event->tenant_id, ['sahodaya_admin', 'sahodaya_staff', 'event_coordinator'], function ($users) use ($slug, $replacements, $url) {
            $service = app(NotificationService::class);
            foreach ($users as $user) {
                $service->notifyFromTemplate($user, $slug, $replacements, $url);
            }
        });
        $this->notifyHeadExtras($head, $slug, $replacements, $url);
    }

    public function judgeAssigned(\App\Models\FestJudgeAssignment $assignment): void
    {
        $assignment->load(['event', 'item', 'user']);
        $user = $assignment->user;
        if (! $user) {
            return;
        }

        $url = "/portal/judge/{$user->tenant_id}/events/{$assignment->event_id}/marks";
        $replacements = [
            'event_title' => $assignment->event->title,
            'item_title' => $assignment->item?->title ?? 'Item',
            'competition_label' => $this->competitionLabel($assignment->event),
        ];

        $service = app(NotificationService::class);
        $slug = $this->resolveTemplateSlug($assignment->event, 'fest.judge.assigned');
        if (! $service->notifyFromTemplate($user, $slug, $replacements, $url)) {
            $service->notify(
                $user,
                'New judging assignment',
                "You have been assigned to judge {$replacements['item_title']} at {$replacements['event_title']}.",
                $url,
                ['in_app', 'email'],
            );
        }
    }

    public function promotionCompleted(FestEvent $toEvent, int $count, ?FestEvent $fromEvent = null): void
    {
        $head = $this->resolveHeadForEvent($toEvent);
        if ($head && ! $head->notificationEnabledFor('promotion_completed')) {
            return;
        }

        $schoolIds = FestRegistration::where('event_id', $toEvent->id)
            ->distinct()
            ->pluck('school_id');

        if ($schoolIds->isEmpty() && $fromEvent) {
            $schoolIds = FestRegistration::where('event_id', $fromEvent->id)
                ->distinct()
                ->pluck('school_id');
        }

        $slug = $this->resolveTemplateSlug($toEvent, 'fest.promotion.completed');
        $replacements = [
            'event_title' => $toEvent->title,
            'count' => (string) $count,
            'from_title' => $fromEvent?->title ?? 'previous round',
            'competition_label' => $this->competitionLabel($toEvent),
        ];

        foreach ($schoolIds as $schoolId) {
            $this->notifySchool($schoolId, $slug, $replacements);
        }
        $this->notifyHeadExtras($head, $slug, $replacements);
    }

    public function registrationDeadlineReminder(FestEvent $event, int $daysLeft): void
    {
        $head = $this->resolveHeadForEvent($event);
        if ($head && ! $head->notificationEnabledFor('registration_deadline')) {
            return;
        }

        $schoolIds = Tenant::query()
            ->where('parent_id', $event->tenant_id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->pluck('id');

        $slug = $this->resolveTemplateSlug($event, 'fest.registration.deadline');
        $replacements = [
            'event_title' => $event->title,
            'days_left' => (string) $daysLeft,
            'close_date' => $event->registration_close?->format('d M Y') ?? '',
            'competition_label' => $this->competitionLabel($event),
        ];

        foreach ($schoolIds as $schoolId) {
            $this->notifySchool($schoolId, $slug, $replacements);
        }
        $this->notifyHeadExtras($head, $slug, $replacements);
    }

    private function notifySchool(string $schoolId, string $template, array $replacements, ?string $url = null): void
    {
        $school = Tenant::query()->find($schoolId);
        if (! $school?->parent_id) {
            return;
        }

        $this->withSchoolUsers($school, ['school_admin', 'school_staff'], function ($users) use ($template, $replacements, $url) {
            $service = app(NotificationService::class);

            foreach ($users as $user) {
                $service->notifyFromTemplate($user, $template, $replacements, $url);
            }
        });
    }

    private function notifyEventParticipants(FestEvent $event, string $schoolId, string $template, array $replacements): void
    {
        $participants = FestParticipant::query()
            ->whereHas('registration', fn ($q) => $q
                ->where('event_id', $event->id)
                ->where('school_id', $schoolId)
                ->where('status', 'approved'))
            ->with(['student.user', 'teacher.user'])
            ->get();

        $service = app(NotificationService::class);
        $notified = [];

        foreach ($participants as $participant) {
            foreach ([$participant->student?->user, $participant->teacher?->user] as $user) {
                if (! $user || isset($notified[$user->id])) {
                    continue;
                }

                $notified[$user->id] = true;
                $portalPath = $user->hasRole('student')
                    ? "/portal/student/{$schoolId}"
                    : ($user->hasRole('teacher') ? "/portal/teacher/{$schoolId}" : null);

                $service->notifyFromTemplate($user, $template, $replacements, $portalPath);
            }
        }
    }

    /** @param  list<string>  $roles */
    private function withSahodayaUsers(string $sahodayaId, array $roles, callable $callback): void
    {
        $sahodaya = Tenant::query()->find($sahodayaId);
        if (! $sahodaya) {
            return;
        }

        TenancyDatabase::withTenantDatabase($sahodaya, function () use ($sahodaya, $roles, $callback) {
            $users = User::role($roles)->where('tenant_id', $sahodaya->id)->get();
            $callback($users);
        });
    }

    /** @param  list<string>  $roles */
    private function withSchoolUsers(Tenant $school, array $roles, callable $callback): void
    {
        TenancyDatabase::withTenantDatabase($school, function () use ($school, $roles, $callback) {
            $users = User::role($roles)->where('tenant_id', $school->id)->get();
            $callback($users);
        });
    }
}
