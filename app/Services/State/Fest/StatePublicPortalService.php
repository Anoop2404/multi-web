<?php

namespace App\Services\State\Fest;

use App\Models\FestStateProgramItem;
use App\Models\State\StateCertificate;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestMark;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateItemResult;
use App\Models\State\StateItemSchedule;
use App\Models\State\StateSahodaya;
use App\Models\State\StateVenue;
use Illuminate\Support\Collection;

/**
 * Phase 7 of the State Kalotsav module — what the public may see.
 *
 * Fail-closed on two independent gates. A section is visible only when its own setting is on
 * (public_schedule_visible / public_results_visible / public_ranking_visible), and a result appears
 * only when that item's own result is published or locked. So an operator can compute and check every
 * ranking internally with nothing leaking, and can stage the schedule publicly before any result
 * exists — which is the ordinary sequence, not an edge case.
 *
 * Nothing here reads a participant's contact details, date of birth or admission number. The public
 * portal shows a name, a School, a Sahodaya and a position; everything else stays inside the State
 * office.
 */
class StatePublicPortalService
{
    public function __construct(
        private StateEventSettings $settings,
        private StateResultService $results,
    ) {}

    /** @return array<string, bool> */
    public function visibility(StateFestEvent $event): array
    {
        $settings = $this->settings->all($event);

        return [
            'schedule' => (bool) ($settings['public_schedule_visible'] ?? false),
            // Results need the event-wide publish flag as well as the public one: the public toggle
            // stages visibility, it does not substitute for publishing.
            'results' => (bool) ($settings['public_results_visible'] ?? false) && (bool) $event->results_published,
            'ranking' => (bool) ($settings['public_ranking_visible'] ?? false) && (bool) $event->results_published,
        ];
    }

    public function assertVisible(StateFestEvent $event, string $section): void
    {
        abort_unless(
            $this->visibility($event)[$section] ?? false,
            404,
            'That is not published yet.',
        );
    }

    /** The events a member of the public may see at all — anything with one section switched on. */
    public function publicEvents(): Collection
    {
        return StateFestEvent::query()
            ->orderByDesc('starts_on')->limit(20)->get()
            ->filter(fn (StateFestEvent $e) => in_array(true, $this->visibility($e), true))
            ->values();
    }

