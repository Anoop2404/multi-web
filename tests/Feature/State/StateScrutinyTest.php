<?php

namespace Tests\Feature\State;

use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\PlatformState;
use App\Models\PlatformUser;
use App\Models\State\StateEntryReview;
use App\Models\State\StateFestEvent;
use App\Models\State\StateQualifierEntry;
use App\Models\State\StateQualifierIntake;
use App\Models\State\StateSahodaya;
use App\Services\State\Fest\StateEventSettings;
use App\Services\State\Fest\StateScrutinyService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 4 of the State Kalotsav module — scrutiny.
 *
 * The outcome that matters most here is "returned": before it, a scrutineer had to reject a whole
 * package over one missing date of birth, and a rejected intake is closed, so the Sahodaya could
 * not fix it.
 */
class StateScrutinyTest extends TestCase
{
    use RefreshDatabase;

    private PlatformState $state;

    private StateFestEvent $event;

    private FestStateProgram $program;

    private StateSahodaya $sahodaya;

    private FestStateProgramItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Artisan::call('state:migrate');

        $this->state = PlatformState::create(['code' => 'KL', 'name' => 'Kerala', 'is_active' => true]);
        $this->program = FestStateProgram::create([
            'title' => 'Kerala State Kalotsavam 2026', 'state_id' => $this->state->id,
            'event_type' => 'kalolsavam', 'conduct_levels' => ['state'], 'status' => 'published',
        ]);
        $this->event = StateFestEvent::create([
            'state_program_id' => $this->program->id, 'state_id' => $this->state->id,
            'name' => 'State Finals', 'slug' => 'finals', 'status' => 'active',
        ]);
        $this->item = FestStateProgramItem::create([
            'state_program_id' => $this->program->id, 'title' => 'Light Music',
            'item_code' => 'LM01', 'qualify_count' => 2,
        ]);
        $this->sahodaya = StateSahodaya::create([
            'id' => (string) Str::uuid(), 'state_id' => $this->state->id, 'name' => 'Malappuram Sahodaya',
            'tenant_id' => (string) Str::uuid(), 'origin' => StateSahodaya::ORIGIN_MANAGED,
        ]);
    }

    private function intake(): StateQualifierIntake
    {
        return StateQualifierIntake::firstOrCreate(
            ['state_program_id' => $this->program->id, 'sahodaya_id' => $this->sahodaya->id],
            [
                'state_id' => $this->state->id, 'source_tenant_id' => $this->sahodaya->tenant_id,
                'sahodaya_name' => $this->sahodaya->name, 'source_event_id' => 1,
                'idempotency_key' => 'k:'.Str::random(8), 'status' => 'received', 'payload' => [],
            ],
        );
    }

    private function entry(string $name, array $attrs = []): StateQualifierEntry
    {
        return StateQualifierEntry::create(array_merge([
            'intake_id' => $this->intake()->id, 'school_id' => 'sjh', 'school_name' => 'St Joseph HSS',
            'item_id' => $this->item->id, 'item_code' => 'LM01', 'student_name' => $name, 'status' => 'pending',
        ], $attrs));
    }

    private function scrutiny(): StateScrutinyService
    {
        return app(StateScrutinyService::class);
    }

    private function user(string $role = 'state_admin'): PlatformUser
    {
        $user = PlatformUser::create([
            'name' => 'Registrar', 'email' => $role.'@example.test', 'username' => $role,
            'password' => 'password', 'state_id' => $this->state->id, 'email_verified_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_an_entry_can_be_returned_for_correction_rather_than_rejected(): void
    {
        $entry = $this->entry('Athira Menon');

        $returned = $this->scrutiny()->decide($entry, 'returned', ['note' => 'Date of birth missing.']);

        $this->assertSame('returned', $returned->status);
        $this->assertSame('Date of birth missing.', $returned->review_note);
        // The intake stays open, which is the whole point — a rejected intake cannot be corrected.
        $this->assertSame('received', $this->intake()->fresh()->status);
    }

    public function test_returning_without_saying_what_is_wrong_is_refused(): void
    {
        // The Sahodaya reads this note; without it they have nothing to act on.
        $this->expectException(ValidationException::class);

        $this->scrutiny()->decide($this->entry('Athira Menon'), 'returned', []);
    }

    public function test_requesting_documents_holds_the_entry_without_judging_it(): void
    {
        $entry = $this->scrutiny()->decide($this->entry('Athira'), 'documents_requested', ['note' => 'Send the birth certificate.']);

        $this->assertSame('documents_requested', $entry->status);
        $this->assertContains($entry->status, StateScrutinyService::OPEN_DECISIONS);
    }

    public function test_every_decision_is_recorded_with_who_and_why(): void
    {
        $entry = $this->entry('Athira');

        $this->scrutiny()->decide($entry, 'returned', ['note' => 'Fix the class', 'user_id' => 7, 'user_name' => 'Registrar']);
        $this->scrutiny()->decide($entry->fresh(), 'approved', ['user_id' => 7, 'user_name' => 'Registrar']);

        $log = StateEntryReview::orderBy('id')->get();
        $this->assertCount(2, $log);
        $this->assertSame('returned', $log[0]->decision);
        $this->assertSame('pending', $log[0]->previous_status);
        $this->assertSame('Registrar', $log[0]->decided_by_name);
        // The history survives the status being overwritten, which a status column alone cannot do.
        $this->assertSame('approved', $log[1]->decision);
        $this->assertSame('returned', $log[1]->previous_status);
    }

    public function test_approval_is_still_held_to_the_sahodayas_slots(): void
    {
        $this->scrutiny()->decide($this->entry('One'), 'approved');
        $this->scrutiny()->decide($this->entry('Two'), 'approved');

        $this->expectException(ValidationException::class);
        $this->scrutiny()->decide($this->entry('Three'), 'approved');
    }

    public function test_a_reserve_replaces_an_entry_as_two_recorded_decisions(): void
    {
        $original = $this->scrutiny()->decide($this->entry('Injured Student'), 'approved');
        $reserve = $this->entry('Standby Student', ['is_reserve' => true]);

        $accepted = $this->scrutiny()->acceptReserve($original->fresh(), $reserve, ['user_name' => 'Registrar']);

        $this->assertSame('approved', $accepted->status);
        $this->assertSame('rejected', $original->fresh()->status);
        // Two decisions, so the log reads as what happened rather than an unexplained swap.
        $this->assertSame(3, StateEntryReview::count());
    }

    public function test_a_reserve_for_a_different_item_is_refused(): void
    {
        $other = FestStateProgramItem::create([
            'state_program_id' => $this->program->id, 'title' => 'Folk Dance', 'item_code' => 'FD01', 'qualify_count' => 2,
        ]);
        $original = $this->entry('Original');
        $reserve = $this->entry('Wrong Item Reserve', ['item_id' => $other->id, 'item_code' => 'FD01']);

        $this->expectException(ValidationException::class);
        $this->scrutiny()->acceptReserve($original, $reserve);
    }

    public function test_an_intake_with_open_entries_cannot_be_finalised(): void
    {
        $this->scrutiny()->decide($this->entry('Returned One'), 'returned', ['note' => 'Fix it']);

        // Finalising would strand it: a finalised intake cannot be edited.
        $this->expectException(ValidationException::class);
        $this->scrutiny()->finalise($this->intake());
    }

    public function test_finalising_approves_the_intake_when_any_entry_was_approved(): void
    {
        $this->scrutiny()->decide($this->entry('Yes'), 'approved');
        $this->scrutiny()->decide($this->entry('No'), 'rejected', ['note' => 'Ineligible']);

        $intake = $this->scrutiny()->finalise($this->intake());

        $this->assertSame('approved', $intake->status);
    }

    public function test_finalising_rejects_the_intake_when_nothing_was_approved(): void
    {
        $this->scrutiny()->decide($this->entry('No'), 'rejected', ['note' => 'Ineligible']);

        $this->assertSame('rejected', $this->scrutiny()->finalise($this->intake())->status);
    }

    public function test_a_finalised_intake_refuses_further_decisions_until_reopened(): void
    {
        $entry = $this->entry('One');
        $this->scrutiny()->decide($entry, 'approved');
        $this->scrutiny()->finalise($this->intake());

        try {
            $this->scrutiny()->decide($this->entry('Late Arrival'), 'approved');
            $this->fail('A finalised intake must refuse new decisions.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('already finalised', collect($e->errors())->flatten()->first());
        }

        $this->scrutiny()->reopen($this->intake()->fresh(), ['user_name' => 'Registrar']);
        $this->assertSame('received', $this->intake()->fresh()->status);
    }

    public function test_decisions_are_refused_once_the_scrutiny_window_has_closed(): void
    {
        // The window built in Phase 3 actually gates the workflow rather than documenting a deadline.
        app(StateEventSettings::class)->update($this->event, ['scrutiny_closes_at' => now()->subDay()->toDateTimeString()]);

        try {
            $this->scrutiny()->decide($this->entry('Athira'), 'approved', ['event' => $this->event->fresh()]);
            $this->fail('A closed scrutiny window must refuse decisions.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Scrutiny closed on', collect($e->errors())->flatten()->first());
        }
    }

    public function test_a_batch_continues_past_an_entry_that_breaches_the_quota(): void
    {
        // One entry over the allowance must not lose the scrutineer the rest of the work.
        $a = $this->entry('One');
        $b = $this->entry('Two');
        $c = $this->entry('Three');

        $this->actingAs($this->user(), 'platform')
            ->post("http://superadmin.test/admin/state/fest/{$this->event->id}/scrutiny/{$this->intake()->id}/decide", [
                'entry_ids' => [$a->id, $b->id, $c->id],
                'decision' => 'approved',
            ])->assertRedirect();

        $this->assertSame(2, StateQualifierEntry::where('status', 'approved')->count());
        $this->assertSame('pending', $c->fresh()->status, 'The one over the allowance stays pending.');
    }

    public function test_scrutiny_screens_are_gated_by_capability(): void
    {
        $url = "http://superadmin.test/admin/state/fest/{$this->event->id}";

        // A mark operator may open the workspace but not the scrutiny queue.
        $this->actingAs($this->user('state_mark_operator'), 'platform')->get("{$url}/submissions")->assertForbidden();

        // A scrutiny officer may do both.
        $officer = PlatformUser::create([
            'name' => 'Officer', 'email' => 'officer@example.test', 'username' => 'officer',
            'password' => 'password', 'state_id' => $this->state->id, 'email_verified_at' => now(),
        ]);
        $officer->assignRole('state_scrutiny_officer');

        $this->actingAs($officer, 'platform')->get("{$url}/submissions")->assertOk();
        $this->actingAs($officer, 'platform')->get("{$url}/registrations")->assertOk();
    }

    public function test_every_scrutiny_row_carries_both_the_sahodaya_and_the_school(): void
    {
        $this->entry('Athira Menon');

        $page = $this->actingAs($this->user(), 'platform')
            ->get("http://superadmin.test/admin/state/fest/{$this->event->id}/scrutiny/{$this->intake()->id}")
            ->viewData('page');

        $this->assertSame('Malappuram Sahodaya', $page['props']['intake']['sahodaya']);
        $this->assertSame('St Joseph HSS', $page['props']['entries'][0]['school_name']);
        // The allowance is shown beside the decision so a scrutineer is not approving blind.
        $this->assertSame(2, $page['props']['entries'][0]['slots']);
    }
}
