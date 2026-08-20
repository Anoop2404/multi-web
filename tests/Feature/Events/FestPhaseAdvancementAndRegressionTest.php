<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestPhaseAdvancement;
use App\Models\FestRegistration;
use App\Models\FestRegistrationBatch;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\EventContext;
use App\Services\Events\FestEventPhaseService;
use App\Services\Events\FestPhaseAdvancementService;
use App\Services\Events\FestPhasedWorkflowService;
use App\Services\Events\FestPhasePublicationService;
use App\Services\Events\FestPhaseScoreboardService;
use App\Services\Events\FestPhaseTopologyService;
use App\Services\Events\FestRegistrationBatchFeeService;
use App\Services\Events\FestSchoolPhaseRegionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Regression coverage for the bugs found by actually conducting the MCS event locally
 * (registration -> billing -> results -> scoreboard), plus the new phase-advancement
 * feature. Companion to FestPhasedRegionalBillingWorkflowTest.php, which covers the
 * originally-shipped routing/invoice/publication/report-scoping behavior — none of these
 * cases were covered there.
 */
class FestPhaseAdvancementAndRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_student_fee_is_computed_per_phase_not_from_one_arbitrary_phase(): void
    {
        [$root, $regions, $phases] = $this->fourPhaseFixture();
        $school = $this->makeSchool($root->tenant_id);

        $phases['DIGI']->update(['student_registration_fee' => 50]);
        $phases['OFF_STAGE']->update(['student_registration_fee' => 75]);

        $this->registerOneStudent($root, $phases['DIGI'], $school);
        $this->registerOneStudent($root, $phases['OFF_STAGE'], $school, $regions['NILAMBUR']->id);

        $fee = app(FestRegistrationBatchFeeService::class)->recalculateBatch(
            $root, $school->id, FestRegistrationBatch::where('event_id', $root->id)->where('code', 'LEVEL_1')->first()
        );

        $studentLines = $fee->lines->where('line_type', 'student_registration');
        $this->assertCount(2, $studentLines, 'Expected one student-fee line per phase, not one merged/arbitrary line.');
        $this->assertEqualsCanonicalizing([50.0, 75.0], $studentLines->pluck('amount')->map(fn ($a) => (float) $a)->all());
    }

    public function test_combined_scoreboard_includes_non_regional_phase_leaves(): void
    {
        [$root, $regions, $phases] = $this->fourPhaseFixture();
        $school = $this->makeSchool($root->tenant_id);

        $this->registerAndScoreOneStudent($root, $phases['DIGI'], $school, null, position: 1);
        $this->registerAndScoreOneStudent($root, $phases['OFF_STAGE'], $school, $regions['NILAMBUR']->id, position: 1);

        app(FestPhasePublicationService::class)->publishResults($this->leafFor($root, $phases['DIGI'], null));
        // Publish BOTH Off Stage region leaves (Tirur has no registrations) so the phase
        // itself is marked complete -- cumulativeOverall() only counts a phase once every
        // one of its region leaves has published, by design (a partially-published regional
        // phase shouldn't count toward the overall yet). combinedScoreboard() has no such
        // per-phase-completeness gate (it just sums whatever FestResult rows already exist),
        // so the two only agree once the phase is genuinely complete -- which is what this
        // test needs to isolate the role-filter fix from that separate, correct gate.
        app(FestPhasePublicationService::class)->publishResults($this->leafFor($root, $phases['OFF_STAGE'], $regions['NILAMBUR']->id));
        app(FestPhasePublicationService::class)->publishResults($this->leafFor($root, $phases['OFF_STAGE'], $regions['TIRUR']->id));

        $cumulative = app(FestPhaseScoreboardService::class)->cumulativeOverall($root);
        $combined = EventContext::for($root)->scoreboardBySchool();

        $this->assertSame($cumulative[0]['total_points'] ?? null, $combined[0]['total_points'] ?? null);
        $this->assertGreaterThan(0, $combined[0]['total_points'] ?? 0, 'combinedScoreboard()/scoreboardBySchool() must not drop non-regional phase points.');
    }

    public function test_disabling_a_region_after_publication_does_not_erase_its_points(): void
    {
        [$root, $regions, $phases] = $this->fourPhaseFixture();
        $school = $this->makeSchool($root->tenant_id);

        $this->registerAndScoreOneStudent($root, $phases['OFF_STAGE'], $school, $regions['NILAMBUR']->id, position: 1);
        app(FestPhasePublicationService::class)->publishResults($this->leafFor($root, $phases['OFF_STAGE'], $regions['NILAMBUR']->id));

        $before = app(FestPhaseScoreboardService::class)->phaseScoreboard($phases['OFF_STAGE']);
        $this->assertNotEmpty($before);

        \App\Models\FestPhaseRegion::where('phase_id', $phases['OFF_STAGE']->id)
            ->where('region_id', $regions['NILAMBUR']->id)
            ->update(['enabled' => false]);

        $after = app(FestPhaseScoreboardService::class)->phaseScoreboard($phases['OFF_STAGE']);
        $this->assertSame($before[0]['total_points'], $after[0]['total_points'] ?? null, 'Disabling a region must not erase its already-published points.');
    }

    public function test_phase_region_selection_locks_immediately_not_deferred_to_first_registration(): void
    {
        [$root, $regions, $phases] = $this->fourPhaseFixture();
        $school = $this->makeSchool($root->tenant_id);

        $selector = app(FestSchoolPhaseRegionService::class);
        $selection = $selector->select($root, $phases['OFF_STAGE'], $school->id, $regions['NILAMBUR']->id);

        $this->assertNotNull($selection->locked_at, 'A first selection must be locked immediately, before any registration exists.');

        $this->expectException(ValidationException::class);
        $selector->select($root, $phases['OFF_STAGE'], $school->id, $regions['TIRUR']->id);
    }

    public function test_points_for_mark_returns_zero_not_raw_score_for_an_unranked_position(): void
    {
        $event = FestEvent::create([
            'tenant_id' => Tenant::create([
                'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Grade Fallback Test Sahodaya',
                'domain' => Str::uuid().'.test', 'is_active' => true,
            ])->id,
            'title' => 'Grade Fallback Test Event',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'participant_type' => 'individual', 'is_enabled' => true]);
        $schoolClass = SchoolClass::create(['tenant_id' => $event->tenant_id, 'name' => '10']);
        $student = Student::create(['tenant_id' => $event->tenant_id, 'school_class_id' => $schoolClass->id, 'name' => 'Test Student', 'admission_no' => 'S1']);
        $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $event->tenant_id, 'status' => 'approved']);
        $participant = FestParticipant::create(['registration_id' => $registration->id, 'student_id' => $student->id, 'participant_role' => 'main']);
        $mark = FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'grade' => 'A', 'position' => 4, 'score' => 80]);

        $this->assertSame(0, app(\App\Services\Events\FestGradePointService::class)->pointsForMark($event, $mark));
    }

    public function test_advance_moves_regional_winners_into_a_later_phase_item_and_is_idempotent(): void
    {
        [$root, $regions, $phases] = $this->fourPhaseFixture();
        $schoolA = $this->makeSchool($root->tenant_id, 'School A');
        $schoolB = $this->makeSchool($root->tenant_id, 'School B');

        $offStageItem = FestEventItem::where('event_id', $root->id)->where('phase_id', $phases['OFF_STAGE']->id)->first();
        $districtItem = FestEventItem::where('event_id', $root->id)->where('phase_id', $phases['DISTRICT']->id)->first();

        $regA = $this->registerAndScoreOneStudent($root, $phases['OFF_STAGE'], $schoolA, $regions['NILAMBUR']->id, position: 1);
        $regB = $this->registerAndScoreOneStudent($root, $phases['OFF_STAGE'], $schoolB, $regions['TIRUR']->id, position: 1);

        // The cumulative ledger requires phases to publish/lock in sort_order sequence, so
        // Digi Fest (sort_order 1, no registrations here) must publish before Off Stage
        // (sort_order 2) is allowed to.
        app(FestPhasePublicationService::class)->publishResults($this->leafFor($root, $phases['DIGI'], null));
        app(FestPhasePublicationService::class)->publishResults($this->leafFor($root, $phases['OFF_STAGE'], $regions['NILAMBUR']->id));
        app(FestPhasePublicationService::class)->publishResults($this->leafFor($root, $phases['OFF_STAGE'], $regions['TIRUR']->id));

        $service = app(FestPhaseAdvancementService::class);
        $candidates = $service->eligibleCandidates($offStageItem);
        $this->assertCount(2, $candidates);

        $advanced = $service->advance($offStageItem, $districtItem, [$regA->id, $regB->id]);
        $this->assertCount(2, $advanced);

        $districtLeaf = $this->leafFor($root, $phases['DISTRICT'], null);
        $this->assertSame(2, FestRegistration::where('event_id', $districtLeaf->id)->where('mode', 'phase_advance')->count());

        // Idempotent: re-advancing the same source registrations must not create duplicates.
        $again = $service->advance($offStageItem, $districtItem, [$regA->id, $regB->id]);
        $this->assertCount(2, $again);
        $this->assertSame(2, FestPhaseAdvancement::count());

        // Withdraw cancels the target registration and is reflected immediately.
        $service->withdraw($advanced->first());
        $this->assertTrue($advanced->first()->fresh()->isWithdrawn());
        $this->assertSame('withdrawn', FestRegistration::find($advanced->first()->target_registration_id)->status);
    }

    /** @return array{0: FestEvent, 1: array<string, Region>, 2: array<string, FestEventPhase>} */
    private function fourPhaseFixture(): array
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Regression Test Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'REG', 'student_data_mode' => 'counts_only']);

        $root = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Regression Test Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open', 'fee_type' => 'none',
            'fee_settings' => ['fee_model' => 'item_catalog'],
            'workflow_mode' => FestPhasedWorkflowService::MODE, 'phase_mode_enabled' => true, 'conduct_mode' => 'partitioned',
        ]);

        $regions = collect(['NILAMBUR' => 'Nilambur', 'TIRUR' => 'Tirur'])
            ->mapWithKeys(fn (string $name, string $code) => [$code => Region::create([
                'tenant_id' => $sahodaya->id, 'name' => $name, 'code' => $code, 'is_active' => true,
            ])]);

        $batch = FestRegistrationBatch::create([
            'event_id' => $root->id, 'code' => 'LEVEL_1', 'name' => 'Level 1', 'sort_order' => 1,
            'school_base_fee' => 4000, 'status' => 'registration_open', 'registration_close' => now()->addMonth(),
        ]);

        $phaseService = app(FestEventPhaseService::class);
        $workflow = app(FestPhasedWorkflowService::class);
        $definitions = [
            ['code' => 'DIGI', 'name' => 'Digi Fest', 'sort_order' => 1, 'regional' => false, 'regions' => []],
            ['code' => 'OFF_STAGE', 'name' => 'Off Stage', 'sort_order' => 2, 'regional' => true, 'regions' => [$regions['NILAMBUR']->id, $regions['TIRUR']->id]],
            ['code' => 'DISTRICT', 'name' => 'District Kalotsav', 'sort_order' => 3, 'regional' => false, 'regions' => []],
        ];

        $phases = collect();
        foreach ($definitions as $d) {
            $phase = $phaseService->createPhase($root, [
                'name' => $d['name'], 'code' => $d['code'], 'sort_order' => $d['sort_order'],
                'registration_batch_id' => $batch->id, 'is_regional' => $d['regional'],
            ]);
            $phase->update(['registration_open' => now()->subDay(), 'registration_close' => now()->addMonth(), 'status' => 'registration_open']);
            if ($d['regions'] !== []) {
                $workflow->syncAllowedRegions($phase, $d['regions']);
            }
            $phases[$d['code']] = $phase->fresh(['registrationBatch', 'allowedRegions.region']);

            FestEventItem::create([
                'event_id' => $root->id, 'phase_id' => $phase->id, 'title' => $d['name'].' Item',
                'item_code' => $d['code'].'-01', 'is_enabled' => true, 'fee_amount' => 0,
            ]);
        }

        app(FestPhaseTopologyService::class)->sync($root->fresh());

        return [$root->fresh(), $regions->all(), $phases->all()];
    }

    private function makeSchool(string $sahodayaId, string $name = 'Test School'): Tenant
    {
        return Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodayaId,
            'name' => $name, 'domain' => Str::uuid().'.test', 'membership_status' => 'approved', 'is_active' => true,
        ]);
    }

    private function leafFor(FestEvent $root, FestEventPhase $phase, ?int $regionId): FestEvent
    {
        return FestEvent::where('parent_event_id', $root->id)
            ->where('source_phase_id', $phase->id)
            ->where('region_id', $regionId)
            ->firstOrFail();
    }

    private function registerOneStudent(FestEvent $root, FestEventPhase $phase, Tenant $school, ?int $regionId = null): FestRegistration
    {
        if ($phase->isRegional()) {
            app(FestSchoolPhaseRegionService::class)->select($root, $phase, $school->id, $regionId);
        }

        $leaf = $this->leafFor($root, $phase, $phase->isRegional() ? $regionId : null);
        $leafItem = FestEventItem::where('event_id', $leaf->id)->firstOrFail();

        $registration = FestRegistration::create([
            'event_id' => $leaf->id, 'item_id' => $leafItem->id, 'school_id' => $school->id,
            'status' => 'approved', 'submitted_at' => now(),
        ]);
        FestParticipant::create(['registration_id' => $registration->id, 'student_id' => 1, 'participant_role' => 'performer']);

        return $registration;
    }

    private function registerAndScoreOneStudent(FestEvent $root, FestEventPhase $phase, Tenant $school, ?int $regionId, int $position): FestRegistration
    {
        $registration = $this->registerOneStudent($root, $phase, $school, $regionId);
        $participant = $registration->participants->first();

        FestMark::create([
            'event_id' => $registration->event_id, 'item_id' => $registration->item_id,
            'participant_id' => $participant->id, 'grade' => 'A', 'position' => $position, 'score' => 90,
        ]);

        return $registration;
    }
}
