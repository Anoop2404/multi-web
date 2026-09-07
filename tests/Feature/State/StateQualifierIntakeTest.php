<?php

namespace Tests\Feature\State;

use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateQualifierIntake;
use App\Services\State\StateQualifierIntakeService;
use App\Services\State\StateQualifierMaterializationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
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
            'event_type'     => 'kalolsavam',
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

        $service = app(StateQualifierIntakeService::class);
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
        $this->assertEquals('tenant-sahodaya-1', $stateReg->sahodaya_id);
    }

    public function test_idempotency_key_cannot_be_reused_for_changed_payload_or_tenant(): void
    {
        $program = FestStateProgram::create([
            'title' => 'State Program',
            'event_type' => 'kalolsavam',
            'conduct_levels' => ['state'],
            'status' => 'published',
        ]);
        $payload = [
            'state_program_id' => $program->id,
            'source_event_id' => 1,
            'entries' => [[
                'school_id' => 'school-1',
                'student_name' => 'Original Student',
            ]],
        ];

        $service = app(StateQualifierIntakeService::class);
        $service->receive('stable-key', $payload, 'tenant-1');

        $this->expectException(ValidationException::class);
        $service->receive('stable-key', array_replace_recursive($payload, [
            'entries' => [['student_name' => 'Changed Student']],
        ]), 'tenant-2');
    }

    public function test_finalizing_an_intake_with_every_entry_rejected_does_not_create_state_registrations(): void
    {
        $program = FestStateProgram::create([
            'title' => 'Rejected State Intake',
            'event_type' => 'kalolsavam',
            'conduct_levels' => ['state'],
            'status' => 'published',
        ]);
        $service = app(StateQualifierIntakeService::class);
        $intake = $service->receive('all-rejected-key', [
            'state_program_id' => $program->id,
            'source_event_id' => 99,
            'entries' => [[
                'school_id' => 'school-rejected',
                'student_name' => 'Rejected Student',
            ]],
        ], 'tenant-rejected');
        $entry = $intake->entries()->sole();

        $service->reviewEntry($intake, $entry, 'rejected');
        $finalized = $service->approve($intake);

        $this->assertSame('rejected', $finalized->status);
        $this->assertSame(0, StateFestRegistration::whereHas(
            'stateEvent',
            fn ($query) => $query->where('state_program_id', $program->id),
        )->count());
        $this->assertFalse(StateFestEvent::where('state_program_id', $program->id)->exists());
    }

    public function test_review_entry_blocks_when_max_per_school_would_be_exceeded(): void
    {
        $program = FestStateProgram::create([
            'title' => 'Limit Test Program', 'event_type' => 'kalolsavam',
            'conduct_levels' => ['state'], 'status' => 'published',
        ]);
        $item = FestStateProgramItem::create([
            'state_program_id' => $program->id, 'title' => 'Group Song', 'item_code' => 'GS01',
            'max_per_school' => 1,
        ]);

        $service = app(StateQualifierIntakeService::class);
        $intake = $service->receive('max-per-school-key', [
            'state_program_id' => $program->id,
            'source_event_id' => 1,
            'entries' => [
                ['school_id' => 'sch-1', 'student_name' => 'A', 'item_id' => $item->id, 'item_code' => $item->item_code],
                ['school_id' => 'sch-1', 'student_name' => 'B', 'item_id' => $item->id, 'item_code' => $item->item_code],
            ],
        ], 'tenant-x');

        [$first, $second] = $intake->entries()->orderBy('id')->get();

        $service->reviewEntry($intake, $first, 'approved');
        $this->assertSame('approved', $first->fresh()->status);

        try {
            $service->reviewEntry($intake, $second, 'approved');
            $this->fail('Expected a limit-breach exception.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString('at most 1', $e->getMessage());
        }

        $this->assertSame('pending', $second->fresh()->status);
    }

    public function test_review_entry_blocks_when_qualify_count_would_be_exceeded_globally(): void
    {
        $program = FestStateProgram::create([
            'title' => 'Global Limit Test Program', 'event_type' => 'kalolsavam',
            'conduct_levels' => ['state'], 'status' => 'published',
        ]);
        $item = FestStateProgramItem::create([
            'state_program_id' => $program->id, 'title' => 'Solo Dance', 'item_code' => 'SD01',
            'qualify_count' => 1,
        ]);

        $service = app(StateQualifierIntakeService::class);
        $intakeA = $service->receive('qualify-count-key-a', [
            'state_program_id' => $program->id, 'source_event_id' => 1,
            'entries' => [['school_id' => 'sch-a', 'student_name' => 'A', 'item_id' => $item->id, 'item_code' => $item->item_code]],
        ], 'tenant-a');
        $intakeB = $service->receive('qualify-count-key-b', [
            'state_program_id' => $program->id, 'source_event_id' => 1,
            'entries' => [['school_id' => 'sch-b', 'student_name' => 'B', 'item_id' => $item->id, 'item_code' => $item->item_code]],
        ], 'tenant-b');

        $service->reviewEntry($intakeA, $intakeA->entries()->sole(), 'approved');

        try {
            $service->reviewEntry($intakeB, $intakeB->entries()->sole(), 'approved');
            $this->fail('Expected a global qualify_count breach exception.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString('state-wide', $e->getMessage());
        }

        $this->assertSame('pending', $intakeB->entries()->sole()->fresh()->status);
    }

    public function test_bulk_approve_rejects_whole_intake_when_any_pending_entry_would_breach_a_limit(): void
    {
        $program = FestStateProgram::create([
            'title' => 'Bulk Limit Test Program', 'event_type' => 'kalolsavam',
            'conduct_levels' => ['state'], 'status' => 'published',
        ]);
        $limited = FestStateProgramItem::create([
            'state_program_id' => $program->id, 'title' => 'Group Dance', 'item_code' => 'GD01',
            'max_per_school' => 1,
        ]);
        $unlimited = FestStateProgramItem::create([
            'state_program_id' => $program->id, 'title' => 'Recitation', 'item_code' => 'RC01',
        ]);

        $service = app(StateQualifierIntakeService::class);
        $intake = $service->receive('bulk-limit-key', [
            'state_program_id' => $program->id, 'source_event_id' => 1,
            'entries' => [
                ['school_id' => 'sch-1', 'student_name' => 'A', 'item_id' => $limited->id, 'item_code' => $limited->item_code],
                ['school_id' => 'sch-1', 'student_name' => 'B', 'item_id' => $limited->id, 'item_code' => $limited->item_code],
                ['school_id' => 'sch-1', 'student_name' => 'C', 'item_id' => $unlimited->id, 'item_code' => $unlimited->item_code],
            ],
        ], 'tenant-bulk');

        try {
            $service->approve($intake);
            $this->fail('Expected the whole intake approval to be blocked.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertSame('received', $intake->fresh()->status);
        $this->assertTrue($intake->entries()->where('status', 'pending')->count() === 3);
    }
}