    /**
     * The schedule, day by day. Reporting time is included because that is the time a participant
     * actually needs; the start time alone has people arriving as their item begins.
     *
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    public function schedule(StateFestEvent $event): Collection
    {
        $items = FestStateProgramItem::where('state_program_id', $event->state_program_id)
            ->get()->keyBy('id');
        $venues = StateVenue::where('state_event_id', $event->id)->get()->keyBy('id');

        return StateItemSchedule::where('state_event_id', $event->id)
            ->orderBy('scheduled_on')->orderBy('starts_at')->get()
            ->map(fn (StateItemSchedule $s) => [
                'date' => $s->scheduled_on?->toDateString(),
                'day' => $s->scheduled_on?->format('D, d M Y'),
                'reporting_at' => $s->reporting_at ? substr($s->reporting_at, 0, 5) : null,
                'starts_at' => $s->starts_at ? substr($s->starts_at, 0, 5) : null,
                'ends_at' => $s->ends_at ? substr($s->ends_at, 0, 5) : null,
                'item_code' => $items->get($s->item_id)?->item_code,
                'item' => $items->get($s->item_id)?->title,
                'venue' => $venues->get($s->venue_id)?->name,
            ])
            ->groupBy('day');
    }

    /**
     * Published item results, winners only.
     *
     * The public sees positions, not scores: a mark out of 100 invites argument about a judgement
     * that is already final, and the State publishes placings and grades.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function results(StateFestEvent $event, ?string $itemId = null): Collection
    {
        $published = StateItemResult::where('state_event_id', $event->id)
            ->whereIn('status', [StateItemResult::PUBLISHED, StateItemResult::LOCKED])
            ->when($itemId, fn ($q) => $q->where('item_id', $itemId))
            ->pluck('item_id');

        if ($published->isEmpty()) {
            return collect();
        }

        $items = FestStateProgramItem::whereIn('id', $published)->get()->keyBy('id');

        $registrations = StateFestRegistration::where('state_event_id', $event->id)
            ->whereIn('item_id', $published)->with('participants')->get()->keyBy('id');

        return StateFestMark::where('state_event_id', $event->id)
            ->whereIn('registration_id', $registrations->keys())
            ->whereNotNull('position')
            ->get()
            ->groupBy(fn (StateFestMark $m) => $registrations[$m->registration_id]->item_id)
            ->map(fn (Collection $marks, string $id) => [
                'item_id' => $id,
                'item_code' => $items->get($id)?->item_code,
                'item' => $items->get($id)?->title,
                'winners' => $marks->sortBy('position')->values()->map(function (StateFestMark $m) use ($registrations) {
                    $registration = $registrations[$m->registration_id];

                    return [
                        'position' => (int) $m->position,
                        'grade' => $m->grade,
                        'participants' => $registration->participants
                            ->filter(fn ($p) => $p->isCompeting())->pluck('student_name')->implode(', '),
                        'school' => $registration->school_name ?: $registration->school_id,
                        'sahodaya' => $registration->sahodaya_name,
                    ];
                })->all(),
            ])
            ->sortBy('item_code')->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function ranking(StateFestEvent $event): Collection
    {
        return $this->results->sahodayaStandings($event);
    }

    /**
     * A Sahodaya's own page: which of its Schools earned its points, and every placing it took.
     *
     * @return array<string, mixed>
     */
    public function sahodaya(StateFestEvent $event, StateSahodaya $sahodaya): array
    {
        $standing = $this->ranking($event)->firstWhere('sahodaya_id', $sahodaya->id);

        return [
            'sahodaya' => ['id' => $sahodaya->id, 'name' => $sahodaya->name, 'district' => $sahodaya->district],
            'standing' => $standing,
            'schools' => $this->results->schoolContribution($event, $sahodaya->id),
            'placings' => $this->results($event)
                ->flatMap(fn (array $item) => collect($item['winners'])
                    ->where('sahodaya', $sahodaya->name)
                    ->map(fn (array $w) => $w + ['item' => $item['item'], 'item_code' => $item['item_code']]))
                ->sortBy(['item_code', 'position'])->values(),
        ];
    }

    /**
     * Certificate verification. Always answers — a code that resolves to nothing returns "not found"
     * rather than an error, and a stale or superseded certificate says so plainly instead of
     * presenting itself as valid. Verification is not gated on the public visibility settings: a
     * certificate in someone's hand is already public, and refusing to confirm it helps nobody.
     *
     * @return array<string, mixed>
     */
    public function verify(?string $code): array
    {
        $certificate = $code ? app(StateCertificateService::class)->verify($code) : null;

        if (! $certificate) {
            return ['found' => false, 'valid' => false, 'message' => 'No certificate matches that code.'];
        }

        $event = StateFestEvent::find($certificate->state_event_id);

        return [
            'found' => true,
            'valid' => $certificate->status === StateCertificate::GENERATED,
            'message' => match ($certificate->status) {
                StateCertificate::STALE => 'This certificate was issued from a result that has since been corrected. Ask the State office for a reissued copy.',
                StateCertificate::SUPERSEDED => 'This certificate has been replaced by a later one. The replacement is the valid copy.',
                default => 'This certificate is valid.',
            },
            'certificate' => [
                'number' => $certificate->certificate_number,
                'type' => StateCertificate::TYPES[$certificate->type] ?? $certificate->type,
                'recipient' => $certificate->recipient_name,
                'sahodaya' => $certificate->sahodaya_name,
                'school' => $certificate->school_name,
                'item' => $certificate->item_name ?: $certificate->item_code,
                'position' => $certificate->position,
                'grade' => $certificate->grade,
                'issued_on' => $certificate->generated_at?->format('d M Y'),
                'event' => $event?->name,
            ],
        ];
    }
}
