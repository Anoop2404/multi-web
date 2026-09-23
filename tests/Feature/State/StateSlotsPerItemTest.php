<?php

namespace Tests\Feature\State;

use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\State\StateQualifierEntry;
use App\Models\State\StateQualifierIntake;
use App\Services\State\StateParticipationLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * "Each Sahodaya gets two slots per item" — the actual State Kalotsavam rule, and the one every
 * seeded item already encodes as qualify_count = 2 (140 of 140 items on the Kerala 2026 program).
 *
 * The cap has to mean the same thing everywhere it is applied, because three different places
 * apply it: FestStateQualifierPayloadBuilder when a managed Sahodaya nominates, ExternalIntakeService
 * when an outside Sahodaya types entries, and StateParticipationLimitService when the State approves.
 */
class StateSlotsPerItemTest extends TestCase
{
    use RefreshDatabase;

    private FestStateProgram $program;

    private FestStateProgramItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('state:migrate');

        $this->program = FestStateProgram::create([
            'title'          => 'Kerala State Kalotsavam 2026',
            'event_type'     => 'kalolsavam',
            'conduct_levels' => ['sahodaya', 'state'],
            'status'         => 'published',
        ]);

        $this->item = FestStateProgramItem::create([
            'state_program_id' => $this->program->id,
            'title'            => 'Light Music-Malayalam',
            'item_code'        => 'LM01',
            'qualify_count'    => 2,
        ]);
    }

    private function entryFor(string $sahodayaTenantId, string $student): StateQualifierEntry
    {
        $intake = StateQualifierIntake::firstOrCreate(
            ['state_program_id' => $this->program->id, 'source_tenant_id' => $sahodayaTenantId],
            ['status' => 'pending', 'idempotency_key' => 'k:'.$sahodayaTenantId, 'source_event_id' => 1, 'payload' => []],
        );

        return StateQualifierEntry::create([
            'intake_id'    => $intake->id,
            'school_id'    => 'school-of-'.$sahodayaTenantId,
            'item_id'      => $this->item->id,
            'item_code'    => 'LM01',
            'student_name' => $student,
            'status'       => 'pending',
        ]);
    }

    private function approve(StateQualifierEntry $entry): array
    {
        $errors = app(StateParticipationLimitService::class)->validateEntryApproval($entry);

        if ($errors === []) {
            $entry->forceFill(['status' => 'approved'])->save();
        }

        return $errors;
    }

    public function test_each_sahodaya_gets_its_own_two_slots_for_an_item(): void
    {
        // Sahodaya A fills both of its slots.
        $this->assertSame([], $this->approve($this->entryFor('sahodaya-a', 'Athira')));
        $this->assertSame([], $this->approve($this->entryFor('sahodaya-a', 'Rahul')));

        // Sahodaya B must still have two slots of its own — the cap is per Sahodaya, not a
        // state-wide pool. With 19+ Sahodayas submitting, a global reading approves the first two
        // entries in all of Kerala and refuses everybody else.
        $this->assertSame([], $this->approve($this->entryFor('sahodaya-b', 'Meera')),
            'Sahodaya B must not be blocked by Sahodaya A having used its slots.');
        $this->assertSame([], $this->approve($this->entryFor('sahodaya-c', 'Nikhil')));
    }

    public function test_a_third_entry_from_the_same_sahodaya_is_refused(): void
    {
        $this->approve($this->entryFor('sahodaya-a', 'Athira'));
        $this->approve($this->entryFor('sahodaya-a', 'Rahul'));

        $errors = $this->approve($this->entryFor('sahodaya-a', 'Third Wheel'));

        $this->assertNotEmpty($errors, 'The third entry from one Sahodaya must be refused.');
        $this->assertSame('pending', StateQualifierEntry::where('student_name', 'Third Wheel')->value('status'));
    }

    public function test_an_explicit_max_per_sahodaya_overrides_the_qualify_count(): void
    {
        // Some items qualify fewer — English One Act Play is top-1.
        $this->item->forceFill(['max_per_school' => 1])->save();

        $this->assertSame([], $this->approve($this->entryFor('sahodaya-a', 'Athira')));
        $this->assertNotEmpty($this->approve($this->entryFor('sahodaya-a', 'Rahul')));

        // Still its own slot for a different Sahodaya.
        $this->assertSame([], $this->approve($this->entryFor('sahodaya-b', 'Meera')));
    }

    public function test_bulk_intake_approval_counts_pending_entries_against_each_other(): void
    {
        $this->entryFor('sahodaya-a', 'One');
        $this->entryFor('sahodaya-a', 'Two');
        $this->entryFor('sahodaya-a', 'Three');

        $intake = StateQualifierIntake::where('source_tenant_id', 'sahodaya-a')->first();
        $errors = app(StateParticipationLimitService::class)->validateBulkApproval($intake);

        $this->assertNotEmpty($errors, 'Three pending entries for one item must not all pass as a batch.');
    }
}
