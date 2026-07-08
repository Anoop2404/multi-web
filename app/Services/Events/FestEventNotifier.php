<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Support\TenancyDatabase;

class FestEventNotifier
{
    public function registrationApproved(FestRegistration $registration): void
    {
        $registration->load(['event', 'item']);
        $this->notifySchool(
            $registration->school_id,
            'fest.registration.approved',
            [
                'event_title' => $registration->event->title,
                'item_title'  => $registration->item?->title ?? 'General',
            ]
        );
    }

    public function registrationRejected(FestRegistration $registration): void
    {
        $registration->load('event');
        $this->notifySchool(
            $registration->school_id,
            'fest.registration.rejected',
            ['event_title' => $registration->event->title]
        );
    }

    public function registrationWithdrawn(FestRegistration $registration): void
    {
        $registration->load(['event', 'item']);
        $this->notifySchool(
            $registration->school_id,
            'fest.registration.withdrawn',
            [
                'event_title' => $registration->event->title,
                'item_title'  => $registration->item?->title ?? 'General',
            ]
        );
    }

    public function resultsPublished(FestEvent $event): void
    {
        $schoolIds = FestRegistration::where('event_id', $event->id)
            ->distinct()
            ->pluck('school_id');

        foreach ($schoolIds as $schoolId) {
            $replacements = ['event_title' => $event->title];
            $this->notifySchool($schoolId, 'fest.results.published', $replacements);
            $this->notifyEventParticipants($event, $schoolId, 'fest.results.published', $replacements);
        }
    }

    public function schedulePublished(FestEvent $event): void
    {
        $schoolIds = Tenant::query()
            ->where('parent_id', $event->tenant_id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->pluck('id');

        foreach ($schoolIds as $schoolId) {
            $replacements = ['event_title' => $event->title];
            $this->notifySchool($schoolId, 'fest.schedule.published', $replacements);
            $this->notifyEventParticipants($event, $schoolId, 'fest.schedule.published', $replacements);
        }
    }

    public function sportsWinnersReceived(FestEvent $event, Tenant $school, int $count): void
    {
        $this->withSahodayaUsers($event->tenant_id, ['sahodaya_admin', 'sahodaya_staff', 'event_coordinator'], function ($users) use ($event, $school, $count) {
            $service = app(NotificationService::class);
            $replacements = [
                'event_title' => $event->title,
                'school_name' => $school->name,
                'count'       => (string) $count,
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
        $this->notifySchool($schoolId, 'fest.chest_numbers.revealed', [
            'event_title'      => $event->title,
            'participant_name' => $participantName,
        ], "/school-admin/{$schoolId}/fest-day/{$event->id}");
    }

    public function appealReceived(FestEvent $event, string $participantName): void
    {
        $this->withSahodayaUsers($event->tenant_id, ['sahodaya_admin', 'sahodaya_staff', 'event_coordinator'], function ($users) use ($event, $participantName) {
            $service = app(NotificationService::class);
            $replacements = [
                'event_title'      => $event->title,
                'participant_name' => $participantName,
            ];

            foreach ($users as $user) {
                $service->notifyFromTemplate(
                    $user,
                    'fest.appeal.received',
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
            'item_title'  => $assignment->item?->title ?? 'Item',
        ];

        $service = app(NotificationService::class);
        if (! $service->notifyFromTemplate($user, 'fest.judge.assigned', $replacements, $url)) {
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

        foreach ($schoolIds as $schoolId) {
            $this->notifySchool($schoolId, 'fest.promotion.completed', [
                'event_title' => $toEvent->title,
                'count'       => (string) $count,
                'from_title'  => $fromEvent?->title ?? 'previous round',
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

        foreach ($schoolIds as $schoolId) {
            $this->notifySchool($schoolId, 'fest.registration.deadline', [
                'event_title' => $event->title,
                'days_left'   => (string) $daysLeft,
                'close_date'  => $event->registration_close?->format('d M Y') ?? '',
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
