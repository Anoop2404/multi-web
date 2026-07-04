<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Support\FestReportCatalog;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EventLifecycleGate
{
    public static function allowRegistration(FestEvent $event): void
    {
        if ($event->registration_locked) {
            throw new HttpException(422, 'Registration is locked for this event.');
        }

        if (! $event->isRegistrationOpen()) {
            throw new HttpException(422, 'Registration is not open for this event.');
        }
    }

    /** Staff review of submitted registrations (approve/reject). Pass override=true to bypass closed registration. */
    public static function allowRegistrationReview(FestEvent $event, bool $override = false): void
    {
        if ($override) {
            return;
        }

        if ($event->registration_locked) {
            throw new HttpException(422, 'Registration is locked. Use override to approve late entries.');
        }

        if ($event->results_published || $event->status === 'completed') {
            throw new HttpException(422, 'Registration review is closed after results are published.');
        }
    }

    public static function currentReportPhase(FestEvent $event): string
    {
        if ($event->results_published || $event->status === 'completed') {
            return 'after';
        }

        if ($event->schedule_published || $event->status === 'ongoing') {
            return 'during';
        }

        return 'before';
    }

    /** @return list<string> */
    public static function allowedReportPhases(FestEvent $event): array
    {
        $current = self::currentReportPhase($event);

        return match ($current) {
            'after'  => ['before', 'during', 'after'],
            'during' => ['before', 'during'],
            default  => ['before'],
        };
    }

    public static function allowMarkEntry(FestEvent $event): void
    {
        if ($event->scoring_locked) {
            throw new HttpException(422, 'Scoring is locked for this event.');
        }

        if (! in_array($event->status, ['ongoing', 'registration_open', 'published'], true)) {
            throw new HttpException(422, 'Mark entry is not allowed in the current event phase.');
        }
    }

    public static function allowSchedulePublish(FestEvent $event): void
    {
        if ($event->schedule_published) {
            throw new HttpException(422, 'Schedule is already published.');
        }
    }

    public static function allowPublicSchedule(FestEvent $event): void
    {
        if (! $event->schedule_published) {
            throw new HttpException(404, 'Schedule is not published yet.');
        }
    }

    public static function allowReportExport(FestEvent $event, string $exportType, string $audience = 'staff'): void
    {
        if ($audience === 'staff') {
            return;
        }

        $staffOnly = [
            'registration-list', 'registrations', 'admit-cards', 'clashes', 'clashes-school',
            'fees', 'students', 'student-participation', 'promotions', 'certificate-counts', 'catering',
            'catering-by-school', 'volunteer-roster', 'id-cards-by-head', 'audit-log-extract',
        ];

        if (in_array($exportType, $staffOnly, true)) {
            throw new HttpException(422, 'This report is staff-only and cannot be exported for public distribution.');
        }
    }

    public static function allowResultReport(FestEvent $event, string $exportType): void
    {
        $resultExports = FestReportCatalog::resultExportTypes();

        if (in_array($exportType, $resultExports, true) && ! $event->results_published) {
            throw new HttpException(422, 'Result reports are available only after results are published.');
        }
    }

    public static function allowPublishResults(FestEvent $event): void
    {
        if ($event->results_published) {
            throw new HttpException(422, 'Results are already published.');
        }

        app(FestJudgeGateService::class)->assertCanPublish($event);

        if ($event->require_all_marks_before_publish) {
            self::assertAllParticipantsMarked($event);
        }
    }

    /** Block publishing an event that schools would see as empty. */
    public static function assertCanPublishEvent(FestEvent $event, ?string $venue = null, $eventStart = null): void
    {
        if (! $event->items()->exists()) {
            throw new HttpException(422, 'Add at least one competition item before publishing this event.');
        }

        $venueValue = $venue ?? $event->venue;
        $startValue = $eventStart ?? $event->event_start;

        if (! filled($venueValue) && ! filled($startValue)) {
            throw new HttpException(422, 'Set a venue or event start date before publishing.');
        }
    }

    private static function assertAllParticipantsMarked(FestEvent $event): void
    {
        $participantCount = FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('status', 'approved'))
            ->where(function ($q) {
                $q->where('participant_role', 'performer')->orWhereNull('participant_role');
            })
            ->count();

        $markedCount = FestMark::where('event_id', $event->id)
            ->where(function ($q) {
                $q->whereNotNull('grade')->orWhereNotNull('score')->orWhereNotNull('position');
            })
            ->count();

        if ($participantCount > 0 && $markedCount < $participantCount) {
            throw new HttpException(422, "Mark entry incomplete ({$markedCount}/{$participantCount}). Complete all marks before publishing.");
        }
    }
}
