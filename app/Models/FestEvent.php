<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use App\Services\Events\FestCompetitionTypeRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FestEvent extends Model
{
    use BelongsToCentralTenant;

    /**
     * Mirrors FestItemHead::NOTIFICATION_TRIGGERS — kept as its own copy (not a shared
     * reference) for the same reason the "Sports unified event fields" block below is a
     * copy rather than a join: this list needs to work for any event, head or no head.
     */
    public const NOTIFICATION_TRIGGERS = [
        'registration_approved', 'registration_rejected', 'registration_withdrawn',
        'registration_opened', 'registration_deadline', 'payment_pending',
        'competition_reminder', 'certificates_available', 'results_published',
        'schedule_published', 'chest_reveal', 'promotion_completed',
        'sports_winners_received', 'appeal_received',
    ];

    protected $fillable = [
        'tenant_id', 'academic_year_id', 'title', 'event_type', 'conductor_level',
        'conduct_levels', 'level_round', 'state_program_id', 'conducting_school_id',
        'is_cascaded', 'parent_event_id', 'root_event_id',         'cluster_key', 'cluster_label', 'cloned_from_event_id',
        'conduct_mode', 'combine_regions_at_finale', 'partition_role', 'partition_key', 'region_id', 'aggregation_config', 'scoring_preset',
        'registration_open', 'registration_close', 'event_start', 'event_end', 'sports_age_cutoff_date', 'venue',
        'fee_type', 'fee_amount', 'fee_settings', 'numbering_settings', 'status', 'nav_hidden', 'results_published', 'description',
        'scoring_locked', 'appeals_open', 'chest_reveal_mode', 'require_judge_scores_before_publish',
        'appeal_fee_amount', 'certificate_collection_open', 'registration_locked', 'schedule_published',
        'record_tracking_enabled', 'default_record_prize_label', 'require_all_marks_before_publish',
        'require_event_registration', 'event_reg_start', 'event_reg_end', 'allow_student_self_register',
        'verification_day', 'manual_pdf_path',
        'sport_discipline', 'source_head_id',
        // Sports unified event fields (formerly on FestItemHead)
        'catalog_key', 'is_team_heading', 'sort_order',
        'default_item_fee', 'extra_item_fee',
        'school_registration_fee', 'student_registration_fee', 'team_registration_fee',
        'included_items_per_student', 'included_teams',
        'verification_policy', 'approval_policy',
        'max_participants', 'max_teams',
        'reg_start', 'reg_end', 'competition_start', 'competition_end',
        'schedule_mode', 'competition_time',
        'notification_settings',
        'strict_item_payment_gating',
        'food_payee_type', 'food_host_school_id', 'require_payment_for_coupons',
        'phase_mode_enabled', 'workflow_mode', 'source_phase_id', 'registration_batch_id', 'workflow_leaf_key',
        // sahodaya_customized_at was stamped via updateQuietly() since 2026-08-13 but was
        // never added here — mass-assignment silently dropped it every time, so the
        // customization-indicator badge it drives never actually turned on. fee_customized_at
        // is the same mechanism for the hub -> partition-child fee boundary; added here from
        // the start so it doesn't repeat that mistake.
        'sahodaya_customized_at', 'fee_customized_at',
    ];

    protected $attributes = [
        'approval_policy' => 'auto',
    ];

    protected $casts = [
        'is_cascaded' => 'boolean',
        'nav_hidden' => 'boolean',
        'results_published' => 'boolean',
        'scoring_locked' => 'boolean',
        'appeals_open' => 'boolean',
        'require_judge_scores_before_publish' => 'boolean',
        'certificate_collection_open' => 'boolean',
        'registration_locked' => 'boolean',
        'schedule_published' => 'boolean',
        'require_all_marks_before_publish' => 'boolean',
        'require_event_registration' => 'boolean',
        'allow_student_self_register' => 'boolean',
        'record_tracking_enabled' => 'boolean',
        'is_team_heading' => 'boolean',
        'strict_item_payment_gating' => 'boolean',
        'combine_regions_at_finale' => 'boolean',
        'require_payment_for_coupons' => 'boolean',
        'phase_mode_enabled' => 'boolean',
        'conduct_levels' => 'array',
        'aggregation_config' => 'array',
        'notification_settings' => 'array',
        // date:Y-m-d — plain-date serialization. Bare 'date' casts serialize to a UTC
        // ISO timestamp (2026-07-25 IST → "2026-07-24T18:30:00Z"), so date inputs
        // display the previous day and each save silently shifts the date back one.
        'registration_open' => 'date:Y-m-d',
        'registration_close' => 'date:Y-m-d',
        'event_reg_start' => 'date:Y-m-d',
        'event_reg_end' => 'date:Y-m-d',
        'reg_start' => 'date:Y-m-d',
        'reg_end' => 'date:Y-m-d',
        'competition_start' => 'date:Y-m-d',
        'competition_end' => 'date:Y-m-d',
        'event_start' => 'date:Y-m-d',
        'event_end' => 'date:Y-m-d',
        'verification_day' => 'date:Y-m-d',
        'sports_age_cutoff_date' => 'date:Y-m-d',
        'fee_amount' => 'decimal:2',
        'default_item_fee' => 'decimal:2',
        'extra_item_fee' => 'decimal:2',
        'school_registration_fee' => 'decimal:2',
        'student_registration_fee' => 'decimal:2',
        'team_registration_fee' => 'decimal:2',
        'fee_settings' => 'array',
        'numbering_settings' => 'array',
        'appeal_fee_amount' => 'decimal:2',
        'included_items_per_student' => 'integer',
        'included_teams' => 'integer',
        'max_participants' => 'integer',
        'max_teams' => 'integer',
        'sort_order' => 'integer',
        'sahodaya_customized_at' => 'datetime',
        'fee_customized_at' => 'datetime',
    ];

    /** Whether composite sports fee columns are configured (checklist readiness). */
    public function hasSportsFeesConfigured(): bool
    {
        return $this->school_registration_fee !== null
            || $this->student_registration_fee !== null
            || $this->team_registration_fee !== null
            || $this->default_item_fee !== null
            || $this->extra_item_fee !== null;
    }

    /** Formatted payment details for schools, falling back to Sahodaya default profile. */
    public function paymentDetailsText(?SahodayaProfile $sahodayaProfile = null): string
    {
        $customInstructions = $this->fee_settings['payment_instructions'] ?? null;
        if (filled($customInstructions)) {
            return trim((string) $customInstructions);
        }

        if (! $sahodayaProfile) {
            $sahodayaProfile = SahodayaProfile::where('tenant_id', $this->tenant_id)->first();
        }

        return $sahodayaProfile ? $sahodayaProfile->paymentDetailsText() : '';
    }

    public function requiresManualApproval(): bool
    {
        return $this->approval_policy === 'manual';
    }

    public function requiresVerifiedStudentsOnly(): bool
    {
        return $this->verification_policy === 'verified_only';
    }

    public function notificationEnabledFor(string $trigger): bool
    {
        $disabled = $this->notification_settings['disabled_triggers'] ?? [];

        return ! in_array($trigger, $disabled, true);
    }

    /** @return list<int> */
    public function extraRecipientUserIds(): array
    {
        $ids = $this->notification_settings['extra_recipient_user_ids'] ?? [];

        return array_values(array_unique(array_map('intval', is_array($ids) ? $ids : [])));
    }

    public function isSameTime(): bool
    {
        return $this->schedule_mode === 'same_time';
    }

    public function competitionTimeShort(): ?string
    {
        return $this->competition_time ? substr((string) $this->competition_time, 0, 5) : null;
    }

    public function sourceHead(): BelongsTo
    {
        return $this->belongsTo(FestItemHead::class, 'source_head_id');
    }

    public function getDisplayTitleAttribute(): string
    {
        $parent = $this->parentEvent ?? $this->parent;
        if ($this->parent_event_id && $parent && ! str_contains($this->title, $parent->title)) {
            return "{$parent->title} — {$this->title}";
        }

        return $this->title;
    }

    protected static function booted(): void
    {
        static::saving(function (self $event) {
            if ($event->fee_type === null) {
                $event->fee_type = 'none';
            }
            if ($event->parent_event_id && $event->parentEvent) {
                if (! str_contains($event->title, $event->parentEvent->title)) {
                    $event->title = "{$event->parentEvent->title} — {$event->title}";
                }
            }

            // STATE_SAHODAYA_RULE_BOUNDARY_FIX_PLAN — Set 2, Item 5
            // Once a FestEvent row has state_program_id set, both state_program_id itself
            // and event_type are immutable. Guard is only active on *updates* of persisted
            // rows (exists = true). New records created via FestCascadeService::create()
            // or FestStateProgramService::createTenantEvent() pass through unaffected.
            if ($event->exists) {
                if ($event->isDirty('state_program_id')
                    && $event->getOriginal('state_program_id') !== null) {
                    throw new \DomainException(
                        "FestEvent #{$event->id}: state_program_id is immutable once set."
                    );
                }
                if ($event->isDirty('event_type')
                    && $event->getOriginal('state_program_id') !== null) {
                    throw new \DomainException(
                        "FestEvent #{$event->id}: event_type cannot be changed on a State-linked event."
                    );
                }
            }
        });
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYearRecord::class, 'academic_year_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FestEventItem::class, 'event_id')->orderBy('display_order');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(FestRegistration::class, 'event_id');
    }

    public function foodMenuItems(): HasMany
    {
        return $this->hasMany(FestFoodMenuItem::class, 'event_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(FestResult::class, 'event_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'parent_event_id');
    }

    public function parentEvent(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'parent_event_id');
    }

    public function childEvents(): HasMany
    {
        return $this->hasMany(FestEvent::class, 'parent_event_id');
    }

    public function houses(): HasMany
    {
        return $this->hasMany(FestHouse::class, 'event_id')->orderBy('sort_order');
    }

    public function conductingSchool(): BelongsTo
    {
        return $this->belongsToCentralTenant('conducting_school_id');
    }

    /** The school food payments are payable to when food_payee_type is 'host_school'. */
    public function foodHostSchool(): BelongsTo
    {
        return $this->belongsToCentralTenant('food_host_school_id');
    }

    /**
     * The membership Region this event is a partition of, when it's a region-sourced
     * partition child (partition_role === 'region'). Null for hubs, non-region
     * partitions (finale/cluster/digi_fest), and standard events.
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    /**
     * Breadcrumb-style facts about this event's place in a hub/region/phase hierarchy —
     * built for pages (food menu/billing/coupons/catering first) that previously gave no
     * indication whether the admin or school was looking at a hub, a specific region, or a
     * named competition phase. `is_hub` is only meaningful together with `has_children`:
     * a hub with zero children is just a standard event that happens to have no parent.
     */
    public function hierarchyContext(): array
    {
        $parent = $this->parent_event_id ? $this->parentEvent()->first(['id', 'title']) : null;
        $region = $this->region_id ? $this->region()->first(['id', 'name']) : null;
        $phase = $this->source_phase_id ? $this->sourcePhase()->first(['id', 'name']) : null;

        return [
            'is_hub' => $this->parent_event_id === null,
            'has_children' => $this->parent_event_id === null && $this->childEvents()->exists(),
            'parent_event' => $parent ? ['id' => $parent->id, 'title' => $parent->title] : null,
            'region' => $region ? ['id' => $region->id, 'name' => $region->name] : null,
            'phase' => $phase ? ['id' => $phase->id, 'name' => $phase->name] : null,
            'cluster_label' => $this->cluster_label,
            'partition_role' => $this->partition_role,
        ];
    }

    public function scopeForTenant($q, string $tenantId)
    {
        return $q->where('tenant_id', $tenantId);
    }

    public function scopeOfType($q, string $type)
    {
        if (in_array($type, ['kalotsav', 'kalotsavam', 'kalolsavam'], true)) {
            return $q->whereIn('event_type', ['kalotsav', 'kalotsavam', 'kalolsavam']);
        }

        return $q->where('event_type', $type);
    }

    public function scopeVisibleInNav($q)
    {
        return $q->where('nav_hidden', false);
    }

    /**
     * The single top-level Sahodaya hub event for a program type & year.
     * Excludes school rounds, partition/region child events, and cluster spawns.
     */
    public function scopePrimaryHub($q)
    {
        return $q->whereNull('parent_event_id')
            ->whereNull('conducting_school_id')
            ->where(function ($role) {
                $role->whereNull('partition_role')
                    ->orWhere('partition_role', 'sports_season');
            })
            ->where(function ($inner) {
                $inner->whereIn('level_round', ['sahodaya', 'state'])
                    ->orWhereNull('level_round');
            });
    }

    /**
     * A registrable sport event (Athletics, Chess, …): either a promoted child of a
     * season hub or a standalone sports event created directly in the new flow.
     * Excludes only the season hub container.
     */
    public function isSportsDisciplineEvent(): bool
    {
        return $this->event_type === 'sports' && ! $this->isSportsSeasonEvent();
    }

    /**
     * The legacy season hub container. Kept as a hidden rollup (medal tally, season
     * remittance) — never registrable and never shown to schools once children exist.
     */
    public function isSportsSeasonEvent(): bool
    {
        if ($this->event_type !== 'sports' || $this->parent_event_id !== null) {
            return false;
        }

        if ($this->partition_role === 'sports_season') {
            return true;
        }

        if ($this->partition_role !== null) {
            return false;
        }

        if ($this->conduct_mode === 'partitioned') {
            return false;
        }

        // Untagged top-level sports event: legacy hub if it has discipline children (not regional partitions)
        if ($this->relationLoaded('childEvents')) {
            return $this->childEvents->contains(fn ($c) => ! in_array($c->partition_role, ['region', 'finale'], true));
        }

        return self::where('parent_event_id', $this->id)
            ->where(function ($q) {
                $q->whereNull('partition_role')
                  ->orWhereNotIn('partition_role', ['region', 'finale']);
            })->exists();
    }

    /**
     * Event ids to query against for reports. For a sports season hub, real
     * FestEventItem/FestRegistration/FestParticipant/FestMark/FestSchedule rows all
     * attach to the auto-promoted child sport events, never the hub itself — so any
     * report builder that filters by `event_id = $event->id` directly returns nothing
     * for a season hub. This centralizes the fix: callers should filter with
     * `whereIn('event_id', $event->reportableEventIds())` instead of a plain `where`.
     * For every non-season-hub event this is just `[$this->id]` — a no-op.
     * See docs/SCHOOL_SPORTS_ITEM_HEAD_REPORTS_PLAN.md.
     *
     * @return list<int>
     */
    public function reportableEventIds(): array
    {
        $ids = [(int) $this->id];

        $childIds = self::where('parent_event_id', $this->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (! empty($childIds)) {
            $ids = array_merge($ids, $childIds);
            $grandChildIds = self::whereIn('parent_event_id', $childIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (! empty($grandChildIds)) {
                $ids = array_merge($ids, $grandChildIds);
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Clean list of dropdown options for the event-switcher used across admin listing
     * and report pages (Results, Fees, ChestNumbers, Attendance, Registrations,
     * MarkEntry, and every FestReportController interactive page) — "Hub / All Regions"
     * plus each operational child, so an admin can jump straight to one region's data
     * without leaving the page.
     *
     * Originally sports-only (season -> sport -> region). Generalized 2026-08-14: any
     * partitioned Sahodaya-level event — Kalotsav, Kids Fest, Teacher Fest, English
     * Fest, Science Fest, Custom, anything synced via
     * FestRegionPartitionService::syncPartitionsFromRegions() — now gets the same
     * dropdown for its region children instead of always returning an empty list.
     * Non-partitioned events (no region children) still return [] so the dropdown stays
     * hidden, matching existing behavior.
     *
     * @return list<array{id: int, title: string, short_title: string, parent_event_id: ?int, is_hub: bool}>
     */
    public function sportEventDropdownOptions(): array
    {
        return $this->event_type === 'sports'
            ? $this->sportsSeasonDropdownOptions()
            : $this->regionDropdownOptions();
    }

    /** Sports' nested season -> sport -> region topology. Unchanged from the original implementation. */
    private function sportsSeasonDropdownOptions(): array
    {
        $rawParentId = $this->getRawOriginal('parent_event_id')
            ?: ($this->id ? self::where('id', $this->id)->value('parent_event_id') : null);
        $seasonId = (int) ($rawParentId ?: ($this->parent_event_id ?: $this->id));
        $parentEvent = self::find($seasonId) ?: $this;
        $parentTitle = $parentEvent?->title ?? '';

        $query = self::where(function ($q) use ($seasonId) {
            $q->where('parent_event_id', $seasonId)
              ->orWhere('id', $seasonId);
        })
            ->ofType('sports')
            ->orderBy('sort_order')
            ->orderBy('title');

        $events = $query->get(['id', 'title', 'parent_event_id', 'partition_role']);

        // Check if region partitions exist
        $hasRegions = $events->contains(fn ($e) => $e->partition_role === 'region');

        $options = [];
        foreach ($events as $ev) {
            // If region partitions exist, skip intermediate non-region child events
            if ($hasRegions && $ev->parent_event_id !== null && $ev->partition_role !== 'region') {
                continue;
            }

            $isHub = $ev->parent_event_id === null;
            $rawTitle = $ev->title;

            // Strip redundant parent title prefix
            $shortTitle = $rawTitle;
            if (! empty($parentTitle) && str_contains($rawTitle, $parentTitle)) {
                $shortTitle = trim(str_replace([$parentTitle.' — ', $parentTitle.' - ', $parentTitle], '', $rawTitle));
            }

            if ($isHub) {
                $displayTitle = "{$parentTitle} (Season Hub — All Regions)";
                $shortTitle = "All Regions (Season Hub)";
            } else {
                $displayTitle = $shortTitle;
            }

            $options[] = [
                'id'              => $ev->id,
                'title'           => $displayTitle,
                'short_title'     => $shortTitle,
                'parent_event_id' => $ev->parent_event_id,
                'is_hub'          => $isHub,
            ];
        }

        return $options;
    }

    /**
     * Non-sports region topology (single level: hub -> region children). Uses the same
     * rootEvent()/childrenForRoles() topology helpers as FestReportScopeResolver and
     * FestReportController::reportProps() rather than a hand-rolled query, so this stays
     * consistent with the rest of the region-scoped reporting work — see
     * docs/REGION_PHASE_EVENT_REPORTING_REMEDIATION_PLAN.md.
     */
    private function regionDropdownOptions(): array
    {
        $root = $this->rootEvent();
        $regionChildren = $root->childrenForRoles(['region'])
            ->load('region:id,name,code')
            ->sortBy('sort_order')
            ->values();

        if ($regionChildren->isEmpty()) {
            return [];
        }

        $options = [[
            'id'              => $root->id,
            'title'           => "{$root->title} (All Regions)",
            'short_title'     => 'All Regions',
            'parent_event_id' => null,
            'is_hub'          => true,
        ]];

        foreach ($regionChildren as $child) {
            $label = $child->region?->name ?? $child->title;

            $options[] = [
                'id'              => $child->id,
                'title'           => $label,
                'short_title'     => $label,
                'parent_event_id' => $child->parent_event_id,
                'is_hub'          => false,
            ];
        }

        return $options;
    }

    /**
     * Resolve source item ids to every equivalent copied item inside this event's
     * report topology. Partition children copy hub items, so filtering only by the
     * hub item id would otherwise exclude every regional registration.
     *
     * @param  list<int>|null  $itemIds
     * @return list<int>
     */
    public function reportableItemIds(?array $itemIds = null): array
    {
        $query = FestEventItem::query()->whereIn('event_id', $this->reportableEventIds());

        if ($itemIds === null) {
            return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $selected = (clone $query)->whereIn('id', $itemIds)->get(['id', 'item_code', 'inherited_from_item_id']);
        if ($selected->isEmpty()) {
            return [];
        }

        $rootIds = $selected
            ->map(fn (FestEventItem $item) => (int) ($item->inherited_from_item_id ?: $item->id))
            ->unique()
            ->values();
        $codes = $selected->pluck('item_code')->filter()->unique()->values();

        return $query
            ->where(function ($items) use ($rootIds, $codes) {
                $items->whereIn('id', $rootIds)
                    ->orWhereIn('inherited_from_item_id', $rootIds);

                if ($codes->isNotEmpty()) {
                    $items->orWhereIn('item_code', $codes);
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The season/program root of this event's topology. For every non-partitioned,
     * non-child event this is just $this. Added for FestReportScopeResolver
     * (remediation plan §4.2) — reportableEventIds() intentionally stays untouched for
     * existing non-report callers.
     *
     * Prefers the indexed root_event_id column (Phase 7, §7.1) when it's set, falling
     * back to walking parent_event_id — root_event_id may be null for rows created
     * before that backfill ran, or in test fixtures that don't set it explicitly.
     */
    public function rootEvent(): self
    {
        if ($this->root_event_id && (int) $this->root_event_id !== (int) $this->id) {
            $root = self::find($this->root_event_id);
            if ($root) {
                return $root;
            }
        }

        $event = $this;
        $seen = [];

        while ($event->parent_event_id && ! in_array($event->parent_event_id, $seen, true)) {
            $seen[] = $event->parent_event_id;
            $parent = self::find($event->parent_event_id);
            if (! $parent) {
                break;
            }
            $event = $parent;
        }

        return $event;
    }

    /** @return \Illuminate\Support\Collection<int, self> Root-first ancestry, excluding $this. */
    public function ancestors(): \Illuminate\Support\Collection
    {
        $chain = [];
        $event = $this;
        $seen = [];

        while ($event->parent_event_id && ! in_array($event->parent_event_id, $seen, true)) {
            $seen[] = $event->parent_event_id;
            $parent = self::find($event->parent_event_id);
            if (! $parent) {
                break;
            }
            $chain[] = $parent;
            $event = $parent;
        }

        return collect(array_reverse($chain));
    }

    /**
     * Immediate children matching one or more partition_role values (e.g. ['region'],
     * ['finale'], ['sports_discipline']) — the role-aware replacement for blindly taking
     * every immediate child, which is exactly gap G2 in reportableEventIds().
     *
     * @param  list<string>  $roles
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public function childrenForRoles(array $roles): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('parent_event_id', $this->id)
            ->where(function ($query) use ($roles) {
                $query->whereIn('partition_role', $roles)
                    ->orWhereNotNull('region_id');
                if (in_array('region', $roles, true)) {
                    $query->orWhereNull('partition_role');
                }
            })
            ->get();
    }

    /** The single region-partition child matching a given region, if any. */
    public function regionalChild(int $regionId): ?self
    {
        return self::where('parent_event_id', $this->id)
            ->where(function ($query) use ($regionId) {
                $query->where('region_id', $regionId)
                    ->orWhere('id', $regionId);
            })
            ->first();
    }

    /**
     * Operational leaves for a report family: this event's own id when it has no
     * matching children of the given roles (a standard event, or a role-less leaf),
     * otherwise the matching children themselves (never the hub, which only holds
     * shared configuration — plan §3.2).
     *
     * @param  list<string>  $roles
     * @return list<int>
     */
    public function operationalLeaves(array $roles = ['region']): array
    {
        $children = $this->childrenForRoles($roles);

        if ($children->isEmpty()) {
            return [(int) $this->id];
        }

        return $children->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    /** Fest program types that are unique (one per Sahodaya per academic year). */
    public static function singletonEventTypes(?string $tenantId = null): array
    {
        if ($tenantId) {
            try {
                return app(FestCompetitionTypeRegistry::class)
                    ->forTenant($tenantId)
                    ->singletonKeys();
            } catch (\Throwable) {
                // Fall through to config defaults when the master table is unavailable.
            }
        }

        return collect(config('fest_competition_types', []))
            ->filter(fn ($meta) => (bool) ($meta['is_singleton'] ?? false))
            ->keys()
            ->values()
            ->all();
    }

    public static function isSingletonType(?string $eventType, ?string $tenantId = null): bool
    {
        return $eventType !== null && in_array($eventType, self::singletonEventTypes($tenantId), true);
    }

    public function scopeVisibleToSchool($q, string $schoolId)
    {
        return $q->where('nav_hidden', false)->where(function ($inner) use ($schoolId) {
            $inner->where(function ($cluster) {
                $cluster->where('level_round', 'sahodaya')
                    ->orWhereNull('level_round');
            })->orWhere(function ($school) use ($schoolId) {
                $school->where('level_round', 'school')
                    ->where('conducting_school_id', $schoolId);
            });
        });
    }

    /**
     * Events schools may list (hub, nav switcher, registration, API).
     * Sports: only once registration opens (draft/published stay Sahodaya-only).
     * Other fest types: published preview remains allowed.
     *
     * @param  Builder<FestEvent>  $q
     * @return Builder<FestEvent>
     */
    public function scopeListedForSchool($q, string $schoolId, ?string $eventType = null)
    {
        $q->visibleToSchool($schoolId);

        if ($eventType !== null) {
            return $q->whereIn('status', self::schoolListStatusesForType($eventType));
        }

        return $q->whereIn('status', self::schoolListStatusesForType(null));
    }

    /** @return list<string> */
    public static function schoolListStatusesForType(?string $eventType): array
    {
        return ['published', 'registration_open', 'ongoing', 'completed'];
    }

    public function conductsAt(string $level): bool
    {
        return in_array($level, $this->conduct_levels ?? ['sahodaya'], true);
    }

    public function isStateProgram(): bool
    {
        return $this->state_program_id !== null;
    }

    /**
     * True when a Sahodaya Admin has explicitly edited at least one state-seeded field
     * (title, dates, venue, fee, description) after the event was first created from the
     * State program. Used by the customization indicator badge (Set 1, Item 3 of the
     * STATE_SAHODAYA_RULE_BOUNDARY_FIX_PLAN_2026_08_13).
     */
    public function isCustomizedBySahodaya(): bool
    {
        return $this->sahodaya_customized_at !== null;
    }

    /**
     * True when this partition child's own fee data (fee_settings, an item's fee_amount,
     * or a head's fee columns) has been edited directly on the child itself, rather than
     * inherited untouched from the hub. FestSchoolEventFeeService::propagateFeeSettingsToChildren()
     * skips a child entirely once this is set, so a hub-level fee save no longer reverts it.
     */
    public function hasCustomizedFees(): bool
    {
        return $this->fee_customized_at !== null;
    }

    public function isEditableBySahodaya(): bool
    {
        return ! $this->isStateProgram() || $this->level_round !== 'state';
    }

    /** @return array<string, string> */
    public static function levelLabels(): array
    {
        return FestStateProgram::levelLabels();
    }

    public function isRegistrationOpen(): bool
    {
        if (! in_array($this->status, ['registration_open', 'published'], true)) {
            return false;
        }

        $today = now();

        if ($this->registration_open && $today->lt($this->registration_open->startOfDay())) {
            return false;
        }

        if ($this->registration_close && $today->gt($this->registration_close->endOfDay())) {
            return false;
        }

        return true;
    }

    public function phases(): HasMany
    {
        return $this->hasMany(FestEventPhase::class, 'event_id')->orderBy('sort_order');
    }

    public function registrationBatches(): HasMany
    {
        return $this->hasMany(FestRegistrationBatch::class, 'event_id')->orderBy('sort_order');
    }

    public function sourcePhase(): BelongsTo
    {
        return $this->belongsTo(FestEventPhase::class, 'source_phase_id');
    }

    public function registrationBatch(): BelongsTo
    {
        return $this->belongsTo(FestRegistrationBatch::class, 'registration_batch_id');
    }

    public function schoolPhaseRegionSelections(): HasMany
    {
        return $this->hasMany(FestSchoolPhaseRegionSelection::class, 'event_id');
    }

    public function usesPhasedRegionalBilling(): bool
    {
        return $this->rootEvent()->workflow_mode === 'phased_regional_billing';
    }
}
