<?php

namespace App\Services\Events;

use App\Models\FestEvent;
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
     * Resolve a fest template slug with optional per-competition-type override.
     * Tries fest.{event_type}.{suffix} first, then fest.{suffix}.
     */
    public function resolveTemplateSlug(?FestEvent $event, string $baseSlug): string
    {
        $suffix = str_starts_with($baseSlug, 'fest.')
            ? substr($baseSlug, 5)
            : $baseSlug;

        $type = $event?->event_type;
        if ($type) {
            $typed = "fest.{$type}.{$suffix}";
            if (NotificationTemplate::where('slug', $typed)->where('is_active', true)->exists()) {
                return $typed;
            }
        }

        return "fest.{$suffix}";
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
        $this->notifySchool(
            $registration->school_id,
            $this->resolveTemplateSlug($registration->event, 'fest.registration.approved'),
            [
                'event_title' => $registration->event->title,
                'item_title' => $registration->item?->title ?? 'General',
                'competition_label' => $this->competitionLabel($registration->event),
            ]
        );
    }

    public function registrationRejected(FestRegistration $registration): void
    {
        $registration->load('event');
        $this->notifySchool(
            $registration->school_id,
            $this->resolveTemplateSlug($registration->event, 'fest.registration.rejected'),
            [
                'event_title' => $registration->event->title,
                'competition_label' => $this->competitionLabel($registration->event),
            ]
        );
    }

    public function registrationWithdrawn(FestRegistration $registration): void
    {
        $registration->load(['event', 'item']);
        $this->notifySchool(
            $registration->school_id,
            $this->resolveTemplateSlug($registration->event, 'fest.registration.withdrawn'),
            [
                'event_title' => $registration->event->title,
                'item_title' => $registration->item?->title ?? 'General',
                'competition_label' => $this->competitionLabel($registration->event),
            ]
        );
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

    public function paymentPending(FestEvent $event, string $schoolId, float $amount): void
    {
        $this->notifySchool(
            $schoolId,
            $this->resolveTemplateSlug($event, 'fest.payment.pending'),
            [
                'event_title' => $event->title,
                'competition_label' => $this->competitionLabel($event),
                'amount' => number_format($amount, 2),
            ],
            "/school-admin/{$schoolId}/fest/{$event->id}/fees"
        );
    }

    public function competitionReminder(FestEvent $event): void
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
    }

    public function certificatesAvailable(FestEvent $event, int $count): void
    {
        if ($count < 1) {
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
        $schoolIds = FestRegistration::where('event_id', $event->id)
            ->distinct()
            ->pluck('school_id');

        $slug = $this->resolveTemplateSlug($event, 'fest.results.published');

        foreach ($schoolIds as $schoolId) {
            $replacements = [
                'event_title' => $event->title,
                'competition_label' => $this->competitionLabel($event),
            ];
            $this->notifySchool($schoolId, $slug, $replacements);
            $this->notifyEventParticipants($event, $schoolId, $slug, $replacements);
        }
    }

    public function schedulePublished(FestEvent $event): void
    {
        $schoolIds = Tenant::query()
            ->where('parent_id', $event->tenant_id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->pluck('id');

        $slug = $this->resolveTemplateSlug($event, 'fest.schedule.published');

        foreach ($schoolIds as $schoolId) {
            $replacements = [
                'event_title' => $event->title,
                'competition_label' => $this->competitionLabel($event),
            ];
            $this->notifySchool($schoolId, $slug, $replacements);
            $this->notifyEventParticipants($event, $schoolId, $slug, $replacements);
        }
    }

    public function sportsWinnersReceived(FestEvent $event, Tenant $school, int $count): void
    {
        $this->withSahodayaUsers($event->tenant_id, ['sahodaya_admin', 'sahodaya_staff', 'event_coordinator'], function ($users) use ($event, $school, $count) {
            $service = app(NotificationService::class);
            $replacements = [
                'event_title' => $event->title,
                'school_name' => $school->name,
                'count' => (string) $count,
                'competition_label' => $this->competitionLabel($event),
            ];

            foreach ($users as $user) {
                $service->notifyFromTemplate(
                    $user,
                    'sports.winners.received',
                    $replacements,
                    "/sahodaya-admin/{$event->tenant_id}/events/{$event->id}/registrations",
                );
            }
        });
    }

    public function notifySchoolForChestReveal(FestEvent $event, string $schoolId, string $participantName): void
    {
        $this->notifySchool($schoolId, $this->resolveTemplateSlug($event, 'fest.chest_numbers.revealed'), [
            'event_title' => $event->title,
            'participant_name' => $participantName,
            'competition_label' => $this->competitionLabel($event),
        ], "/school-admin/{$schoolId}/fest-day/{$event->id}");
    }

    public function appealReceived(FestEvent $event, string $participantName): void
    {
        $this->withSahodayaUsers($event->tenant_id, ['sahodaya_admin', 'sahodaya_staff', 'event_coordinator'], function ($users) use ($event, $participantName) {
            $service = app(NotificationService::class);
            $replacements = [
                'event_title' => $event->title,
                'participant_name' => $participantName,
                'competition_label' => $this->competitionLabel($event),
            ];

            foreach ($users as $user) {
                $service->notifyFromTemplate(
                    $user,
                    $this->resolveTemplateSlug($event, 'fest.appeal.received'),
                    $replacements,
                    "/sahodaya-admin/{$event->tenant_id}/events/{$event->id}/appeals",
                );
            }
        });
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
        $schoolIds = FestRegistration::where('event_id', $toEvent->id)
            ->distinct()
            ->pluck('school_id');

        if ($schoolIds->isEmpty() && $fromEvent) {
            $schoolIds = FestRegistration::where('event_id', $fromEvent->id)
                ->distinct()
                ->pluck('school_id');
        }

        $slug = $this->resolveTemplateSlug($toEvent, 'fest.promotion.completed');

        foreach ($schoolIds as $schoolId) {
            $this->notifySchool($schoolId, $slug, [
                'event_title' => $toEvent->title,
                'count' => (string) $count,
                'from_title' => $fromEvent?->title ?? 'previous round',
                'competition_label' => $this->competitionLabel($toEvent),
            ]);
        }
    }

    public function registrationDeadlineReminder(FestEvent $event, int $daysLeft): void
    {
        $schoolIds = Tenant::query()
            ->where('parent_id', $event->tenant_id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->pluck('id');

        $slug = $this->resolveTemplateSlug($event, 'fest.registration.deadline');

        foreach ($schoolIds as $schoolId) {
            $this->notifySchool($schoolId, $slug, [
                'event_title' => $event->title,
                'days_left' => (string) $daysLeft,
                'close_date' => $event->registration_close?->format('d M Y') ?? '',
                'competition_label' => $this->competitionLabel($event),
            ]);
        }
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
