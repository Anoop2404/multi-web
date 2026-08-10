<?php

namespace Tests\Feature\State;

use App\Models\FestStateProgram;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateFestRegistration;
use App\Models\Tenant;
use App\Models\User;
use App\Services\State\StateConductService;
use App\Services\State\StatePublicResultsProjectionService;
use App\Services\State\StateRemittanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class StateConductAndRemittanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('state:migrate');
    }

    public function test_state_remittance_demand_calculation_and_verification(): void
    {
        $program = FestStateProgram::create([
            'title'          => 'Kerala State Kalotsavam 2026',
            'event_type'     => 'kalotsavam',
            'conduct_levels' => ['sahodaya', 'state'],
            'status'         => 'published',
            'level_fees'     => ['state' => ['individual_amount' => 500]],
        ]);

        $sahodaya = Tenant::create(['id' => 'sahodaya-101', 'name' => 'Malappuram Sahodaya', 'type' => 'sahodaya']);
        $reviewer = User::factory()->create();

        $service = new StateRemittanceService();
        $remittance = $service->calculateDemand($program, $sahodaya, 10);

        $this->assertEquals(5000.00, $remittance->amount);
        $this->assertEquals('pending', $remittance->status);

        $verified = $service->verifyProof($remittance, $reviewer->id, 'Paid via NEFT UTR987654');
        $this->assertEquals('verified', $verified->status);
        $this->assertEquals($reviewer->id, $verified->reviewed_by);
    }

    public function test_state_conduct_chest_number_assignment_and_public_results(): void
    {
        $event = StateFestEvent::create([
            'state_program_id' => '019fea66-9b8d-7361-9828-1f6bbacaf36e',
            'name'             => 'State Final Kalotsavam',
            'status'           => 'published',
        ]);

        $reg = StateFestRegistration::create([
            'state_event_id' => $event->id,
            'school_id'      => 'sch-1',
            'school_name'    => 'Model HSS',
            'item_code'      => 'LM01',
            'status'         => 'approved',
        ]);

        $part = StateFestParticipant::create([
            'registration_id' => $reg->id,
            'student_name'    => 'State Finalist',
            'class_name'      => 'Class 10',
            'meta'            => ['position' => 1, 'grade' => 'A'],
        ]);

        $conductService = new StateConductService();
        $assigned = $conductService->assignChestNumbers($event);
        $this->assertEquals(1, $assigned);
        $this->assertEquals('101', $part->fresh()->chest_number);

        $publicService = new StatePublicResultsProjectionService();
        $publicRows = $publicService->getPublicResults($event);

        $this->assertCount(1, $publicRows);
        $this->assertEquals('State Finalist', $publicRows[0]['student_name']);
        $this->assertEquals('Model HSS', $publicRows[0]['school_name']);
        $this->assertEquals('101', $publicRows[0]['chest_number']);
    }
}
