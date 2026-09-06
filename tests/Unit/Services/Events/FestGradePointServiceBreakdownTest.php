<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestPointRule;
use App\Models\FestRegistration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestGradePointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression coverage for a real bug reported from the public results page (e.g.
 * /fest/{event}/items/{item}/results): participants ranked 4th and below showed a real
 * Grade (A/B/C) but "GRADE PTS" rendered as "—" even though their total score visibly
 * came from grade points alone. pointsBreakdown() only ever returned grade_points
 * together with rank_points, both or neither -- since place_points only covers 1st-3rd
 * (config/fest_confed_kalotsav_scoring.php), rank 4+ always resolved placePoints to
 * null, and the old `if ($gradePoints !== null && $placePoints !== null && ...)` check
 * nulled out the otherwise-valid grade_points too just because there was no rank
 * component to pair it with.
 */
class FestGradePointServiceBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private function service(): FestGradePointService
    {
        return app(FestGradePointService::class);
    }

    private function makeEvent(string $preset = 'confed_kalotsav'): FestEvent
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Breakdown Test Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);

        return FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Breakdown Test Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open', 'scoring_preset' => $preset,
        ]);
    }

    /** @return array{0: FestParticipant} */
    private function makeParticipant(FestEvent $event, FestEventItem $item): array
    {
        $schoolClass = SchoolClass::create(['tenant_id' => $event->tenant_id, 'name' => '10']);
        $student = Student::create([
            'tenant_id' => $event->tenant_id, 'school_class_id' => $schoolClass->id,
            'name' => 'Test Student', 'admission_no' => 'S'.random_int(1000, 9999),
        ]);

        $registration = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $event->tenant_id, 'status' => 'approved',
        ]);

        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'student_id' => $student->id, 'participant_role' => 'performer',
        ]);

        return [$participant];
    }

    public function test_rank_and_grade_points_both_shown_for_top_three_matching_official_table(): void
    {
        $event = $this->makeEvent();
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'participant_type' => 'individual']);
        [$participant] = $this->makeParticipant($event, $item);

        // No custom point rules — confedPointsForMark() uses individual_points.A.1 = 10,
        // which is exactly place_points.individual.1 (5) + grade_points.individual.A (5).
        $mark = FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 1, 'grade' => 'A']);

        $breakdown = $this->service()->pointsBreakdown($event, $mark);

        $this->assertSame(['rank_points' => 5, 'grade_points' => 5, 'total' => 10], $breakdown);
    }

    public function test_grade_points_shown_alone_for_fourth_place_and_below(): void
    {
        $event = $this->makeEvent();
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'participant_type' => 'individual']);
        [$participant] = $this->makeParticipant($event, $item);

        // The official manual's grade-only points (no rank component past 3rd place) are
        // configured as an "any position" custom rule matching grade_points.individual.A —
        // this is how an admin following the manual would set up scoring for 4th place+.
        FestPointRule::create(['event_id' => $event->id, 'grade' => 'A', 'position' => null, 'points' => 5, 'is_group' => false]);

        $mark = FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 4, 'grade' => 'A']);

        $this->assertSame(5, $this->service()->pointsForMark($event, $mark));

        $breakdown = $this->service()->pointsBreakdown($event, $mark);

        // Before the fix: ['rank_points' => null, 'grade_points' => null, 'total' => 5] —
        // the real bug, hiding a valid grade because place_points has no 4th-place entry.
        $this->assertSame(['rank_points' => null, 'grade_points' => 5, 'total' => 5], $breakdown);
    }

    public function test_both_null_when_top_three_total_does_not_match_official_split(): void
    {
        $event = $this->makeEvent();
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'participant_type' => 'individual']);
        [$participant] = $this->makeParticipant($event, $item);

        // Admin overrode 1st place / Grade A with a value that doesn't match the manual's
        // 5+5=10 split — the breakdown has no defined rank/grade components to show.
        FestPointRule::create(['event_id' => $event->id, 'grade' => 'A', 'position' => 1, 'points' => 999, 'is_group' => false]);

        $mark = FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 1, 'grade' => 'A']);

        $breakdown = $this->service()->pointsBreakdown($event, $mark);

        $this->assertSame(['rank_points' => null, 'grade_points' => null, 'total' => 999], $breakdown);
    }

    public function test_both_null_when_fourth_place_total_does_not_match_grade_alone(): void
    {
        $event = $this->makeEvent();
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'participant_type' => 'individual']);
        [$participant] = $this->makeParticipant($event, $item);

        // A custom "any position" rule that doesn't match grade_points.individual.A (5) —
        // the grade-only total can't be trusted to genuinely be "just the grade points".
        FestPointRule::create(['event_id' => $event->id, 'grade' => 'A', 'position' => null, 'points' => 777, 'is_group' => false]);

        $mark = FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 4, 'grade' => 'A']);

        $breakdown = $this->service()->pointsBreakdown($event, $mark);

        $this->assertSame(['rank_points' => null, 'grade_points' => null, 'total' => 777], $breakdown);
    }
}
