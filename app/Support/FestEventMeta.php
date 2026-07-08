<?php

namespace App\Support;

use App\Models\FestEvent;
use App\Services\Events\EventLifecycleGate;
use Carbon\CarbonInterface;

class FestEventMeta
{
    /** @return array<string, mixed> */
    public static function reportSnapshot(FestEvent $event, ?string $overviewUrl = null, ?string $settingsUrl = null): array
    {
        return [
            'status'                    => $event->status,
            'status_label'              => self::statusLabel($event->status),
            'event_start'               => $event->event_start?->toDateString(),
            'event_end'                 => $event->event_end?->toDateString(),
            'event_dates_label'         => self::dateRangeLabel($event->event_start, $event->event_end),
            'registration_open'         => $event->registration_open?->toDateString(),
            'registration_close'        => $event->registration_close?->toDateString(),
            'registration_window_label' => self::dateRangeLabel($event->registration_open, $event->registration_close),
            'venue'                     => $event->venue,
            'results_published'         => (bool) $event->results_published,
            'schedule_published'        => (bool) $event->schedule_published,
            'registration_locked'       => (bool) $event->registration_locked,
            'report_phase'              => EventLifecycleGate::currentReportPhase($event),
            'level_round'               => $event->level_round,
            'sports_age_cutoff_date'    => $event->sports_age_cutoff_date?->toDateString(),
            'overview_url'              => $overviewUrl,
            'settings_url'              => $settingsUrl,
        ];
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'draft'              => 'Draft',
            'published'          => 'Published',
            'registration_open'  => 'Registration open',
            'ongoing'            => 'Live / ongoing',
            'completed'          => 'Completed',
            'cancelled'          => 'Cancelled',
            default              => ucfirst(str_replace('_', ' ', (string) $status)),
        };
    }

    public static function dateRangeLabel(?CarbonInterface $start, ?CarbonInterface $end): ?string
    {
        if (! $start && ! $end) {
            return null;
        }

        if ($start && $end && ! $start->equalTo($end)) {
            return $start->format('j M Y').' – '.$end->format('j M Y');
        }

        return ($start ?? $end)->format('j M Y');
    }
}
