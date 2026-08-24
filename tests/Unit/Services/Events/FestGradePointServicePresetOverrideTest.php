<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestGradeConfig;
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
 * A scoring_preset (mcs_kalotsav/confed_kalotsav) event's Grade Master/Rank Points tabs
 * were previously a dead end: fully editable-looking, but resolveGradeFromScore() and
 * pointsForMark() short-circuited to the fixed preset table unconditionally, before ever
 * checking FestGradeConfig/FestPointRule. This covers the fix — the preset is now only
 * the DEFAULT (used while nothing custom exists), not a hard override.
 */
class FestGradePointServicePresetOverrideTest extends TestCase
{
    use RefreshDatabase;

    private function service(): FestGradePointService
    {
        return app(FestGradePointService::class);
    }

    private function makeEvent(string $preset): FestEvent
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Preset Override Test Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);

        return FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Preset Test Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open', 'scoring_preset' => $preset,
        ]);
    }

    public function test_grade_resolution_uses_preset_default_when_nothing_custom_configured(): void
    {
        $event = $this->makeEvent('confed_kalotsav');

        // No FestGradeConfig rows at all — confed_kalotsav's own fixed table applies
        // (A >= 70, matches FestGradePointServiceTest's confed boundary coverage).
        $this->assertSame('A', $this->service()->resolveGradeFromScore($event, null, 85));
    }

    public function test_grade_resolution_prefers_custom_bands_once_any_are_configured(): void
    {
        $event = $this->makeEvent('confed_kalotsav');

        // Admin has added their own band that disagrees with the confed default (which
        // would grade 85 as 'A') — the custom band must win once it exists at all.
        FestGradeConfig::create(['event_id' => $event->id, 'item_id' => null, 'grade' => 'B', 'min_score' => 0, 'max_score' => 100]);

        $this->assertSame('B', $this->service()->resolveGradeFromScore($event, null, 85));
    }

    public function test_points_use_preset_default_when_no_point_rules_configured(): void
    {
        $event = $this->makeEvent('mcs_kalotsav');
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'participant_type' => 'individual']);
        [$participant] = $this->makeParticipant($event, $item);

        $mark = FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 1, 'grade' => 'A']);

        // No FestPointRule rows — mcs_kalotsav's own fixed table applies (non-zero).
        $this->assertGreaterThan(0, $this->service()->pointsForMark($event, $mark));
    }

    public function test_points_prefer_custom_point_rules_once_any_are_configured(): void
    {
        $event = $this->makeEvent('mcs_kalotsav');
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'participant_type' => 'individual']);
        [$participant] = $this->makeParticipant($event, $item);

        // Admin configured their own rule for Grade A / 1st place / individual.
        FestPointRule::create(['event_id' => $event->id, 'grade' => 'A', 'position' => 1, 'points' => 999, 'is_group' => false]);

        $mark = FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 1, 'grade' => 'A']);

        $this->assertSame(999, $this->service()->pointsForMark($event, $mark));
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
}
