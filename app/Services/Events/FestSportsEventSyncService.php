<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestItemHead;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Keeps a Sports season hub's child sport FestEvents (Athletics, Chess, …) in sync.
 *
 * Head = Event unification: each sport is its own FestEvent. Sport events are only
 * CREATED on explicit admin action ($createMissing = true — "Add sport" / catalog
 * sync button / migration commands). Passive page-load syncs only update existing
 * children, so deleted sports stay deleted and tenants don't accumulate catalog
 * sports they never conduct.
 *
 * Catalog templates may still live as FestItemHead rows with event_id=null;
 * runtime sport events are FestEvent rows — no FestItemHead rows required.
 */
class FestSportsEventSyncService
{
    public function __construct(
        private FestItemHeadService $headService,
    ) {}

    /**
     * Sync catalog sports onto a season hub as child FestEvents.
     * No-op for non-sports events, child events, and standalone sport events
     * created via the new flow (top-level, no children, no heads).
     *
     * @return array{created: int, updated: int}
     */
    public function syncSeason(FestEvent $season, bool $createMissing = false): array
    {
        if ($season->event_type !== 'sports' || $season->parent_event_id !== null) {
            return ['created' => 0, 'updated' => 0];
        }

        $hasChildren = FestEvent::where('parent_event_id', $season->id)->exists();
        $hasSeasonHeads = FestItemHead::forTenant($season->tenant_id)
            ->where('event_id', $season->id)
            ->exists();

        // A standalone sport event from the new flow is not a season hub — never
        // tag it or seed catalog sports under it.
        if (! $hasChildren && ! $hasSeasonHeads && $season->partition_role !== 'sports_season' && ! $createMissing) {
            return ['created' => 0, 'updated' => 0];
        }

        if ($season->partition_role !== 'sports_season') {
            $season->update(['partition_role' => 'sports_season']);
        }

        // Ensure catalog head templates (and item head_keys) exist for seeding.
        $this->headService->ensureCatalogHeads($season->tenant_id, 'sports');
        $this->headService->syncCatalogItemHeadKeys($season->tenant_id, 'sports');

        $definitions = FestItemHeadService::catalogHeadDefinitions();
        $created = 0;
        $updated = 0;

        foreach ($definitions as $index => $def) {
            $key = $def['key'];
            $slug = Str::slug($key);
            $title = $this->sportTitle($season, $def['name']);

            $existing = FestEvent::query()
                ->where('parent_event_id', $season->id)
                ->where(function ($q) use ($key, $slug) {
                    $q->where('catalog_key', $key)
                        ->orWhere('partition_key', $slug);
                })
                ->first();

            if ($existing) {
                $payload = [
                    'sport_discipline' => $def['sport_discipline'] ?? $existing->sport_discipline,
                    'catalog_key' => $key,
                    'is_team_heading' => (bool) ($def['is_team_heading'] ?? true),
                    'sort_order' => $index + 1,
                    'partition_role' => 'sports_discipline',
                    'partition_key' => $slug,
                    'sports_age_cutoff_date' => $season->sports_age_cutoff_date,
                ];
                // Registration windows: inherit from the season only when unset —
                // per-sport windows are authoritative once configured.
                foreach (['registration_open', 'registration_close', 'event_reg_start', 'event_reg_end'] as $window) {
                    if ($existing->{$window} === null && $season->{$window} !== null) {
                        $payload[$window] = $season->{$window};
                    }
                }
                // Do not overwrite customised titles once set.
                if (! filled($existing->title)) {
                    $payload['title'] = $title;
                }
                // Inherit season open status so schools see Chess/Aquatics, not only the hub.
                if (in_array($existing->status, ['draft', 'published'], true)
                    && in_array($season->status, ['registration_open', 'ongoing', 'published'], true)) {
                    $payload['status'] = $season->status;
                }
                $existing->update($payload);
                $this->ensureItemsOnSportEvent($season, $existing, $key);
                $updated++;

                continue;
            }

            // Prefer migrating a legacy head + its discipline event if present.
            $legacyHead = FestItemHead::forTenant($season->tenant_id)
                ->where('catalog_key', $key)
                ->where(function ($q) use ($season) {
                    $childIds = FestEvent::where('parent_event_id', $season->id)->pluck('id');
                    $q->where('event_id', $season->id)->orWhereIn('event_id', $childIds);
                })
                ->first();

            if ($legacyHead?->discipline_event_id) {
                $discipline = FestEvent::find($legacyHead->discipline_event_id);
                if ($discipline) {
                    $this->applyCatalogFields($discipline, $season, $def, $index + 1, $slug);
                    $this->copyHeadFeesIfEmpty($legacyHead, $discipline);
                    $this->copyHeadWindowsIfMissing($legacyHead, $discipline);
                    $this->ensureItemsOnSportEvent($season, $discipline, $key);
                    $updated++;

                    continue;
                }
            }

            if (! $createMissing) {
                continue;
            }

            $sport = $this->createSportEvent($season, [
                'title' => $title,
                'sport_discipline' => $def['sport_discipline'] ?? null,
                'catalog_key' => $key,
                'is_team_heading' => (bool) ($def['is_team_heading'] ?? true),
                'sort_order' => $index + 1,
                'partition_key' => $slug,
            ], $legacyHead);

            $this->ensureItemsOnSportEvent($season, $sport, $key);
            $created++;
        }

        if ($createMissing) {
            $created += $this->promoteCustomHeads($season);
        }

        $this->hideSeasonHubIfChildrenExist($season);

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * Alias used by season page loads — replaces FestItemHeadService::syncEventHeads
     * + promote. Passive: never creates sport events (explicit add only).
     */
    public function syncEventHeads(FestEvent $event): int
    {
        $result = $this->syncSeason($event);

        return $result['created'];
    }

    /**
     * Explicit "Add sport" action: create a single sport event under a season hub.
     * When the name matches a catalog sport, catalog metadata and items are used;
     * otherwise an empty custom sport event is created.
     *
     * @param  array{name: string, sport_discipline?: ?string, is_team_heading?: bool}  $data
     */
    public function addSport(FestEvent $season, array $data): FestEvent
    {
        $catalogKey = FestItemHeadService::resolveCatalogHeadKey([
            'title' => $data['name'],
            'sport_discipline' => $data['sport_discipline'] ?? null,
        ]);

        $def = collect(FestItemHeadService::catalogHeadDefinitions())
            ->firstWhere('key', $catalogKey);

        $slug = Str::slug($catalogKey ?: $data['name']);

        $existing = FestEvent::query()
            ->where('parent_event_id', $season->id)
            ->where(function ($q) use ($catalogKey, $slug) {
                $q->where('partition_key', $slug);
                if ($catalogKey) {
                    $q->orWhere('catalog_key', $catalogKey);
                }
            })
            ->first();

        if ($existing) {
            return $existing;
        }

        $sortBase = (int) FestEvent::where('parent_event_id', $season->id)->max('sort_order');

        $sport = $this->createSportEvent($season, [
            'title' => $this->sportTitle($season, $def['name'] ?? $data['name']),
            'sport_discipline' => $data['sport_discipline'] ?? ($def['sport_discipline'] ?? null),
            'catalog_key' => $catalogKey,
            'is_team_heading' => (bool) ($data['is_team_heading'] ?? ($def['is_team_heading'] ?? true)),
            'sort_order' => $sortBase + 1,
            'partition_key' => $slug,
        ], null);

        $this->ensureItemsOnSportEvent($season, $sport, $catalogKey);
        $this->hideSeasonHubIfChildrenExist($season);

        return $sport;
    }

    /**
     * Promote Event Heads that are NOT in the master catalog (custom sports an admin
     * created by name) into their own sport events. Catalog heads are handled by the
     * definitions loop in syncSeason().
     */
    public function promoteCustomHeads(FestEvent $season): int
    {
        $created = 0;

        $pending = FestItemHead::forTenant($season->tenant_id)
            ->where('event_id', $season->id)
            ->whereNull('discipline_event_id')
            ->whereNull('parent_id')
            ->whereNull('catalog_key')
            ->orderBy('sort_order')
            ->get();

        $sortBase = (int) FestEvent::where('parent_event_id', $season->id)->max('sort_order');

        foreach ($pending as $head) {
            $sport = $this->createSportEvent($season, [
                'title' => $this->sportTitle($season, $head->name),
                'sport_discipline' => $head->sport_discipline,
                'catalog_key' => null,
                'is_team_heading' => (bool) $head->is_team_heading,
                'sort_order' => ++$sortBase,
                'partition_key' => $head->slug ?: Str::slug($head->name),
            ], $head);

            $this->ensureItemsOnSportEvent($season, $sport, null, $head->id);
            $created++;
        }

        return $created;
    }

    /**
     * Season hub is a hidden rollup (medal tally, remittance) once sport events
     * exist — schools and navs must only ever see the per-sport events.
     */
    public function hideSeasonHubIfChildrenExist(FestEvent $season): void
    {
        if (! $season->nav_hidden
            && FestEvent::where('parent_event_id', $season->id)->exists()) {
            $season->update(['nav_hidden' => true]);
        }
    }

    /** @param  array{title: string, sport_discipline: ?string, catalog_key: ?string, is_team_heading: bool, sort_order: int, partition_key: string}  $attributes */
    private function createSportEvent(FestEvent $season, array $attributes, ?FestItemHead $legacyHead): FestEvent
    {
        return DB::transaction(function () use ($season, $attributes, $legacyHead) {
            $sport = FestEvent::create(array_merge($attributes, [
                'tenant_id' => $season->tenant_id,
                'academic_year_id' => $season->academic_year_id,
                'event_type' => 'sports',
                'source_head_id' => $legacyHead?->id,
                'level_round' => $season->level_round ?: 'sahodaya',
                'parent_event_id' => $season->id,
                'partition_role' => 'sports_discipline',
                'venue' => $season->venue,
                'event_start' => $season->event_start,
                'event_end' => $season->event_end,
                'registration_open' => $season->registration_open,
                'registration_close' => $season->registration_close,
                'event_reg_start' => $season->event_reg_start,
                'event_reg_end' => $season->event_reg_end,
                'sports_age_cutoff_date' => $season->sports_age_cutoff_date,
                'fee_settings' => array_merge(
                    is_array($season->fee_settings) ? $season->fee_settings : [],
                    ['fee_model' => 'sports_composite'],
                ),
                'numbering_settings' => $season->numbering_settings,
                'status' => $season->status ?: 'draft',
                'description' => $season->description,
                'verification_policy' => 'all_students',
                'approval_policy' => 'auto',
            ]));

            if ($legacyHead) {
                $this->copyHeadFeesIfEmpty($legacyHead, $sport);
                $this->copyHeadWindowsIfMissing($legacyHead, $sport);
                $legacyHead->update([
                    'discipline_event_id' => $sport->id,
                    'event_id' => $sport->id,
                ]);
            }

            return $sport;
        });
    }

    /** @param  array{key: string, name: string, sport_discipline?: ?string, is_team_heading?: bool}  $def */
    private function applyCatalogFields(FestEvent $sport, FestEvent $season, array $def, int $sortOrder, string $slug): void
    {
        $sport->update([
            'catalog_key' => $def['key'],
            'sport_discipline' => $def['sport_discipline'] ?? $sport->sport_discipline,
            'is_team_heading' => (bool) ($def['is_team_heading'] ?? true),
            'sort_order' => $sortOrder,
            'partition_role' => 'sports_discipline',
            'partition_key' => $slug,
            'sports_age_cutoff_date' => $season->sports_age_cutoff_date,
            'parent_event_id' => $season->id,
        ]);
    }

    private function copyHeadFeesIfEmpty(FestItemHead $head, FestEvent $event): void
    {
        if ($event->hasSportsFeesConfigured()) {
            return;
        }

        $event->fill([
            'default_item_fee' => $head->default_item_fee,
            'extra_item_fee' => $head->extra_item_fee,
            'school_registration_fee' => $head->school_registration_fee,
            'student_registration_fee' => $head->student_registration_fee,
            'team_registration_fee' => $head->team_registration_fee,
            'included_items_per_student' => $head->included_items_per_student ?? 0,
            'included_teams' => $head->included_teams ?? 0,
            'verification_policy' => $head->verification_policy ?? 'all_students',
            'approval_policy' => $head->approval_policy ?? 'auto',
            'max_participants' => $head->max_participants,
            'max_teams' => $head->max_teams,
            'schedule_mode' => $head->schedule_mode,
            'competition_time' => $head->competition_time,
            'notification_settings' => $head->notification_settings,
            'source_head_id' => $head->id,
        ])->save();
    }

    /**
     * Registration/competition windows from the head are copied whenever the event
     * has none — independent of fees, since FestItemRegistrationGate now hard-checks
     * the event window for sports (a missing window blocks every item).
     */
    private function copyHeadWindowsIfMissing(FestItemHead $head, FestEvent $event): void
    {
        $dirty = false;
        foreach ([
            'reg_start' => $head->reg_start,
            'reg_end' => $head->reg_end,
            'competition_start' => $head->competition_start,
            'competition_end' => $head->competition_end,
        ] as $column => $value) {
            if ($event->{$column} === null && $value !== null) {
                $event->{$column} = $value;
                $dirty = true;
            }
        }

        if ($event->registration_open === null && $head->reg_start !== null) {
            $event->registration_open = $head->reg_start;
            $dirty = true;
        }
        if ($event->registration_close === null && $head->reg_end !== null) {
            $event->registration_close = $head->reg_end;
            $dirty = true;
        }

        if ($dirty) {
            $event->save();
        }
    }

    /**
     * Move season items matching this sport onto the sport event — together with
     * their registrations, participants, marks, and schedule rows (same
     * transaction; a moved item with left-behind registrations makes the school's
     * existing registrations invisible). Seeds from the master catalog when the
     * sport event ends up with no items.
     *
     * @param  ?string  $catalogKey  match items by resolved catalog head key
     * @param  ?int  $headId  additionally match items still linked to this head
     */
    private function ensureItemsOnSportEvent(FestEvent $season, FestEvent $sport, ?string $catalogKey, ?int $headId = null): void
    {
        $movableIds = FestEventItem::where('event_id', $season->id)
            ->with('head:id,catalog_key')
            ->get()
            ->filter(function (FestEventItem $item) use ($catalogKey, $headId) {
                if ($headId !== null && (int) $item->head_id === $headId) {
                    return true;
                }
                if ($catalogKey === null) {
                    return false;
                }
                $key = $item->head?->catalog_key
                    ?: FestItemHeadService::resolveCatalogHeadKey([
                        'title' => $item->title,
                        'sport_discipline' => $item->sport_discipline,
                        'head_key' => $item->head_key ?? null,
                    ]);

                return $key === $catalogKey;
            })
            ->pluck('id')
            ->all();

        if ($movableIds !== []) {
            DB::transaction(function () use ($season, $sport, $movableIds) {
                FestEventItem::whereIn('id', $movableIds)
                    ->update(['event_id' => $sport->id, 'head_id' => null]);

                $registrationIds = FestRegistration::where('event_id', $season->id)
                    ->whereIn('item_id', $movableIds)
                    ->pluck('id');

                if ($registrationIds->isNotEmpty()) {
                    FestRegistration::whereIn('id', $registrationIds)
                        ->update(['event_id' => $sport->id]);
                    FestParticipant::whereIn('registration_id', $registrationIds)
                        ->update(['event_id' => $sport->id]);
                }

                FestMark::where('event_id', $season->id)
                    ->whereIn('item_id', $movableIds)
                    ->update(['event_id' => $sport->id]);

                FestSchedule::where('event_id', $season->id)
                    ->whereIn('item_id', $movableIds)
                    ->update(['event_id' => $sport->id]);
            });
        }

        // Clear leftover head_ids on sport event items (Head = Event).
        // NOTE: no catalog auto-seeding — sport events start empty and admins load
        // items explicitly via "Assign items to event" (items master → assign).
        FestEventItem::where('event_id', $sport->id)
            ->whereNotNull('head_id')
            ->update(['head_id' => null]);
    }

    private function sportTitle(FestEvent $season, string $name): string
    {
        $year = $season->academicYear?->label;

        return $year ? "{$name} {$year}" : $name;
    }
}
