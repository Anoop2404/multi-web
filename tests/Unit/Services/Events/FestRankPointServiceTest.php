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

    public function test_unconfigured_sports_ranks_receive_zero_and_have_no_autofill_points(): void
    {
        $event = $this->makeEvent('sports');
        // No configured rule means no awarded or auto-filled points.

        $points = app(FestRankPointService::class)->pointsForRank($event, 2, 'team');

        $this->assertSame(0, $points);
        $service = app(FestRankPointService::class);
        $this->assertSame(0, $service->pointsForRank($event, 1, 'individual'));
        $this->assertSame([], $service->rowsForType($event, 'individual'));
        $this->assertSame([], $service->rowsForType($event, 'team'));
    }

    public function test_missing_rank_does_not_inherit_hardcoded_points(): void
    {
        $event = $this->makeEvent();
        $this->makeTemplate($event, 'Individual', ['individual'], [['rank' => 1, 'points' => 12]]);
        $service = app(FestRankPointService::class);
        $this->assertSame(12, $service->pointsForRank($event, 1, 'individual'));
        $this->assertSame(0, $service->pointsForRank($event, 2, 'individual'));
        $this->assertSame(0, $service->pointsForRank($event, 2, 'team'));
        $this->assertSame([['rank' => 1, 'points' => 12]], $service->rowsForType($event, 'individual'));
    }

    public function test_sports_scoring_uses_configured_ranks_and_never_grade_defaults(): void
    {
        $event = $this->makeEvent();
        $this->makeTemplate($event, 'Individual', ['individual'], [['rank' => 1, 'points' => 12]]);
        $item = new \App\Models\FestEventItem(['participant_type' => 'individual']);
        $mark = new \App\Models\FestMark(['position' => 1, 'grade' => 'A', 'score' => 95]);
        $mark->setRelation('item', $item);
        $service = app(\App\Services\Events\FestGradePointService::class);
        $this->assertSame(12, $service->pointsForMark($event, $mark));
        $mark->position = 2;
        $this->assertSame(0, $service->pointsForMark($event, $mark));
        $mark->position = null;
        $this->assertSame(0, $service->pointsForMark($event, $mark));
    }

    public function test_saving_sports_rank_keeps_optional_score_empty(): void
    {
        $event = $this->makeEvent();
        $this->makeTemplate($event, 'Individual', ['individual'], [['rank' => 1, 'points' => 8]]);
        $item = \App\Models\FestEventItem::create(['event_id' => $event->id, 'title' => 'Race', 'participant_type' => 'individual']);
        $registration = \App\Models\FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $event->tenant_id, 'status' => 'approved',
        ]);
        $participant = \App\Models\FestParticipant::create([
            'event_id' => $event->id, 'registration_id' => $registration->id,
            'participant_type' => 'student', 'participant_role' => 'performer',
        ]);
        $data = ['item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 1, 'score' => null];
        $service = app(\App\Services\Events\FestMarkSaveService::class);
        $service->save($event, $data, 1, false);
        $mark = \App\Models\FestMark::where('participant_id', $participant->id)->firstOrFail();
        $this->assertNull($mark->score);
        $this->assertSame(1, (int) $mark->position);
        $this->assertSame(8, app(\App\Services\Events\FestGradePointService::class)->pointsForMark($event, $mark));
        $data['score'] = 42;
        $service->save($event, $data, 1, false);
        $this->assertSame(42.0, (float) $mark->fresh()->score);
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
