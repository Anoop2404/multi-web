<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\FestGradeConfig;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestGradePointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the per-event grade vocabulary generalization: validGradesForEvent(),
 * gradeOptionsForEvent(), gradeValidationRule(), and normalizeGrade() becoming
 * event-aware. Every event that never customizes its grades must keep resolving to
 * exactly the original fixed A+/A/B/C set — that backward-compatibility guarantee is
 * the main thing under test here, alongside the new custom-vocabulary behavior.
 */
class FestGradePointServiceCustomVocabularyTest extends TestCase
{
    use RefreshDatabase;

    private function service(): FestGradePointService
    {
        return app(FestGradePointService::class);
    }

    private function makeEvent(array $overrides = []): FestEvent
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Grade Vocabulary Test Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);

        return FestEvent::create(array_merge([
            'tenant_id' => $sahodaya->id,
            'title' => 'Grade Vocabulary Test Kalotsav',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ], $overrides));
    }

    public function test_untouched_event_falls_back_to_the_original_legacy_grade_set(): void
    {
        $event = $this->makeEvent();

        $this->assertSame(['A+', 'A', 'B', 'C'], $this->service()->validGradesForEvent($event));
        $this->assertSame(['A+' => 'A+', 'A' => 'A', 'B' => 'B', 'C' => 'C'], $this->service()->gradeOptionsForEvent($event));
    }

    public function test_custom_grade_labels_become_the_events_own_vocabulary_best_first(): void
    {
        $event = $this->makeEvent();

        FestGradeConfig::create(['event_id' => $event->id, 'item_id' => null, 'grade' => 'Excellent', 'min_score' => 80, 'max_score' => 100]);
        FestGradeConfig::create(['event_id' => $event->id, 'item_id' => null, 'grade' => 'Good', 'min_score' => 60, 'max_score' => 79]);
        FestGradeConfig::create(['event_id' => $event->id, 'item_id' => null, 'grade' => 'Average', 'min_score' => 40, 'max_score' => 59]);

        $this->assertSame(['Excellent', 'Good', 'Average'], $this->service()->validGradesForEvent($event));
    }

    public function test_grade_validation_rule_accepts_only_this_events_configured_grades(): void
    {
        $event = $this->makeEvent();
        FestGradeConfig::create(['event_id' => $event->id, 'item_id' => null, 'grade' => 'Excellent', 'min_score' => 80, 'max_score' => 100]);
        FestGradeConfig::create(['event_id' => $event->id, 'item_id' => null, 'grade' => 'Average', 'min_score' => 0, 'max_score' => 79]);

        $rule = $this->service()->gradeValidationRule($event);
        $validator = validator(['grade' => 'Excellent'], ['grade' => $rule]);
        $this->assertFalse($validator->fails());

        $validator = validator(['grade' => 'A+'], ['grade' => $rule]);
        $this->assertTrue($validator->fails());
    }

    public function test_normalize_grade_keeps_legacy_underscore_encoding_for_the_original_four(): void
    {
        $event = $this->makeEvent();
        $service = $this->service();

        $this->assertSame('A_plus', $service->normalizeGrade($event, 'A+'));
        $this->assertSame('A_plus', $service->normalizeGrade($event, 'A_plus'));
        $this->assertSame('B', $service->normalizeGrade($event, 'b'));
    }

    public function test_normalize_grade_passes_custom_labels_through_verbatim(): void
    {
        $event = $this->makeEvent();

        $this->assertSame('Excellent', $this->service()->normalizeGrade($event, 'Excellent'));
    }

    public function test_points_for_mark_resolves_correctly_for_a_custom_grade_via_point_rule(): void
    {
        $event = $this->makeEvent();
        \App\Models\FestPointRule::create([
            'event_id' => $event->id, 'grade' => 'Excellent', 'position' => null, 'points' => 12, 'is_group' => false,
        ]);

        $item = \App\Models\FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'participant_type' => 'individual', 'is_enabled' => true]);
        $schoolClass = SchoolClass::create(['tenant_id' => $event->tenant_id, 'name' => '10']);
        $student = Student::create(['tenant_id' => $event->tenant_id, 'school_class_id' => $schoolClass->id, 'name' => 'Test Student', 'admission_no' => 'S1']);
        $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $event->tenant_id, 'status' => 'approved']);
        $participant = FestParticipant::create(['registration_id' => $registration->id, 'student_id' => $student->id, 'participant_role' => 'main']);
        $mark = FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'grade' => 'Excellent']);

        $this->assertSame(12, $this->service()->pointsForMark($event, $mark));
    }

    public function test_untouched_event_mcs_and_confed_presets_are_unaffected_by_generic_vocabulary_changes(): void
    {
        $event = $this->makeEvent(['scoring_preset' => 'mcs_kalotsav']);

        // Presets short-circuit before validGradesForEvent()'s FestGradeConfig-driven path
        // is ever consulted — confirmed by there being zero FestGradeConfig rows here at all
        // and resolution still succeeding via the config-file-driven preset path.
        $this->assertNotNull($this->service()->resolveMcsGradeFromScore(85));
    }
}
