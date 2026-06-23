<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\User;
use App\Services\Notifications\NotificationService;

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

    public function resultsPublished(FestEvent $event): void
    {
        $schoolIds = FestRegistration::where('event_id', $event->id)
            ->distinct()
            ->pluck('school_id');

        foreach ($schoolIds as $schoolId) {
            $this->notifySchool($schoolId, 'fest.results.published', [
                'event_title' => $event->title,
            ]);
        }
    }

    private function notifySchool(string $schoolId, string $template, array $replacements): void
    {
        $users = User::role('school_admin')->where('tenant_id', $schoolId)->get();
        $service = app(NotificationService::class);

        foreach ($users as $user) {
            $service->notifyFromTemplate($user, $template, $replacements);
        }
    }
}
