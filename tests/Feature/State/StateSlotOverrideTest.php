<?php

namespace Tests\Feature\State;

use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\PlatformState;
use App\Models\State\StateQualifierEntry;
use App\Models\State\StateQualifierIntake;
use App\Models\State\StateSahodaya;
use App\Models\State\StateSlotAuditEntry;
use App\Services\State\Fest\StateSlotService;
use App\Services\State\StateParticipationLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 3 of the State Kalotsav module — per-Sahodaya slot overrides.
 *
 * Slots resolve in three levels, most specific first: a Sahodaya's override for that item, the
 * item's own figure, then the item default. All three are per Sahodaya.
 */
class StateSlotOverrideTest extends TestCase
{
    use RefreshDatabase;

    private FestStateProgram $program;

    private FestStateProgramItem $item;

    private StateSahodaya $sahodayaA;

    private StateSahodaya $sahodayaB;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('state:migrate');

        $state = PlatformState::create(['code' => 'KL', 'name' => 'Kerala', 'is_active' => true]);
        $this->program = FestStateProgram::create([
            'title' => 'Kerala State Kalotsavam 2026', 'state_id' => $state->id,
            'event_type' => 'kalolsavam', 'conduct_levels' => ['state'], 'status' => 'published',
        ]);
        $this->item = FestStateProgramItem::create([
            'state_program_id' => $this->program->id, 'title' => 'Light Music', 'item_code' => 'LM01',
            'qualify_count' => 2,
        ]);

