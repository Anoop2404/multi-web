<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestItemHead;
use App\Services\Events\FestItemHeadService;
use App\Support\FestPageActivity;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FestItemHeadController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        return redirect()->route('sahodaya.events.competition.index', [
            'tenantId' => $tenantId,
            'event' => $event->id,
        ]);
    }

    /**
     * Head-first permalink: /sahodaya-admin/{tenantId}/sports/heads/{head} always resolves
     * to this head's own event, regardless of which discipline event id it currently lives
     * on (or the season, if it hasn't been promoted yet). Gives each sport a stable URL
     * instead of admins/bookmarks having to reference a discipline event id that only
     * exists once promotion has run.
     *
     * Redirects to the same Overview page the existing "Manage" link on the Sports hub's
     * events table already opens for a discipline event (?overview=1) -- this is purely
     * about giving that destination a stable head-based address, not about changing where
     * "Manage" takes an admin.
     */
    public function showByHead(string $tenantId, FestItemHead $head)
    {
        abort_if($head->tenant_id !== $this->sahodaya->id, 403);

        $eventId = $head->discipline_event_id ?: $head->event_id;
        abort_if(! $eventId, 404, 'This Event Head is not linked to any event yet.');

        return redirect()->route('sahodaya.events.show', [
            'tenantId' => $tenantId,
            'event' => $eventId,
            'overview' => 1,
        ]);
    }

    /**
     * Sports uses sport events (Head = Event) — every head write endpoint is closed
     * for sports so no page can recreate head rows or edit head data on the side.
     */
    private function abortIfSports(FestEvent $event): void
    {
        abort_if(
            $event->event_type === 'sports',
            422,
            'Sports no longer uses Event Heads — manage each sport from Setup → Sport events.',
        );
    }

    public function updateWindows(Request $request, string $tenantId, FestEvent $event, FestItemHead $head, FestItemHeadService $headService)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        $this->abortIfSports($event);
        abort_if((int) $head->event_id !== (int) $event->id, 404);

        $data = $request->validate([
            'name'              => 'sometimes|required|string|max:120',
            'sport_discipline'  => 'nullable|string|max:60',
            'is_team_heading'   => 'nullable|boolean',
            'reg_start'         => 'nullable|date',
            'reg_end'           => 'nullable|date|after_or_equal:reg_start',
            'competition_start' => 'nullable|date',
            'competition_end'   => 'nullable|date|after_or_equal:competition_start',
            'schedule_mode'     => 'nullable|in:same_time,different_days',
            'competition_time'  => 'nullable|date_format:H:i',
            'apply_to_items'    => 'nullable|boolean',
            'default_item_fee'  => 'nullable|numeric|min:0',
            'extra_item_fee'    => 'nullable|numeric|min:0',
            'school_registration_fee' => 'nullable|numeric|min:0',
            'student_registration_fee' => 'nullable|numeric|min:0',
            'team_registration_fee' => 'nullable|numeric|min:0',
            'included_items_per_student' => 'nullable|integer|min:0|max:50',
            'included_teams' => 'nullable|integer|min:0|max:50',
            'verification_policy' => 'nullable|in:verified_only,all_students',
            'approval_policy' => 'nullable|in:auto,manual',
            'max_participants' => 'nullable|integer|min:0',
            'max_teams' => 'nullable|integer|min:0',
            'status' => 'nullable|in:draft,published,registration_open,ongoing,completed',
            'venue' => 'nullable|string|max:255',
            'event_start' => 'nullable|date',
            'event_end' => 'nullable|date|after_or_equal:event_start',
        ]);

        $hasSchedulePayload = $request->hasAny([
            'reg_start',
            'reg_end',
            'competition_start',
            'competition_end',
            'competition_time',
            'schedule_mode',
        ]);

        $applyToItems = $hasSchedulePayload && (bool) ($data['apply_to_items'] ?? true);
        unset($data['apply_to_items']);

        if (array_key_exists('name', $data)) {
            $data['slug'] = Str::slug($data['name']);
        }

        $scheduleMode = $data['schedule_mode'] ?? $head->schedule_mode ?? 'different_days';
        if ($request->has('schedule_mode')) {
            $data['schedule_mode'] = $scheduleMode;
        }

        // In same-time mode all items run together on one day: normalise the head's
        // competition window to a single day so downstream displays stay consistent.
        if ($scheduleMode === 'same_time' && ! empty($data['competition_start'])) {
            $data['competition_end'] = $data['competition_start'];
        }

        if (array_key_exists('default_item_fee', $data)) {
            $data['default_item_fee'] = isset($data['default_item_fee']) && $data['default_item_fee'] !== ''
                ? (float) $data['default_item_fee']
                : null;
        }
        if (array_key_exists('extra_item_fee', $data)) {
            $data['extra_item_fee'] = isset($data['extra_item_fee']) && $data['extra_item_fee'] !== ''
                ? (float) $data['extra_item_fee']
                : null;
        }

        foreach (['school_registration_fee', 'student_registration_fee', 'team_registration_fee'] as $feeKey) {
            if (array_key_exists($feeKey, $data)) {
                $data[$feeKey] = isset($data[$feeKey]) && $data[$feeKey] !== ''
                    ? (float) $data[$feeKey]
                    : null;
            }
        }
        foreach (['max_participants', 'max_teams'] as $capKey) {
            if (array_key_exists($capKey, $data)) {
                $data[$capKey] = isset($data[$capKey]) && $data[$capKey] !== ''
                    ? (int) $data[$capKey]
                    : null;
            }
        }

        $head->update($data);

        // What we push down to items depends on the mode:
        //  - same_time      → the single date + time for every item (so they run together)
        //  - different_days → only the registration window; each item keeps its own day/time
        $itemPayload = $hasSchedulePayload
            ? ($scheduleMode === 'same_time'
                ? [
                    'reg_start'         => $data['reg_start'] ?? null,
                    'reg_end'           => $data['reg_end'] ?? null,
                    'competition_start' => $data['competition_start'] ?? null,
                    'competition_end'   => $data['competition_end'] ?? null,
                    'competition_time'  => $data['competition_time'] ?? null,
                ]
                : [
                    'reg_start' => $data['reg_start'] ?? null,
                    'reg_end'   => $data['reg_end'] ?? null,
                ])
            : [];

        $updated = $applyToItems
            ? $headService->applyWindowToItems($head, $event, $itemPayload, true)
            : 0;

        return back()->with('success', $updated > 0
            ? "Head schedule saved and applied to {$updated} item(s)."
            : 'Head schedule saved.');
    }

    public function updateNotifications(Request $request, string $tenantId, FestEvent $event, FestItemHead $head, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        $this->abortIfSports($event);
        abort_if((int) $head->event_id !== (int) $event->id, 404);

        $data = $request->validate([
            'disabled_triggers' => 'nullable|array',
            'disabled_triggers.*' => 'string|in:'.implode(',', FestItemHead::NOTIFICATION_TRIGGERS),
            'extra_recipient_user_ids' => 'nullable|array',
            'extra_recipient_user_ids.*' => 'integer',
        ]);

        $disabledTriggers = array_values(array_unique($data['disabled_triggers'] ?? []));

        // Extra recipients must be existing platform users in this Sahodaya — never
        // free-text emails. Silently drop anything that doesn't resolve to a real,
        // appropriately-roled user rather than trusting the submitted id list as-is.
        $requestedIds = array_map('intval', $data['extra_recipient_user_ids'] ?? []);
        $validUserIds = $requestedIds === [] ? [] : \App\Models\User::role(['sahodaya_admin', 'sahodaya_staff', 'event_coordinator'])
            ->where('tenant_id', $this->sahodaya->id)
            ->whereIn('id', $requestedIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $head->update([
            'notification_settings' => [
                'disabled_triggers' => $disabledTriggers,
                'extra_recipient_user_ids' => $validUserIds,
            ],
        ]);

        $audit->festEvent($event, FestPageActivity::SETTINGS, 'fest.item_head.notifications_updated', "Notification settings updated for Event Head: {$head->name}", [
            'head_id' => $head->id,
            'disabled_triggers' => $disabledTriggers,
            'extra_recipient_count' => count($validUserIds),
        ]);

        return back()->with('success', 'Notification settings saved.');
    }

    public function store(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit, FestItemHeadService $headService)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        // Sports: heads are gone (Head = Event). Any leftover form posting here
        // creates a sport event directly instead of a dead head row.
        if ($event->event_type === 'sports') {
            abort_unless($event->parent_event_id === null, 422, 'Add sports on the season setup page.');

            $sportData = $request->validate([
                'name' => 'required|string|max:120',
                'sport_discipline' => 'nullable|string|max:60',
                'is_team_heading' => 'nullable|boolean',
            ]);

            $sport = app(\App\Services\Events\FestSportsEventSyncService::class)->addSport($event, $sportData);

            return redirect("/sahodaya-admin/{$this->sahodaya->id}/events/{$sport->id}")
                ->with('success', "Sport event \"{$sport->title}\" created.");
        }

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'sport_discipline' => 'nullable|string|max:60',
            'is_team_heading' => 'nullable|boolean',
            'parent_id' => 'nullable|exists:fest_item_heads,id',
            // Event Head owns all registration/fee settings (FRD-04) — captured at
            // creation time so a head is fully configured in one step, same as
            // standing up an independent event.
            'school_registration_fee' => 'nullable|numeric|min:0',
            'student_registration_fee' => 'nullable|numeric|min:0',
            'team_registration_fee' => 'nullable|numeric|min:0',
            'included_items_per_student' => 'nullable|integer|min:0|max:50',
            'included_teams' => 'nullable|integer|min:0|max:50',
            'verification_policy' => 'nullable|in:verified_only,all_students',
            'approval_policy' => 'nullable|in:auto,manual',
            'max_participants' => 'nullable|integer|min:0',
            'max_teams' => 'nullable|integer|min:0',
        ]);

        $order = (int) FestItemHead::forTenant($this->sahodaya->id)->forEvent($event->id)->max('sort_order') + 1;

        $numeric = fn (string $key) => isset($data[$key]) && $data[$key] !== '' ? (float) $data[$key] : null;
        $intNullable = fn (string $key) => isset($data[$key]) && $data[$key] !== '' ? (int) $data[$key] : null;

        $head = FestItemHead::create([
            'tenant_id' => $this->sahodaya->id,
            'event_id' => $event->id,
            'event_type' => $event->event_type,
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'sport_discipline' => $data['sport_discipline'] ?? null,
            'is_team_heading' => (bool) ($data['is_team_heading'] ?? true),
            'sort_order' => $order,
            'school_registration_fee' => $numeric('school_registration_fee'),
            'student_registration_fee' => $numeric('student_registration_fee'),
            'team_registration_fee' => $numeric('team_registration_fee'),
            'included_items_per_student' => (int) ($data['included_items_per_student'] ?? 0),
            'included_teams' => (int) ($data['included_teams'] ?? 0),
            'verification_policy' => $data['verification_policy'] ?? 'all_students',
            'approval_policy' => $data['approval_policy'] ?? 'auto',
            'status' => 'draft',
            'max_participants' => $intNullable('max_participants'),
            'max_teams' => $intNullable('max_teams'),
        ]);

        // Head-first rebuild: a head is only ever "done" once it owns its own dedicated
        // discipline event — promote it immediately instead of leaving it pending on the
        // season hub. Idempotent/no-op for non-sports events and already-promoted heads.
        $headService->promoteIfSeason($event);

        $audit->festEvent($event, FestPageActivity::SETTINGS, 'fest.item_head.created', "Event Head created: {$head->name}", [
            'head_id' => $head->id,
            'school_registration_fee' => $head->school_registration_fee,
            'student_registration_fee' => $head->student_registration_fee,
            'team_registration_fee' => $head->team_registration_fee,
            'included_items_per_student' => $head->included_items_per_student,
            'included_teams' => $head->included_teams,
            'verification_policy' => $head->verification_policy,
            'approval_policy' => $head->approval_policy,
            'max_participants' => $head->max_participants,
            'max_teams' => $head->max_teams,
        ]);

        return back()->with('success', 'Event Head added with its fee settings.');
    }

    public function sync(string $tenantId, FestEvent $event, FestItemHeadService $service)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        if ($event->event_type === 'sports') {
            return redirect()->route('sahodaya.events.setup.index', [$tenantId, $event->id])
                ->with('info', 'Sports no longer uses Event Heads — add sports from the Setup hub.');
        }

        $service->ensureCatalogHeads($this->sahodaya->id, $event->event_type);
        $count = $service->syncEventHeads($event);

        return back()->with('success', "Synced {$count} Event Head(s) to this event.");
    }

    public function destroy(string $tenantId, FestEvent $event, FestItemHead $head, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        $this->abortIfSports($event);
        abort_if((int) $head->event_id !== (int) $event->id, 404);
        abort_if($head->catalog_key, 422, 'Catalog heads cannot be removed — disable items instead.');

        $name = $head->name;

        $head->items()->where('event_id', $event->id)->update(['head_id' => null]);
        $head->delete();

        $audit->festEvent($event, FestPageActivity::COMPETITION, 'fest.item_head.deleted', "Event Head removed: {$name}");

        return redirect()->route('sahodaya.events.competition.index', [
            'tenantId' => $tenantId,
            'event' => $event->id,
        ])->with('success', 'Event Head removed.');
    }
}
