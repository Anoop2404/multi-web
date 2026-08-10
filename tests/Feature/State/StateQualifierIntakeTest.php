<?php

namespace Tests\Feature\State;

use App\Models\FestStateProgram;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateQualifierIntake;
use App\Services\State\StateQualifierIntakeService;
use App\Services\State\StateQualifierMaterializationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class StateQualifierIntakeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('state:migrate');
    }

    public function test_state_qualifier_intake_stores_and_materializes_approved_entries(): void
    {
        $program = FestStateProgram::create([
            'title'          => 'Kerala State Kalotsavam 2026',
            'event_type'     => 'kalotsavam',
            'conduct_levels' => ['sahodaya', 'state'],
            'status'         => 'published',
        ]);

        $payload = [
            'state_program_id' => $program->id,
            'source_event_id'  => 101,
            'entries'          => [
                [
                    'source_registration_id' => 'src-reg-1',
                    'source_participant_id'  => 'src-part-1',
                    'school_id'              => 'school-101',
                    'school_name'            => 'St Joseph HSS',
                    'item_id'                => '019fea66-9b8d-7361-9828-1f6bbacaf36e',
                    'item_code'              => 'LM01',
                    'item_name'              => 'Light Music (Boys)',
                    'student_name'           => 'John Doe',
                    'class_name'             => 'Class 10',
                    'position'               => 1,
                    'grade'                  => 'A',
                    'points'                 => 10,
                ],
            ],
        ];

        $service = new StateQualifierIntakeService();
        $idempotencyKey = 'intake:key:' . uniqid();

        $intake = $service->receive($idempotencyKey, $payload, 'tenant-sahodaya-1');

        $this->assertEquals('received', $intake->status);
        $this->assertEquals(1, $intake->entries()->count());

        // Replay same idempotency key
        $replayed = $service->receive($idempotencyKey, $payload, 'tenant-sahodaya-1');
        $this->assertEquals($intake->id, $replayed->id);

        // Approve intake
        $approved = $service->approve($intake, 1, 'Approved after verification');
        $this->assertEquals('approved', $approved->status);

        // Materialize entries to State DB connection
        $matService = new StateQualifierMaterializationService();
        $matResult = $matService->materializeApprovedIntake($approved);

        $this->assertEquals(1, $matResult['registrations']);
        $this->assertEquals(1, $matResult['participants']);

        $stateReg = StateFestRegistration::where('qualifier_entry_id', $approved->entries()->first()->id)->first();
        $this->assertNotNull($stateReg);
        $this->assertEquals('state', $stateReg->getConnectionName());
        $this->assertEquals('school-101', $stateReg->school_id);
    }
}
