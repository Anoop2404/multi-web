<?php

namespace App\Services\State\Fest;

use App\Models\State\StateEventStaff;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateItemSchedule;
use App\Models\State\StateMealAllocation;
use App\Models\State\StateMealSession;
use App\Models\State\StateSahodaya;
use App\Models\State\StateStaffDuty;
use App\Models\State\StateVenue;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Phase 10 of the State Kalotsav module — catering and duty rosters.
 *
 * Entitlement is computed from who is actually competing that day, not from the whole contingent: a
 * Sahodaya with 90 entries spread over three days does not eat 90 lunches on each of them. Withdrawn
 * and standby members are excluded, since neither is at the venue.
 */
class StateHospitalityService
{
    /** Escorts, coordinators and drivers who come with a contingent but are not entered for anything. */
    public const ESCORT_ALLOWANCE = 2;

    // ── Catering ───────────────────────────────────────────────────────────────────────────

    /** @return Collection<int, array<string, mixed>> */
    public function sessions(StateFestEvent $event): Collection
    {
        $venues = StateVenue::where('state_event_id', $event->id)->get()->keyBy('id');

        return StateMealSession::where('state_event_id', $event->id)
            ->orderBy('served_on')->orderBy('session')->get()
            ->map(function (StateMealSession $session) use ($venues) {
                $allocations = StateMealAllocation::where('meal_session_id', $session->id)->get();

                return [
                    'id' => $session->id,
                    'served_on' => $session->served_on?->toDateString(),
                    'day' => $session->served_on?->format('D, d M'),
                    'session' => $session->session,
                    'menu' => $session->menu,
                    'venue' => $venues->get($session->venue_id)?->name,
                    'venue_id' => $session->venue_id,
                    'capacity' => $session->capacity,
                    'is_active' => (bool) $session->is_active,
                    'entitled' => (int) $allocations->sum('entitled_count'),
                    'issued' => (int) $allocations->sum('issued_count'),
                    'sahodayas' => $allocations->count(),
                    // Flagged rather than silently exceeded: a sitting over capacity is a queue in
                    // the corridor, and the organiser wants to know before the day.
                    'over_capacity' => $session->capacity !== null
                        && (int) $allocations->sum('entitled_count') > $session->capacity,
                ];
            });
    }

