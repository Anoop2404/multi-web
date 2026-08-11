<?php

namespace Tests\Feature\State;

use App\Models\FestStateProgram;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateFestMark;
use App\Models\State\StateQualifierIntake;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\FestEffectiveSettingsResolverService;
use App\Services\Events\FestStateProgramService;
use App\Services\State\StateConductService;
use App\Services\State\StatePublicResultsProjectionService;
use App\Services\State\StateQualifierIntakeService;
use App\Services\State\StateQualifierMaterializationService;
use App\Services\State\StateRemittanceService;
use App\Services\State\StateResultPublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class StateFullPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('state:migrate');
    }

    public function test_complete_state_kalotsavam_pipeline_from_program_to_public_results(): void
    {
        // 1. Create and Publish Program (WP-02)
        $program = FestStateProgram::create([
            'title'               => 'Kerala State Kalotsavam 2026',
            'event_type'          => 'kalotsavam',
            'conduct_levels'      => ['sahodaya', 'state'],
            'academic_year'       => '2026-2027',
            'status'              => 'published',
            'level_event_settings' => [
                'sahodaya' => ['max_total_per_student' => 5],
                'state'    => ['max_total_per_student' => 3],
            ],
            'level_fees'          => ['state' => ['individual_amount' => 500]],
        ]);

        $programService = new FestStateProgramService();
        $programService->publish($program);

        // 2. Resolve Effective Settings (WP-02)
        $resolver = new FestEffectiveSettingsResolverService();
        $stateSettings = $resolver->resolve('state', $program);
        $this->assertEquals(3, $stateSettings['settings']['max_total_per_student']);

        // 3. Receive Qualifier Intake API payload (WP-07)
        $intakePayload = [
            'state_program_id' => $program->id,
            'source_event_id'  => 501,
            'entries'          => [
                [
                    'source_registration_id' => 'reg-501',
                    'source_participant_id'  => 'part-501',
                    'school_id'              => 'school-winner',
                    'school_name'            => 'Government HSS',
                    'item_id'                => '019fea66-9b8d-7361-9828-1f6bbacaf36e',
                    'item_code'              => 'LM01',
                    'item_name'              => 'Light Music',
                    'student_name'           => 'Jane Doe',
                    'class_name'             => 'Class 12',
                    'position'               => 1,
                    'grade'                  => 'A',
                    'points'                 => 10,
                ],
            ],
        ];

        $intakeService = new StateQualifierIntakeService();
        $intake = $intakeService->receive('pipeline:idempotency:1', $intakePayload, 'tenant-sahodaya-1');
        $approvedIntake = $intakeService->approve($intake, 1, 'Approved by Scrutiny');

        // 4. Materialize Entries on State DB Connection (WP-07)
        $matService = new StateQualifierMaterializationService();
        $matResult = $matService->materializeApprovedIntake($approvedIntake);
        $this->assertEquals(1, $matResult['registrations']);

        // 5. Remittance Calculation & Verification (WP-08)
        $sahodaya = Tenant::create(['id' => 'tenant-sahodaya-1', 'name' => 'Sahodaya One', 'type' => 'sahodaya']);
        $reviewer = User::factory()->create();

        $remService = new StateRemittanceService();
        $remittance = $remService->calculateDemand($program, $sahodaya, 1);
        $this->assertEquals(500.00, $remittance->amount);

        $verifiedRemittance = $remService->verifyProof($remittance, $reviewer->id, 'Paid via UTR112233');
        $this->assertEquals('verified', $verifiedRemittance->status);

        // 6. State Conduct & Chest Number Assignment (WP-08)
        $stateEvent = StateFestEvent::where('state_program_id', $program->id)->first();
        $this->assertNotNull($stateEvent);

        $conductService = new StateConductService();
        $assigned = $conductService->assignChestNumbers($stateEvent);
        $this->assertEquals(1, $assigned);

        // 7. Enter State-final marks and publish calculated State results.
        $stateRegistration = StateFestRegistration::where('state_event_id', $stateEvent->id)->firstOrFail();
        $stateParticipant = $stateRegistration->participants()->firstOrFail();
        StateFestMark::create([
            'state_event_id' => $stateEvent->id,
            'registration_id' => $stateRegistration->id,
            'participant_id' => $stateParticipant->id,
            'score' => 82,
            'grade' => 'A',
            'status' => 'draft',
        ]);
        app(StateResultPublicationService::class)->publish($stateEvent);

        // 8. Public Results Projection reads State marks only (WP-08)
        $publicService = new StatePublicResultsProjectionService();
        $publicRows = $publicService->getPublicResults($stateEvent->fresh());

        $this->assertCount(1, $publicRows);
        $this->assertEquals('Jane Doe', $publicRows[0]['student_name']);
        $this->assertEquals('101', $publicRows[0]['chest_number']);
        $this->assertEquals(1, $publicRows[0]['position']);
    }
}
