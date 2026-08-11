<?php

namespace Tests\Feature\State;

use App\Models\FestStateProgram;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateFestMark;
use App\Models\Tenant;
use App\Models\User;
use App\Services\State\StateConductService;
use App\Services\State\StatePublicResultsProjectionService;
use App\Services\State\StateRemittanceService;
use App\Services\State\StateResultPublicationService;
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
            'scoring_preset'   => 'confed_kalotsav',
        ]);

        $reg = StateFestRegistration::create([
            'state_event_id' => $event->id,
            'school_id'      => 'sch-1',
            'school_name'    => 'Model HSS',
            'item_code'      => 'LM01',
            'status'         => 'approved',
        ]);

        $part = StateFestParticipant::create([
            'state_event_id'  => $event->id,
            'registration_id' => $reg->id,
            'student_name'    => 'State Finalist',
            'class_name'      => 'Class 10',
            'meta'            => ['qualifier_position' => 2, 'qualifier_grade' => 'B'],
        ]);

        $conductService = new StateConductService();
        $assigned = $conductService->assignChestNumbers($event);
        $this->assertEquals(1, $assigned);
        $this->assertEquals('101', $part->fresh()->chest_number);

        StateFestMark::create([
            'state_event_id' => $event->id,
            'registration_id' => $reg->id,
            'participant_id' => $part->id,
            'score' => 88,
            'grade' => 'A',
            'status' => 'draft',
        ]);

        $publicService = new StatePublicResultsProjectionService();
        $this->assertSame([], $publicService->getPublicResults($event));

        $published = app(StateResultPublicationService::class)->publish($event);
        $this->assertEquals(['items' => 1, 'marks' => 1], $published);
        $publicRows = $publicService->getPublicResults($event->fresh());

        $this->assertCount(1, $publicRows);
        $this->assertEquals('State Finalist', $publicRows[0]['student_name']);
        $this->assertEquals('Model HSS', $publicRows[0]['school_name']);
        $this->assertEquals('101', $publicRows[0]['chest_number']);
        $this->assertEquals(1, $publicRows[0]['position']);
        $this->assertEquals('A', $publicRows[0]['grade']);
        $this->assertTrue($event->fresh()->scoring_locked);
    }

    public function test_incremental_chest_number_assignment_continues_after_existing_numbers(): void
    {
        $event = StateFestEvent::create([
            'state_program_id' => '019fea66-9b8d-7361-9828-1f6bbacaf36e',
            'name' => 'State Final',
            'status' => 'published',
        ]);

        $firstRegistration = StateFestRegistration::create([
            'state_event_id' => $event->id,
            'school_id' => 'school-1',
            'item_code' => 'ONE',
            'status' => 'approved',
        ]);
        StateFestParticipant::create([
            'state_event_id' => $event->id,
            'registration_id' => $firstRegistration->id,
            'student_name' => 'First',
            'chest_number' => '101',
        ]);

        $secondRegistration = StateFestRegistration::create([
            'state_event_id' => $event->id,
            'school_id' => 'school-2',
            'item_code' => 'TWO',
            'status' => 'approved',
        ]);
        $second = StateFestParticipant::create([
            'state_event_id' => $event->id,
            'registration_id' => $secondRegistration->id,
            'student_name' => 'Second',
        ]);

        $this->assertSame(1, app(StateConductService::class)->assignChestNumbers($event));
        $this->assertSame('102', $second->fresh()->chest_number);
    }

    public function test_group_registration_is_scored_once_while_public_results_show_the_roster(): void
    {
        $event = StateFestEvent::create([
            'state_program_id' => '019fea66-9b8d-7361-9828-1f6bbacaf36e',
            'name' => 'State Group Final',
            'status' => 'published',
            'scoring_preset' => 'confed_kalotsav',
        ]);
        $registration = StateFestRegistration::create([
            'state_event_id' => $event->id,
            'school_id' => 'group-school',
            'school_name' => 'Group School',
            'item_id' => '019fea66-9b8d-7361-9828-1f6bbacaf36e',
            'item_code' => 'GD01',
            'status' => 'approved',
            'meta' => ['participant_type' => 'group'],
        ]);
        $first = StateFestParticipant::create([
            'state_event_id' => $event->id,
            'registration_id' => $registration->id,
            'student_name' => 'Team Member One',
        ]);
        StateFestParticipant::create([
            'state_event_id' => $event->id,
            'registration_id' => $registration->id,
            'student_name' => 'Team Member Two',
        ]);
        StateFestMark::create([
            'state_event_id' => $event->id,
            'registration_id' => $registration->id,
            'participant_id' => $first->id,
            'score' => 90,
            'status' => 'draft',
        ]);

        $result = app(StateResultPublicationService::class)->publish($event);

        $this->assertSame(['items' => 1, 'marks' => 1], $result);
        $publicResult = app(StatePublicResultsProjectionService::class)->getPublicResults($event->fresh())[0];
        $this->assertSame('Team Member One, Team Member Two', $publicResult['student_name']);
        $this->assertCount(1, app(StateResultPublicationService::class)->schoolRankings($event->fresh()));
    }
}
