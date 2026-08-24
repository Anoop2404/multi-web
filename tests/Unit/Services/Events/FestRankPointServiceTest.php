<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\Tenant;
use App\Services\Events\FestRankPointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The Rank Points tab tells admins team/relay ranks fall back to the Individual
 * template when left unassigned. pointsForRank() must honor that for a participant
 * type with no governing template (or a template with no row for that rank) — this
 * was rewritten against the FestRankPointTemplate/FestRankPoint schema (superseding
 * the old flat is_group boolean) after the original version of this test went stale
 * and started failing (NOT NULL constraint on template_id, unknown $isGroup param).
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

    private function makeTemplate(FestEvent $event, string $name, array $participantTypes, array $rows): void
    {
        $service = app(FestRankPointService::class);
        $template = $service->createTemplate($event, $name, $participantTypes);
        $service->replaceRows($template, $rows);
    }

    public function test_unconfigured_team_rank_falls_back_to_configured_individual_points(): void
    {
        $event = $this->makeEvent();
        $this->makeTemplate($event, 'Individual', ['individual'], [['rank' => 1, 'points' => 12]]);
        // No template governs 'team' at all.

        $points = app(FestRankPointService::class)->pointsForRank($event, 1, 'team');

        $this->assertSame(12, $points, 'An unconfigured team rank must fall back to the individual table\'s value, not 0.');
    }

    public function test_unconfigured_team_rank_falls_back_to_athletics_standard_for_sports_events(): void
    {
        $event = $this->makeEvent('sports');
        // No templates configured at all — should reach the built-in athletics-standard
        // default (2nd = 7), same as an unconfigured individual rank would.

        $points = app(FestRankPointService::class)->pointsForRank($event, 2, 'team');

        $this->assertSame(FestRankPointService::ATHLETICS_STANDARD[2], $points);
    }

    public function test_explicit_team_template_row_still_wins_over_the_individual_fallback(): void
    {
        $event = $this->makeEvent();
        $this->makeTemplate($event, 'Individual', ['individual'], [['rank' => 1, 'points' => 12]]);
        $this->makeTemplate($event, 'Team', ['team'], [['rank' => 1, 'points' => 20]]);

        $points = app(FestRankPointService::class)->pointsForRank($event, 1, 'team');

        $this->assertSame(20, $points, 'A configured team rank must not be overridden by the individual fallback.');
    }

    public function test_unconfigured_team_rank_on_a_non_sports_event_still_resolves_to_zero(): void
    {
        $event = $this->makeEvent('kalolsavam');
        // Nothing configured at all, and non-sports events have no athletics-standard fallback.

        $points = app(FestRankPointService::class)->pointsForRank($event, 1, 'team');

        $this->assertSame(0, $points);
    }
}
