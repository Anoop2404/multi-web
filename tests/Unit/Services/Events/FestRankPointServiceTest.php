<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\FestRankPoint;
use App\Models\Tenant;
use App\Services\Events\FestRankPointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The Points settings tab (PointsTab.vue) tells admins "leave empty to fallback to
 * individual ranks" for the team/relay rank-points table. pointsForRank() didn't honor
 * that for group ranks with no configured row — it returned 0 instead of resolving the
 * individual table's value, so any sports event where an admin only filled in the
 * Individual table (exactly what the UI invites) awarded zero championship points to
 * every team/relay placement.
 */
class FestRankPointServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(string $eventType = 'sports'): FestEvent
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Rank Point Test Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);

        return FestEvent::create(['tenant_id' => $tenant->id, 'title' => 'Rank Point Test Meet', 'event_type' => $eventType]);
    }

    public function test_unconfigured_group_rank_falls_back_to_configured_individual_points(): void
    {
        $event = $this->makeEvent();
        FestRankPoint::create(['event_id' => $event->id, 'rank' => 1, 'points' => 12, 'is_group' => false]);
        // No is_group=true row for rank 1 at all.

        $points = app(FestRankPointService::class)->pointsForRank($event, 1, isGroup: true);

        $this->assertSame(12, $points, 'An unconfigured team rank must fall back to the individual table\'s value, not 0.');
    }

    public function test_unconfigured_group_rank_falls_back_to_athletics_standard_for_sports_events(): void
    {
        $event = $this->makeEvent('sports');
        // Neither individual nor group configured for rank 2 — should reach the built-in
        // athletics-standard default (2nd = 7), same as an unconfigured individual rank would.

        $points = app(FestRankPointService::class)->pointsForRank($event, 2, isGroup: true);

        $this->assertSame(FestRankPointService::ATHLETICS_STANDARD[2], $points);
    }

    public function test_explicit_group_row_still_wins_over_the_individual_fallback(): void
    {
        $event = $this->makeEvent();
        FestRankPoint::create(['event_id' => $event->id, 'rank' => 1, 'points' => 12, 'is_group' => false]);
        FestRankPoint::create(['event_id' => $event->id, 'rank' => 1, 'points' => 20, 'is_group' => true]);

        $points = app(FestRankPointService::class)->pointsForRank($event, 1, isGroup: true);

        $this->assertSame(20, $points, 'A configured team rank must not be overridden by the individual fallback.');
    }

    public function test_unconfigured_group_rank_on_a_non_sports_event_still_resolves_to_zero(): void
    {
        $event = $this->makeEvent('kalolsavam');
        // Nothing configured at all, and non-sports events have no athletics-standard fallback.

        $points = app(FestRankPointService::class)->pointsForRank($event, 1, isGroup: true);

        $this->assertSame(0, $points);
    }
}
