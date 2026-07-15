<?php

namespace App\Services\Events;

use App\Models\FestCatalogItem;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestItemHead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ensures each Sports season hub has one child FestEvent per catalog sport
 * (Athletics, Chess, …). Replaces the former Head → promote → discipline flow.
 *
 * Catalog templates may still live as FestItemHead rows with event_id=null;
 * runtime sport events are FestEvent children — no FestItemHead rows required.
 */
class FestSportsEventSyncService
{
    public function __construct(
        private FestItemHeadService $headService,
    ) {}

    /**
     * Sync catalog sports onto a season hub as child FestEvents.
     * No-op for non-sports or non-season (child) events.
     *
     * @return array{created: int, updated: int}
     */
    public function syncSeason(FestEvent $season): array
    {
        if ($season->event_type !== 'sports' || $season->parent_event_id !== null) {
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
                    'registration_open' => $season->registration_open,
                    'registration_close' => $season->registration_close,
                    'event_reg_start' => $season->event_reg_start,
                    'event_reg_end' => $season->event_reg_end,
                ];
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
                    $this->ensureItemsOnSportEvent($season, $discipline, $key);
                    $updated++;

                    continue;
                }
            }

            $sport = DB::transaction(function () use ($season, $def, $key, $slug, $title, $index, $legacyHead) {
                $sport = FestEvent::create([
                    'tenant_id' => $season->tenant_id,
                    'academic_year_id' => $season->academic_year_id,
                    'title' => $title,
                    'event_type' => 'sports',
                    'sport_discipline' => $def['sport_discipline'] ?? null,
                    'catalog_key' => $key,
                    'source_head_id' => $legacyHead?->id,
                    'is_team_heading' => (bool) ($def['is_team_heading'] ?? true),
                    'sort_order' => $index + 1,
                    'level_round' => $season->level_round ?: 'sahodaya',
                    'parent_event_id' => $season->id,
                    'partition_role' => 'sports_discipline',
                    'partition_key' => $slug,
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
                ]);

                if ($legacyHead) {
                    $this->copyHeadFeesIfEmpty($legacyHead, $sport);
                    $legacyHead->update([
                        'discipline_event_id' => $sport->id,
                        'event_id' => $sport->id,
                    ]);
                }

                return $sport;
            });

            $this->ensureItemsOnSportEvent($season, $sport, $key);
            $created++;
        }

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * Alias used by season page loads — replaces FestItemHeadService::syncEventHeads + promote.
     */
    public function syncEventHeads(FestEvent $event): int
    {
        $result = $this->syncSeason($event);

        return $result['created'];
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
            'reg_start' => $head->reg_start,
            'reg_end' => $head->reg_end,
            'competition_start' => $head->competition_start,
            'competition_end' => $head->competition_end,
            'schedule_mode' => $head->schedule_mode,
            'competition_time' => $head->competition_time,
            'notification_settings' => $head->notification_settings,
            'source_head_id' => $head->id,
        ])->save();
    }

    /**
     * Move season items matching this catalog key onto the sport event,
     * and seed from master catalog when the sport event has no items yet.
     */
    private function ensureItemsOnSportEvent(FestEvent $season, FestEvent $sport, string $catalogKey): void
    {
        // Move matching items still on the season hub onto the sport event.
        FestEventItem::where('event_id', $season->id)
            ->with('head:id,catalog_key')
            ->get()
            ->each(function (FestEventItem $item) use ($sport, $catalogKey) {
                $key = $item->head?->catalog_key
                    ?: FestItemHeadService::resolveCatalogHeadKey([
                        'title' => $item->title,
                        'sport_discipline' => $item->sport_discipline,
                        'head_key' => $item->head_key ?? null,
                    ]);
                if ($key === $catalogKey) {
                    $item->update(['event_id' => $sport->id, 'head_id' => null]);
                }
            });

        if (FestEventItem::where('event_id', $sport->id)->exists()) {
            // Clear leftover head_ids on sport event items.
            FestEventItem::where('event_id', $sport->id)
                ->whereNotNull('head_id')
                ->update(['head_id' => null]);

            return;
        }

        // Seed from tenant master catalog.
        $catalogItems = FestCatalogItem::forProgram($season->tenant_id, 'sports')
            ->where('head_key', $catalogKey)
            ->where('is_enabled', true)
            ->orderBy('display_order')
            ->get();

        $order = 0;
        foreach ($catalogItems as $catalog) {
            $order++;
            FestEventItem::create(array_merge($catalog->toEventAttributes(), [
                'event_id' => $sport->id,
                'head_id' => null,
                'display_order' => $order,
                'is_enabled' => true,
                'sport_discipline' => $catalog->sport_discipline ?? $sport->sport_discipline,
            ]));
        }
    }

    private function sportTitle(FestEvent $season, string $name): string
    {
        $year = $season->academicYear?->label;

        return $year ? "{$name} {$year}" : $name;
    }
}