    /** @param  array<string, mixed>  $data */
    public function saveSession(StateFestEvent $event, array $data, ?string $sessionId = null): StateMealSession
    {
        $attributes = [
            'state_event_id' => $event->id,
            'state_id' => $event->state_id,
            'served_on' => $data['served_on'],
            'session' => $data['session'],
            'menu' => $data['menu'] ?? null,
            'venue_id' => $data['venue_id'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ];

        if ($sessionId) {
            $session = StateMealSession::where('state_event_id', $event->id)->findOrFail($sessionId);
            $session->forceFill($attributes)->save();

            return $session;
        }

        // whereDate, not where: served_on is a date column that SQLite stores with a time part, so
        // an equality check against "2026-01-12" silently matches nothing and the unique index
        // raises a raw constraint error instead of a readable message.
        $clash = StateMealSession::where('state_event_id', $event->id)
            ->whereDate('served_on', $data['served_on'])->where('session', $data['session'])->first();

        if ($clash) {
            throw ValidationException::withMessages([
                'session' => "There is already a {$data['session']} sitting on that date. Edit it rather than adding a second.",
            ]);
        }

        return StateMealSession::create($attributes);
    }

    /**
     * How many meals each Sahodaya is entitled to at a sitting.
     *
     * Counted from the participants competing on that date, plus a small escort allowance. A sitting
     * on a date with nothing scheduled entitles nobody — which is the honest answer, and is visible
     * enough that an operator notices the date is wrong.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function entitlement(StateFestEvent $event, StateMealSession $session): Collection
    {
        $itemIds = StateItemSchedule::where('state_event_id', $event->id)
            ->whereDate('scheduled_on', $session->served_on)
            ->pluck('item_id');

        $directory = StateSahodaya::query()->get()->keyBy('id');

        $registrations = StateFestRegistration::where('state_event_id', $event->id)
            ->where('status', 'approved')
            ->whereIn('item_id', $itemIds)
            ->with('participants')->get();

        $existing = StateMealAllocation::where('meal_session_id', $session->id)->get()->keyBy('sahodaya_id');

        return $registrations
            ->groupBy(fn (StateFestRegistration $r) => $r->sahodaya_id ?? 'unattributed')
            ->map(function (Collection $group, string $sahodayaId) use ($directory, $existing) {
                // A participant entered for three items on the same day eats one lunch.
                $heads = $group->flatMap(fn (StateFestRegistration $r) => $r->participants
                    ->filter(fn ($p) => $p->isCompeting())
                    ->pluck('student_name'))
                    ->unique()->count();

                $allocation = $existing->get($sahodayaId);

                return [
                    'sahodaya_id' => $sahodayaId,
                    'sahodaya' => $directory->get($sahodayaId)?->name
                        ?? $group->first()->sahodaya_name ?? 'Unattributed',
                    'participants' => $heads,
                    'entitled' => $heads + ($heads > 0 ? self::ESCORT_ALLOWANCE : 0),
                    'recorded_entitled' => $allocation?->entitled_count,
                    'issued' => $allocation?->issued_count ?? 0,
                    'notes' => $allocation?->notes,
                    'issued_at' => $allocation?->issued_at?->toDateTimeString(),
                ];
            })
            ->sortBy('sahodaya')->values();
    }

    /**
     * Write the computed entitlement onto the session, so what the counter works from is a fixed
     * figure rather than something that shifts as entries are edited on the day.
     *
     * @return array{rows: int, meals: int}
     */
    public function freezeEntitlement(StateFestEvent $event, StateMealSession $session): array
    {
        $rows = $this->entitlement($event, $session);
        $meals = 0;

        foreach ($rows as $row) {
            if ($row['sahodaya_id'] === 'unattributed') {
                continue;
            }

            StateMealAllocation::updateOrCreate(
                ['meal_session_id' => $session->id, 'sahodaya_id' => $row['sahodaya_id']],
                [
                    'state_event_id' => $event->id,
                    'sahodaya_name' => $row['sahodaya'],
                    'entitled_count' => $row['entitled'],
                ],
            );

            $meals += $row['entitled'];
        }

        return ['rows' => $rows->count(), 'meals' => $meals];
    }

    /** @param  array{sahodaya_id: string, issued_count: int, notes?: ?string}  $data */
    public function issue(StateFestEvent $event, StateMealSession $session, array $data, array $context = []): StateMealAllocation
    {
        $allocation = StateMealAllocation::where('meal_session_id', $session->id)
            ->where('sahodaya_id', $data['sahodaya_id'])->first();

        if (! $allocation) {
            $name = StateSahodaya::find($data['sahodaya_id'])?->name;

            $allocation = new StateMealAllocation([
                'meal_session_id' => $session->id,
                'state_event_id' => $event->id,
                'sahodaya_id' => $data['sahodaya_id'],
                'sahodaya_name' => $name,
                'entitled_count' => 0,
            ]);
        }

        $allocation->forceFill([
            'issued_count' => $data['issued_count'],
            'notes' => $data['notes'] ?? $allocation->notes,
            'issued_by_user_id' => $context['user_id'] ?? null,
            'issued_by_name' => $context['user_name'] ?? null,
            'issued_at' => now(),
        ])->save();

        return $allocation;
    }

    /**
     * The kitchen's number: total meals per sitting, and the running variance against entitlement.
     *
     * @return array<string, mixed>
     */
    public function cateringSummary(StateFestEvent $event): array
    {
        $sessions = $this->sessions($event);

        return [
            'sessions' => $sessions,
            'totals' => [
                'entitled' => (int) $sessions->sum('entitled'),
                'issued' => (int) $sessions->sum('issued'),
                'variance' => (int) $sessions->sum('issued') - (int) $sessions->sum('entitled'),
            ],
        ];
    }

    // ── Volunteers and officials ───────────────────────────────────────────────────────────

    /**
     * The duty roster, by day and session.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function roster(StateFestEvent $event, ?string $date = null): Collection
    {
        $staff = StateEventStaff::where('state_event_id', $event->id)->get()->keyBy('id');
        $venues = StateVenue::where('state_event_id', $event->id)->get()->keyBy('id');

        return StateStaffDuty::where('state_event_id', $event->id)
            ->when($date, fn ($q) => $q->whereDate('duty_on', $date))
            ->orderBy('duty_on')->orderBy('session')->get()
            ->map(fn (StateStaffDuty $duty) => [
                'id' => $duty->id,
                'duty_on' => $duty->duty_on?->toDateString(),
                'day' => $duty->duty_on?->format('D, d M'),
                'session' => $duty->session,
                'staff_id' => $duty->staff_id,
                'staff' => $staff->get($duty->staff_id)?->name ?? 'Unknown',
                'role' => $staff->get($duty->staff_id)?->role,
                'phone' => $staff->get($duty->staff_id)?->phone,
                'venue' => $venues->get($duty->venue_id)?->name,
                'venue_id' => $duty->venue_id,
                'duty' => $duty->duty,
                'notes' => $duty->notes,
            ]);
    }

    /** @param  array<string, mixed>  $data */
    public function assignDuty(StateFestEvent $event, array $data): StateStaffDuty
    {
        $staff = StateEventStaff::where('state_event_id', $event->id)->findOrFail($data['staff_id']);

        // The same person cannot be at two stages in one session. Refused rather than recorded,
        // because a roster with a double-booking is discovered by a stage standing unstaffed.
        $clash = StateStaffDuty::where('state_event_id', $event->id)
            ->where('staff_id', $staff->id)
            ->whereDate('duty_on', $data['duty_on'])
            ->where(fn ($q) => $q->where('session', $data['session'])->orWhere('session', 'full_day'))
            ->first();

        if ($clash) {
            throw ValidationException::withMessages([
                'staff_id' => "{$staff->name} is already on duty in that session.",
            ]);
        }

        return StateStaffDuty::create([
            'state_event_id' => $event->id,
            'state_id' => $event->state_id,
            'staff_id' => $staff->id,
            'duty_on' => $data['duty_on'],
            'session' => $data['session'],
            'venue_id' => $data['venue_id'] ?? null,
            'duty' => $data['duty'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function removeDuty(StateFestEvent $event, string $dutyId): void
    {
        StateStaffDuty::where('state_event_id', $event->id)->findOrFail($dutyId)->delete();
    }

    /**
     * Places scheduled to be in use with nobody rostered to them. The whole point of keeping a roster.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function uncoveredVenues(StateFestEvent $event): Collection
    {
        $venues = StateVenue::where('state_event_id', $event->id)->get()->keyBy('id');

        $scheduled = StateItemSchedule::where('state_event_id', $event->id)
            ->whereNotNull('venue_id')->get()
            ->groupBy(fn (StateItemSchedule $s) => $s->scheduled_on?->toDateString().'|'.$s->venue_id);

        $rostered = StateStaffDuty::where('state_event_id', $event->id)->get()
            ->flatMap(fn (StateStaffDuty $d) => [$d->duty_on?->toDateString().'|'.$d->venue_id => true])
            ->all();

        return collect($scheduled)
            ->reject(fn (Collection $items, string $key) => isset($rostered[$key]))
            ->map(function (Collection $items, string $key) use ($venues) {
                [$date, $venueId] = explode('|', $key);

                return [
                    'date' => $date,
                    'venue' => $venues->get($venueId)?->name ?? 'Unknown venue',
                    'items' => $items->count(),
                ];
            })
            ->sortBy(['date', 'venue'])->values();
    }
}
