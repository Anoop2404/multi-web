<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\Tenant;
use App\Services\Events\FestSportsAutoRankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Auto-rank must use dense tie ranking (1,1,2,3 — never 1,1,3,4) and now works for any
 * event type: it ranks by measurement_value when present (sports track/field, unchanged
 * behavior), and falls back to ranking by score (a judged Grand Total) otherwise — the
 * gap that left non-sports events with no auto-rank at all, and left the Sahodaya-admin
 * Marks page's Auto-rank button posting to an unregistered route.
 */
class FestSportsAutoRankServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeEventAndItem(string $eventType, string $participantType = 'individual'): array
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Auto Rank Test Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        $event = FestEvent::create(['tenant_id' => $tenant->id, 'title' => 'Auto Rank Test Meet', 'event_type' => $eventType]);
        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Test Item', 'participant_type' => $participantType, 'is_enabled' => true,
        ]);
        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $tenant->id,
            'name' => 'Auto Rank Test School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);

        return [$event, $item, $school];
    }

    private function markWithScore(FestEvent $event, FestEventItem $item, Tenant $school, float $score): FestMark
    {
        $registration = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id,
            'status' => 'approved', 'submitted_at' => now(),
        ]);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $event->id,
            'student_id' => 1, 'participant_type' => 'student', 'participant_role' => 'performer',
        ]);

        return FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'score' => $score]);
    }

    private function markWithMeasurement(FestEvent $event, FestEventItem $item, Tenant $school, string $measurement): FestMark
    {
        $registration = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id,
            'status' => 'approved', 'submitted_at' => now(),
        ]);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $event->id,
            'student_id' => 1, 'participant_type' => 'student', 'participant_role' => 'performer',
        ]);

        return FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'measurement_value' => $measurement]);
    }

    public function test_score_based_ranking_uses_dense_ties_and_preserves_the_score(): void
    {
        [$event, $item, $school] = $this->makeEventAndItem('kalolsavam');
        $m1 = $this->markWithScore($event, $item, $school, 90);
        $m2 = $this->markWithScore($event, $item, $school, 90); // tied with m1
        $m3 = $this->markWithScore($event, $item, $school, 85);
        $m4 = $this->markWithScore($event, $item, $school, 80);

        $result = app(FestSportsAutoRankService::class)->rankItem($event, $item);

        $this->assertSame(4, $result['ranked']);
        $this->assertSame(1, $m1->fresh()->position);
        $this->assertSame(1, $m2->fresh()->position, 'Tied score must share rank 1.');
        $this->assertSame(2, $m3->fresh()->position, 'Dense ranking: next distinct score is 2nd, not 3rd.');
        $this->assertSame(3, $m4->fresh()->position);

        // Score is the real judged Grand Total — auto-rank must not overwrite it.
        $this->assertEquals(90.0, (float) $m1->fresh()->score);
        $this->assertEquals(80.0, (float) $m4->fresh()->score);
    }

    public function test_non_sports_event_no_longer_aborts(): void
    {
        [$event, $item, $school] = $this->makeEventAndItem('kalolsavam');
        $this->markWithScore($event, $item, $school, 95);

        $result = app(FestSportsAutoRankService::class)->rankItem($event, $item);

        $this->assertSame(1, $result['ranked']);
    }

    public function test_measurement_based_ranking_still_works_and_still_writes_points_to_score(): void
    {
        [$event, $item, $school] = $this->makeEventAndItem('sports');
        $item->update(['ranking_direction' => 'asc']); // lower time is better
        $m1 = $this->markWithMeasurement($event, $item, $school, '11.2');
        $m2 = $this->markWithMeasurement($event, $item, $school, '11.2'); // tied
        $m3 = $this->markWithMeasurement($event, $item, $school, '11.8');

        app(FestSportsAutoRankService::class)->rankItem($event, $item);

        $this->assertSame(1, $m1->fresh()->position);
        $this->assertSame(1, $m2->fresh()->position);
        $this->assertSame(2, $m3->fresh()->position, 'Dense ranking applies to measurement-based ranking too.');
    }

    public function test_prefers_measurement_over_score_when_both_present(): void
    {
        [$event, $item, $school] = $this->makeEventAndItem('sports');
        // A mark with a real measurement should route through the measurement branch
        // even if the event also has some other item with only scores configured.
        $this->markWithMeasurement($event, $item, $school, '10.5');

        $result = app(FestSportsAutoRankService::class)->rankItem($event, $item);

        $this->assertSame(1, $result['ranked']);
    }

    public function test_no_data_at_all_throws_validation_exception(): void
    {
        [$event, $item] = $this->makeEventAndItem('kalolsavam');

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(FestSportsAutoRankService::class)->rankItem($event, $item);
    }

    public function test_auto_rank_does_not_assign_rank_to_zero_or_empty_or_absent_marks(): void
    {
        [$event, $item, $school] = $this->makeEventAndItem('sports');
        $item->update(['result_method' => 'time']);

        // Participant 1: 11.20s -> should get Rank 1
        $m1 = $this->markWithMeasurement($event, $item, $school, '11.20');

        // Participant 2: 12.10s -> should get Rank 2
        $m2 = $this->markWithMeasurement($event, $item, $school, '12.10');

        // Participant 3: 0 -> should NOT receive rank!
        $m3 = $this->markWithMeasurement($event, $item, $school, '0');

        // Participant 4: empty string -> should NOT receive rank!
        $m4 = $this->markWithMeasurement($event, $item, $school, '');

        $result = app(FestSportsAutoRankService::class)->rankItem($event, $item);

        $this->assertSame(2, $result['ranked'], 'Only the 2 participants with valid positive measurements should be ranked.');
        $this->assertSame(1, $m1->fresh()->position);
        $this->assertSame(2, $m2->fresh()->position);
        $this->assertNull($m3->fresh()->position, 'Participant with 0 must not receive a rank.');
        $this->assertNull($m4->fresh()->position, 'Participant with empty mark must not receive a rank.');
    }

    public function test_auto_rank_option_based_on_time_or_length(): void
    {
        [$event, $item, $school] = $this->makeEventAndItem('sports');

        // Distances: 5.45m and 5.10m
        $m1 = $this->markWithMeasurement($event, $item, $school, '5.45');
        $m2 = $this->markWithMeasurement($event, $item, $school, '5.10');

        // When ranked with basis 'distance' (length), longer distance (5.45) wins Rank 1!
        app(FestSportsAutoRankService::class)->rankItem($event, $item, 'distance');

        $this->assertSame(1, $m1->fresh()->position);
        $this->assertSame(2, $m2->fresh()->position);

        // When ranked with basis 'time', lower time (5.10) would win Rank 1!
        app(FestSportsAutoRankService::class)->rankItem($event, $item, 'time');

        $this->assertSame(2, $m1->fresh()->position);
        $this->assertSame(1, $m2->fresh()->position);
    }
}
