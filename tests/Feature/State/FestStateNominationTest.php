<?php

namespace Tests\Feature\State;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestStateProgram;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\State\FestStateNominationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class FestStateNominationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('state:migrate');
    }

    public function test_candidate_pool_aggregates_eligible_certified_winners(): void
    {
        $sahodaya = Tenant::create(['id' => 'sahodaya-1', 'name' => 'Sahodaya One', 'type' => 'sahodaya']);
        $school = Tenant::create(['id' => 'school-1', 'name' => 'School One', 'type' => 'school', 'parent_id' => 'sahodaya-1']);

        $program = FestStateProgram::create([
            'title'          => 'Kerala State Kalotsavam 2026',
            'event_type'     => 'kalolsavam',
            'conduct_levels' => ['sahodaya', 'state'],
            'status'         => 'published',
        ]);

        $hubEvent = FestEvent::create([
            'tenant_id'         => 'sahodaya-1',
            'title'             => 'Sahodaya Kalotsavam 2026',
            'event_type'        => 'kalolsavam',
            'level_round'       => 'sahodaya',
            'results_published' => true,
            'status'            => 'published',
        ]);

        $item = FestEventItem::create([
            'event_id'              => $hubEvent->id,
            'state_program_item_id' => '019fea66-9b8d-7361-9828-1f6bbacaf36e',
            'title'                 => 'Bharatanatyam (Girls)',
            'category'              => 'dance',
            'item_code'             => 'DN01',
        ]);

        $schoolClass = SchoolClass::create(['tenant_id' => 'school-1', 'name' => 'Class 10']);
        $student = Student::create(['name' => 'State Candidate', 'tenant_id' => 'school-1', 'school_class_id' => $schoolClass->id]);

        $reg = FestRegistration::create([
            'event_id'  => $hubEvent->id,
            'item_id'   => $item->id,
            'school_id' => $school->id,
            'status'    => 'approved',
        ]);

        $part = FestParticipant::create([
            'registration_id' => $reg->id,
            'student_id'      => $student->id,
        ]);

        FestMark::create([
            'event_id'       => $hubEvent->id,
            'item_id'        => $item->id,
            'registration_id'=> $reg->id,
            'participant_id' => $part->id,
            'score'          => 95.00,
            'position'       => 1,
            'grade'          => 'A',
        ]);

        $service = new FestStateNominationService();
        $pool = $service->candidatePool($program, $hubEvent);

        $this->assertCount(1, $pool);
        $this->assertEquals('Bharatanatyam (Girls)', $pool[0]['item_title']);
        $this->assertEquals('State Candidate', $pool[0]['student_name']);
    }

    public function test_qualifier_payload_keeps_teams_separate_and_preserves_eligibility_data(): void
    {
        $sahodaya = Tenant::create(['id' => 'sahodaya-1', 'name' => 'Sahodaya', 'type' => 'sahodaya']);
        $school = Tenant::create(['id' => 'school-1', 'name' => 'School', 'type' => 'school', 'parent_id' => $sahodaya->id]);
        $program = FestStateProgram::create(['title' => 'State', 'event_type' => 'kalolsavam', 'status' => 'published']);
        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Fest', 'event_type' => 'kalolsavam', 'status' => 'published']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Dance', 'participant_type' => 'group',
            'item_code' => 'D1', 'state_program_item_id' => (string) \Illuminate\Support\Str::uuid()]);
        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => 'Class 6']);
        $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id,
            'school_id' => $school->id, 'status' => 'approved']);
        foreach ([1, 2] as $team) {
            $group = \App\Models\FestGroup::create(['event_id' => $event->id,
                'registration_id' => $registration->id, 'team_name' => 'Team '.$team]);
            foreach ([1, 2] as $member) {
                $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id,
                    'name' => "Pupil {$team} {$member}", 'gender' => 'female']);
                $participant = FestParticipant::create(['registration_id' => $registration->id,
                    'student_id' => $student->id, 'group_id' => $group->id, 'participant_role' => 'performer']);
                FestMark::create(['event_id' => $event->id, 'item_id' => $item->id,
                    'registration_id' => $registration->id, 'participant_id' => $participant->id,
                    'score' => 90, 'position' => $team, 'grade' => 'A']);
            }
        }
        $payload = app(\App\Services\State\FestStateQualifierPayloadBuilder::class)->build($program, $event, $sahodaya->id);
        $this->assertCount(2, $payload['entries']);
        foreach ($payload['entries'] as $index => $entry) {
            $this->assertCount(2, $entry['participants']);
            $this->assertSame('Pupil '.($index + 1).' 1', $entry['participants'][0]['student_name']);
            $this->assertSame('Class 6', $entry['participants'][0]['class_name']);
            $this->assertSame('female', $entry['participants'][0]['gender']);
            $this->assertNotEmpty($entry['participants'][0]['source_student_id']);
        }
    }

    public function test_maker_checker_nomination_flow_enforces_user_separation(): void
    {
        $program = FestStateProgram::create([
            'title'          => 'Kerala State Kalotsavam 2026',
            'event_type'     => 'kalolsavam',
            'conduct_levels' => ['sahodaya', 'state'],
            'status'         => 'published',
        ]);

        $hubEvent = FestEvent::create([
            'tenant_id'   => 'sahodaya-1',
            'title'       => 'Sahodaya Kalotsavam 2026',
            'event_type'  => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status'      => 'published',
        ]);

        $maker = User::factory()->create(['name' => 'Maker User']);
        $checker = User::factory()->create(['name' => 'Checker User']);

        $service = new FestStateNominationService();

        $selections = [
            ['mark_id' => 1, 'nomination_type' => 'primary', 'priority_order' => 1],
        ];

        $draft = $service->createMakerNomination($program, $hubEvent, $maker, $selections);
        $this->assertEquals('ready_for_check', $draft['status']);

        // Checker certifying
        $certified = $service->certifyCheckerNomination($draft, $checker);
        $this->assertEquals('certified', $certified['status']);
        $this->assertEquals($checker->id, $certified['checker_id']);

        // Same user checking should fail
        $this->expectException(\InvalidArgumentException::class);
        $service->certifyCheckerNomination($draft, $maker);
    }
}
