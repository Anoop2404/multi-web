<?php

namespace App\Services\Calendar;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\McqExam;
use App\Models\SahodayaRegistrationWindow;
use App\Models\Tenant;
use App\Models\TrainingProgram;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CalendarAggregationService
{
    /**
     * @return Collection<int, array{id: string, title: string, module: string, kind: string, start: string, end: ?string, href: ?string}>
     */
    public function forSahodaya(Tenant $sahodaya, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $from ??= now()->startOfMonth();
        $to ??= now()->addMonths(3)->endOfMonth();

        return collect()
            ->merge($this->festEvents($sahodaya->id, $from, $to))
            ->merge($this->trainingPrograms($sahodaya->id, $from, $to))
            ->merge($this->mcqExams($sahodaya->id, $from, $to))
            ->merge($this->festItemSchedules($sahodaya->id, $from, $to))
            ->merge($this->membershipWindows($sahodaya->id, $from, $to))
            ->sortBy('start')
            ->values();
    }

    /**
     * @return Collection<int, array{id: string, title: string, module: string, kind: string, start: string, end: ?string, href: ?string}>
     */
    public function forSchool(Tenant $school, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $sahodayaId = $school->parent_id;
        if (! $sahodayaId) {
            return collect();
        }

        $from ??= now()->startOfMonth();
        $to ??= now()->addMonths(3)->endOfMonth();

        return $this->forSahodaya(Tenant::find($sahodayaId) ?? new Tenant(['id' => $sahodayaId]), $from, $to)
            ->filter(fn (array $e) => in_array($e['module'], ['membership', 'fest', 'mcq', 'training'], true));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function festEvents(string $sahodayaId, Carbon $from, Carbon $to): Collection
    {
        return FestEvent::where('tenant_id', $sahodayaId)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('registration_open', [$from, $to])
                    ->orWhereBetween('registration_close', [$from, $to])
                    ->orWhereBetween('event_start', [$from, $to])
                    ->orWhereBetween('event_end', [$from, $to]);
            })
            ->get()
            ->flatMap(function (FestEvent $event) use ($sahodayaId) {
                $events = [];
                if ($event->registration_open) {
                    $events[] = $this->entry(
                        "fest-reg-{$event->id}",
                        "{$event->title} — registration opens",
                        'fest',
                        'registration',
                        $event->registration_open->toDateString(),
                        $event->registration_close?->toDateString(),
                        "/sahodaya-admin/{$sahodayaId}/events/{$event->id}",
                    );
                }
                if ($event->event_start) {
                    $events[] = $this->entry(
                        "fest-event-{$event->id}",
                        "{$event->title} — event",
                        'fest',
                        'event',
                        $event->event_start->toDateString(),
                        $event->event_end?->toDateString(),
                        "/sahodaya-admin/{$sahodayaId}/events/{$event->id}",
                    );
                }

                return $events;
            });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function trainingPrograms(string $sahodayaId, Carbon $from, Carbon $to): Collection
    {
        return TrainingProgram::where('tenant_id', $sahodayaId)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('registration_open', [$from, $to])
                    ->orWhereBetween('registration_close', [$from, $to]);
            })
            ->get()
            ->map(fn (TrainingProgram $p) => $this->entry(
                "training-{$p->id}",
                "{$p->title} — registration",
                'training',
                'registration',
                $p->registration_open?->toDateString() ?? now()->toDateString(),
                $p->registration_close?->toDateString(),
                "/sahodaya-admin/{$sahodayaId}/training/{$p->id}",
            ));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function mcqExams(string $sahodayaId, Carbon $from, Carbon $to): Collection
    {
        return McqExam::where('tenant_id', $sahodayaId)
            ->whereBetween('scheduled_at', [$from, $to])
            ->get()
            ->map(fn (McqExam $exam) => $this->entry(
                "mcq-{$exam->id}",
                "{$exam->title} — exam",
                'mcq',
                'exam',
                $exam->scheduled_at?->toDateString() ?? now()->toDateString(),
                null,
                "/sahodaya-admin/{$sahodayaId}/mcq/exams/{$exam->id}",
            ));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function membershipWindows(string $sahodayaId, Carbon $from, Carbon $to): Collection
    {
        return SahodayaRegistrationWindow::where('sahodaya_id', $sahodayaId)
            ->get()
            ->flatMap(function (SahodayaRegistrationWindow $w) use ($sahodayaId, $from, $to) {
                $events = [];
                if ($w->add_open && $w->add_open->between($from, $to)) {
                    $events[] = $this->entry(
                        "mem-add-open-{$w->id}",
                        "Membership registration opens ({$w->academic_year})",
                        'membership',
                        'registration',
                        $w->add_open->toDateString(),
                        $w->add_close?->toDateString(),
                        "/sahodaya-admin/{$sahodayaId}/membership/submissions",
                    );
                }
                if ($w->add_close && $w->add_close->between($from, $to)) {
                    $events[] = $this->entry(
                        "mem-add-close-{$w->id}",
                        "Membership registration closes ({$w->academic_year})",
                        'membership',
                        'deadline',
                        $w->add_close->toDateString(),
                        null,
                        "/sahodaya-admin/{$sahodayaId}/membership/submissions",
                    );
                }
                if ($w->registration_ends_at && $w->registration_ends_at->between($from, $to)) {
                    $events[] = $this->entry(
                        "mem-reg-end-{$w->id}",
                        "Membership renewal due ({$w->academic_year})",
                        'membership',
                        'deadline',
                        $w->registration_ends_at->toDateString(),
                        null,
                        "/sahodaya-admin/{$sahodayaId}/membership/payments",
                    );
                }

                return $events;
            });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function festItemSchedules(string $sahodayaId, Carbon $from, Carbon $to): Collection
    {
        $eventIds = FestEvent::where('tenant_id', $sahodayaId)->pluck('id');

        return FestEventItem::whereIn('event_id', $eventIds)
            ->whereNotNull('competition_start')
            ->whereBetween('competition_start', [$from, $to])
            ->with('event')
            ->limit(500)
            ->get()
            ->map(fn (FestEventItem $item) => $this->entry(
                "fest-item-{$item->id}",
                ($item->title ?? 'Item').' — competition ('.($item->event?->title ?? 'Event').')',
                'fest',
                'schedule',
                $item->competition_start->toDateString(),
                $item->competition_end?->toDateString(),
                "/sahodaya-admin/{$sahodayaId}/events/{$item->event_id}/schedule/items",
            ));
    }

    /** @return array{id: string, title: string, module: string, kind: string, start: string, end: ?string, href: ?string} */
    private function entry(
        string $id,
        string $title,
        string $module,
        string $kind,
        string $start,
        ?string $end,
        ?string $href,
    ): array {
        return compact('id', 'title', 'module', 'kind', 'start', 'end', 'href');
    }
}
