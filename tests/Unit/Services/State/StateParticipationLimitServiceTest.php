<?php

namespace Tests\Unit\Services\State;

use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\State\StateQualifierEntry;
use App\Models\State\StateQualifierIntake;
use App\Services\State\StateParticipationLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class StateParticipationLimitServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('state:migrate');
    }

    private function intake(FestStateProgram $program, string $sahodayaId, string $key): StateQualifierIntake
    {
        return StateQualifierIntake::create([
            'state_program_id' => $program->id, 'source_tenant_id' => $sahodayaId, 'source_event_id' => 1,
            'idempotency_key' => $key, 'status' => 'received', 'payload' => [],
        ]);
    }

    private function entry(StateQualifierIntake $intake, string $itemId, string $status = 'pending'): StateQualifierEntry
    {
        return StateQualifierEntry::create([
            'intake_id' => $intake->id, 'school_id' => 'sch-'.uniqid(), 'item_id' => $itemId,
            'student_name' => 'Student '.uniqid(), 'status' => $status,
        ]);
    }

    public function test_sahodaya_and_global_approved_counts_only_count_approved_entries_for_the_item(): void
    {
        $program = FestStateProgram::create(['title' => 'P', 'event_type' => 'kalolsavam', 'conduct_levels' => ['state'], 'status' => 'published']);
        $item = FestStateProgramItem::create(['state_program_id' => $program->id, 'title' => 'Item A']);
        $otherItem = FestStateProgramItem::create(['state_program_id' => $program->id, 'title' => 'Item B']);

        $intakeA = $this->intake($program, 'tenant-a', 'k1');
        $intakeB = $this->intake($program, 'tenant-b', 'k2');
        $this->entry($intakeA, $item->id, 'approved');
        $this->entry($intakeA, $item->id, 'pending');
        $this->entry($intakeA, $otherItem->id, 'approved');
        $this->entry($intakeB, $item->id, 'approved');

        $service = new StateParticipationLimitService();

        $this->assertSame(1, $service->sahodayaApprovedCount($item->id, 'tenant-a'));
        $this->assertSame(2, $service->globalApprovedCount($item->id));
    }

    public function test_entries_approved_via_review_entry_in_a_still_open_intake_count_against_a_second_intakes_global_cap(): void
    {
        // Regression coverage for the specific race this service exists to close: an
        // entry flipped to approved via reviewEntry() is not yet materialized into a
        // StateFestRegistration (that only happens when the whole intake is finalized),
        // so counting must happen at the StateQualifierEntry level, not the
        // registration level, or a second still-open intake would see false headroom.
        $program = FestStateProgram::create(['title' => 'P', 'event_type' => 'kalolsavam', 'conduct_levels' => ['state'], 'status' => 'published']);
        $item = FestStateProgramItem::create(['state_program_id' => $program->id, 'title' => 'Solo', 'qualify_count' => 1]);

        $intakeA = $this->intake($program, 'tenant-a', 'k1');
        $entryA = $this->entry($intakeA, $item->id, 'approved'); // approved pre-finalization, intake still "received"
        $intakeB = $this->intake($program, 'tenant-b', 'k2');
        $entryB = $this->entry($intakeB, $item->id, 'pending');

        $service = new StateParticipationLimitService();

        $this->assertSame('received', $intakeA->status);
        $violations = $service->validateEntryApproval($entryB);
        $this->assertNotEmpty($violations);
        $this->assertStringContainsString('state-wide', $violations[0]);
    }

    public function test_validate_bulk_approval_counts_pending_entries_for_the_same_item_against_each_other(): void
    {
        $program = FestStateProgram::create(['title' => 'P', 'event_type' => 'kalolsavam', 'conduct_levels' => ['state'], 'status' => 'published']);
        $item = FestStateProgramItem::create(['state_program_id' => $program->id, 'title' => 'Duet', 'max_per_school' => 1]);

        $intake = $this->intake($program, 'tenant-a', 'k1');
        $this->entry($intake, $item->id, 'pending');
        $this->entry($intake, $item->id, 'pending');

        $service = new StateParticipationLimitService();
        $violations = $service->validateBulkApproval($intake);

        $this->assertNotEmpty($violations);
        $this->assertStringContainsString('at most 1 per Sahodaya', $violations[0]);
    }

    public function test_validate_bulk_approval_allows_a_batch_within_limits(): void
    {
        $program = FestStateProgram::create(['title' => 'P', 'event_type' => 'kalolsavam', 'conduct_levels' => ['state'], 'status' => 'published']);
        $item = FestStateProgramItem::create(['state_program_id' => $program->id, 'title' => 'Trio', 'max_per_school' => 2]);

        $intake = $this->intake($program, 'tenant-a', 'k1');
        $this->entry($intake, $item->id, 'pending');
        $this->entry($intake, $item->id, 'pending');

        $service = new StateParticipationLimitService();

        $this->assertSame([], $service->validateBulkApproval($intake));
    }

    public function test_items_with_no_limits_configured_never_produce_violations(): void
    {
        $program = FestStateProgram::create(['title' => 'P', 'event_type' => 'kalolsavam', 'conduct_levels' => ['state'], 'status' => 'published']);
        $item = FestStateProgramItem::create(['state_program_id' => $program->id, 'title' => 'Unlimited Item']);

        $intake = $this->intake($program, 'tenant-a', 'k1');
        $entry = $this->entry($intake, $item->id, 'pending');
        $this->entry($intake, $item->id, 'approved');
        $this->entry($intake, $item->id, 'approved');

        $service = new StateParticipationLimitService();

        $this->assertSame([], $service->validateEntryApproval($entry));
        $this->assertSame([], $service->validateBulkApproval($intake));
    }
}