        $this->sahodayaA = $this->sahodaya('Malappuram Sahodaya');
        $this->sahodayaB = $this->sahodaya('Kasaragod Sahodaya');
    }

    private function sahodaya(string $name): StateSahodaya
    {
        return StateSahodaya::create([
            'id' => (string) Str::uuid(), 'state_id' => $this->program->state_id, 'name' => $name,
            'tenant_id' => (string) Str::uuid(), 'origin' => StateSahodaya::ORIGIN_MANAGED,
        ]);
    }

    private function slots(): StateSlotService
    {
        return app(StateSlotService::class);
    }

    private function approvedEntry(StateSahodaya $sahodaya, string $student, string $status = 'approved'): StateQualifierEntry
    {
        $intake = StateQualifierIntake::firstOrCreate(
            ['state_program_id' => $this->program->id, 'sahodaya_id' => $sahodaya->id],
            [
                'state_id' => $this->program->state_id, 'source_tenant_id' => $sahodaya->tenant_id,
                'sahodaya_name' => $sahodaya->name, 'source_event_id' => 1,
                'idempotency_key' => 'k:'.Str::random(8), 'status' => 'received', 'payload' => [],
            ],
        );

        return StateQualifierEntry::create([
            'intake_id' => $intake->id, 'school_id' => 'sch-1', 'school_name' => 'St Joseph HSS',
            'item_id' => $this->item->id, 'item_code' => 'LM01', 'student_name' => $student, 'status' => $status,
        ]);
    }

    public function test_slots_fall_back_through_the_three_levels(): void
    {
        // 3. item default
        $this->assertSame(2, $this->slots()->slotsFor($this->item, $this->sahodayaA->id));

        // 2. item override
        $this->item->forceFill(['max_per_school' => 1])->save();
        $this->assertSame(1, $this->slots()->slotsFor($this->item->fresh(), $this->sahodayaA->id));

        // 1. Sahodaya override — most specific wins
        $this->slots()->setSahodayaSlots($this->item->fresh(), $this->sahodayaA->id, 4, 'Host Sahodaya', null);
        $this->assertSame(4, $this->slots()->slotsFor($this->item->fresh(), $this->sahodayaA->id));

        // and applies only to that Sahodaya
        $this->assertSame(1, $this->slots()->slotsFor($this->item->fresh(), $this->sahodayaB->id));
    }

    public function test_clearing_an_override_returns_the_sahodaya_to_the_item_figure(): void
    {
        $this->slots()->setSahodayaSlots($this->item, $this->sahodayaA->id, 5, 'Temporary', null);
        $this->assertSame(5, $this->slots()->slotsFor($this->item->fresh(), $this->sahodayaA->id));

        $this->slots()->setSahodayaSlots($this->item->fresh(), $this->sahodayaA->id, null, 'Reverted', null);

        $this->assertSame(2, $this->slots()->slotsFor($this->item->fresh(), $this->sahodayaA->id));
    }

    public function test_an_override_changes_what_approval_allows(): void
    {
        $limits = app(StateParticipationLimitService::class);

        $this->approvedEntry($this->sahodayaA, 'One');
        $this->approvedEntry($this->sahodayaA, 'Two');
        $third = $this->approvedEntry($this->sahodayaA, 'Three', 'pending');

        // At the item default of 2, the third is refused.
        $this->assertNotEmpty($limits->validateEntryApproval($third));

        // Granted a third slot, it is allowed — and the Sahodaya next door is unaffected.
        $this->slots()->setSahodayaSlots($this->item, $this->sahodayaA->id, 3, 'Appeal upheld', null);

        $this->assertSame([], $limits->validateEntryApproval($third->fresh()));

        $otherThird = $this->approvedEntry($this->sahodayaB, 'B-One');
        $this->approvedEntry($this->sahodayaB, 'B-Two');
        $bThird = $this->approvedEntry($this->sahodayaB, 'B-Three', 'pending');
        $this->assertNotEmpty($limits->validateEntryApproval($bThird), 'The other Sahodaya keeps the item figure.');
    }

    public function test_every_change_is_recorded_with_who_what_and_why(): void
    {
        $this->slots()->setSahodayaSlots($this->item, $this->sahodayaA->id, 4, 'Appeal 12 upheld', 99, 'Registrar');
        $this->slots()->setItemSlots($this->item->fresh(), 1, 'Reduced by circular', 99, 'Registrar');

        $entries = StateSlotAuditEntry::orderBy('id')->get();
        $this->assertCount(2, $entries);

        $this->assertSame('sahodaya', $entries[0]->scope);
        $this->assertSame(2, $entries[0]->slots_from);
        $this->assertSame(4, $entries[0]->slots_to);
        $this->assertSame('Appeal 12 upheld', $entries[0]->reason);
        $this->assertSame('Registrar', $entries[0]->changed_by_name);

        $this->assertSame('item', $entries[1]->scope);
        $this->assertNull($entries[1]->sahodaya_id, 'An item-level change applies to every Sahodaya.');
        $this->assertSame(1, $entries[1]->slots_to);
    }

    public function test_a_team_entry_consumes_one_slot_not_one_per_member(): void
    {
        // The slot belongs to the entry, so a group item with a 2-slot allowance admits two teams,
        // however many members each has.
        $team = FestStateProgramItem::create([
            'state_program_id' => $this->program->id, 'title' => 'Group Dance', 'item_code' => 'GD01',
            'qualify_count' => 2, 'participant_type' => 'group',
        ]);

        $matrix = $this->slots()->matrix($this->program);
        $row = collect($matrix['items'])->firstWhere('item_id', $team->id);

        $this->assertTrue($row['is_team']);
        $this->assertSame(2, $row['default_slots']);
    }

    public function test_the_matrix_reports_used_available_and_exceeded_per_sahodaya(): void
    {
        $this->approvedEntry($this->sahodayaA, 'One');
        $this->approvedEntry($this->sahodayaA, 'Two');
        $this->approvedEntry($this->sahodayaA, 'Three'); // over the 2-slot allowance

        $row = collect($this->slots()->matrix($this->program)['items'])->firstWhere('item_id', $this->item->id);
        $cell = collect($row['cells'])->firstWhere('sahodaya_id', $this->sahodayaA->id);

        $this->assertSame(2, $cell['slots']);
        $this->assertSame(3, $cell['used']);
        $this->assertSame(0, $cell['available']);
        $this->assertTrue($cell['exceeded']);
        $this->assertSame(1, $row['exceeded']);
    }

    public function test_a_promoted_sahodaya_gets_one_allowance_not_two(): void
    {
        // Its entries arrived under two different raw submission keys. Counting those separately
        // would hand it a second full allowance for every item.
        $promoted = StateSahodaya::create([
            'id' => (string) Str::uuid(), 'state_id' => $this->program->state_id, 'name' => 'Promoted Sahodaya',
            'tenant_id' => (string) Str::uuid(), 'external_sahodaya_id' => (string) Str::uuid(),
            'origin' => StateSahodaya::ORIGIN_EXTERNAL,
        ]);

        $before = StateQualifierIntake::create([
            'state_program_id' => $this->program->id, 'state_id' => $this->program->state_id,
            'sahodaya_id' => $promoted->id, 'source_tenant_id' => "external:{$promoted->external_sahodaya_id}",
            'source_event_id' => 1, 'idempotency_key' => 'k:before', 'status' => 'received', 'payload' => [],
        ]);
        $after = StateQualifierIntake::create([
            'state_program_id' => $this->program->id, 'state_id' => $this->program->state_id,
            'sahodaya_id' => $promoted->id, 'source_tenant_id' => $promoted->tenant_id,
            'source_event_id' => 1, 'idempotency_key' => 'k:after', 'status' => 'received', 'payload' => [],
        ]);

        foreach ([[$before, 'Old One'], [$before, 'Old Two'], [$after, 'New Three']] as [$intake, $name]) {
            StateQualifierEntry::create([
                'intake_id' => $intake->id, 'school_id' => 's', 'item_id' => $this->item->id,
                'item_code' => 'LM01', 'student_name' => $name,
                'status' => $name === 'New Three' ? 'pending' : 'approved',
            ]);
        }

        $third = StateQualifierEntry::where('student_name', 'New Three')->sole();

        $this->assertNotEmpty(
            app(StateParticipationLimitService::class)->validateEntryApproval($third),
            'The two entries submitted before promotion must still count against its allowance.',
        );
    }
}
