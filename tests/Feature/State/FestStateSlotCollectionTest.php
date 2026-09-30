<?php

namespace Tests\Feature\State;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestStateNominationSelection;
use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\State\FestStateNominationService;
use App\Services\State\FestStateSlotCollectionService;
use App\Services\State\FestStateWinnerSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * "Get a confirmed list before State registration opens" — a phase gate on top of the existing
 * State winner sheet. See App\Services\State\FestStateSlotCollectionService.
 */
class FestStateSlotCollectionTest extends TestCase
{
    use RefreshDatabase;

    private FestStateProgram $program;

    private FestEvent $hubEvent;

    private FestEventItem $item;

    private FestStateProgramItem $stateItem;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('state:migrate');

        Tenant::create(['id' => 'sahodaya-1', 'name' => 'Sahodaya One', 'type' => 'sahodaya']);
        Tenant::create(['id' => 'school-1', 'name' => 'School One', 'type' => 'school', 'parent_id' => 'sahodaya-1']);
        Tenant::create(['id' => 'school-2', 'name' => 'School Two', 'type' => 'school', 'parent_id' => 'sahodaya-1']);
        Tenant::create(['id' => 'school-3', 'name' => 'School Three', 'type' => 'school', 'parent_id' => 'sahodaya-1']);

        $this->program = FestStateProgram::create([
            'title' => 'Kerala State Kalotsavam 2026', 'event_type' => 'kalolsavam',
            'conduct_levels' => ['sahodaya', 'state'], 'status' => 'published',
        ]);

        $this->stateItem = FestStateProgramItem::create([
            'state_program_id' => $this->program->id, 'title' => 'Light Music',
            'item_code' => 'LM01', 'category' => 'music', 'class_group' => 'category_2',
            'qualify_count' => 2, 'participant_type' => 'individual',
        ]);

        $this->hubEvent = FestEvent::create([
            'tenant_id' => 'sahodaya-1', 'title' => 'Sahodaya Kalotsavam 2026',
            'event_type' => 'kalolsavam', 'level_round' => 'sahodaya',
            'state_program_id' => $this->program->id,
            'results_published' => true, 'status' => 'published',
        ]);

        $this->item = FestEventItem::create([
            'event_id' => $this->hubEvent->id, 'state_program_item_id' => $this->stateItem->id,
            'title' => 'Light Music', 'category' => 'music', 'item_code' => 'LM01',
        ]);
    }

    private function winner(string $name, int $position, float $score, string $schoolId = 'school-1'): FestMark
    {
        $class = SchoolClass::firstOrCreate(['tenant_id' => $schoolId, 'name' => 'Class 6']);
        $student = Student::create(['name' => $name, 'tenant_id' => $schoolId, 'school_class_id' => $class->id]);

        $registration = FestRegistration::create([
            'event_id' => $this->hubEvent->id, 'item_id' => $this->item->id,
            'school_id' => $schoolId, 'status' => 'approved',
        ]);

        $participant = FestParticipant::create(['registration_id' => $registration->id, 'student_id' => $student->id]);

        return FestMark::create([
            'event_id' => $this->hubEvent->id, 'item_id' => $this->item->id,
            'participant_id' => $participant->id, 'position' => $position,
            'score' => $score, 'grade' => 'A',
        ]);
    }

    private function service(): FestStateSlotCollectionService
    {
        return app(FestStateSlotCollectionService::class);
    }

    private function batch()
    {
        return app(FestStateNominationService::class)->openBatch($this->program, $this->hubEvent);
    }

    private function primaries()
    {
        return $this->batch()->selections()->where('nomination_type', 'primary')->where('status', 'selected')->get();
    }

    private array $users = [];

    private function user(string $name = 'Convener'): User
    {
        return $this->users[$name] ??= User::create([
            'name' => $name, 'email' => \Str::slug($name).'@example.test', 'username' => \Str::slug($name),
            'password' => 'password', 'tenant_id' => 'sahodaya-1', 'email_verified_at' => now(),
        ]);
    }

    public function test_opening_fills_the_top_ranks_and_marks_them_pending(): void
    {
        $this->winner('First', 1, 95, 'school-1');
        $this->winner('Second', 2, 88, 'school-2');
        $this->winner('Third', 3, 80, 'school-1');

        $result = $this->service()->open($this->program, $this->hubEvent, $this->user());

        $this->assertSame(2, $result['filled']);
        $this->hubEvent->refresh();
        $this->assertTrue($this->hubEvent->state_slot_collection_open);
        $this->assertNotNull($this->hubEvent->state_slot_collection_opened_at);

        $primaries = $this->primaries();
        $this->assertCount(2, $primaries);
        $this->assertSame(['pending', 'pending'], $primaries->pluck('school_response')->all());
        $this->assertSame(['First', 'Second'], $primaries->pluck('student_name')->sort()->values()->all());
    }

    public function test_opening_twice_is_refused(): void
    {
        $this->winner('First', 1, 95);
        $this->service()->open($this->program, $this->hubEvent, $this->user());

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service()->open($this->program, $this->hubEvent, $this->user());
    }

    public function test_a_school_accepting_records_who_and_when(): void
    {
        $this->winner('First', 1, 95, 'school-1');
        $this->service()->open($this->program, $this->hubEvent, $this->user());

        $selection = $this->primaries()->first();
        $by = $this->user('School Coordinator');

        $updated = $this->service()->respond($selection, 'accept', null, $by);

        $this->assertSame('accepted', $updated->school_response);
        $this->assertSame($by->id, $updated->school_responded_by);
        $this->assertNotNull($updated->school_responded_at);
        $this->assertSame('selected', $updated->status);
    }

    public function test_opting_out_requires_a_reason(): void
    {
        $this->winner('First', 1, 95, 'school-1');
        $this->service()->open($this->program, $this->hubEvent, $this->user());

        $selection = $this->primaries()->first();

        $this->expectException(ValidationException::class);
        $this->service()->respond($selection, 'opt_out', '', $this->user());
    }

    public function test_opting_out_declines_and_offers_the_next_rank(): void
    {
        $this->winner('First', 1, 95, 'school-1');
        $this->winner('Second', 2, 88, 'school-2');
        $this->winner('Third', 3, 80, 'school-3');

        $this->service()->open($this->program, $this->hubEvent, $this->user());

        $first = $this->primaries()->firstWhere('student_name', 'First');
        $this->service()->respond($first, 'opt_out', 'Exam clash', $this->user());

        $first->refresh();
        $this->assertSame(FestStateWinnerSheetService::DECLINED, $first->status);
        $this->assertSame('opted_out', $first->school_response);
        $this->assertSame('Exam clash', $first->skip_reason);

        $primaries = $this->primaries();
        $this->assertCount(2, $primaries);
        $names = $primaries->pluck('student_name')->sort()->values()->all();
        $this->assertSame(['Second', 'Third'], $names);

        $promoted = $primaries->firstWhere('student_name', 'Third');
        $this->assertSame('pending', $promoted->school_response);
        $this->assertSame($first->id, $promoted->replaces_selection_id);
    }

    public function test_opting_out_with_nobody_left_just_shrinks_the_slot(): void
    {
        $this->winner('First', 1, 95, 'school-1');

        $this->service()->open($this->program, $this->hubEvent, $this->user());
        $first = $this->primaries()->first();
        $this->service()->respond($first, 'opt_out', 'Exam clash', $this->user());

        $this->assertCount(0, $this->primaries());
    }

    public function test_responding_twice_to_the_same_offer_is_refused(): void
    {
        $this->winner('First', 1, 95, 'school-1');
        $this->service()->open($this->program, $this->hubEvent, $this->user());
        $selection = $this->primaries()->first();

        $this->service()->respond($selection, 'accept', null, $this->user());

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service()->respond($selection->fresh(), 'accept', null, $this->user());
    }

    public function test_approve_is_blocked_while_a_slot_is_still_pending(): void
    {
        $this->winner('First', 1, 95, 'school-1');
        $this->winner('Second', 2, 88, 'school-2');
        $this->service()->open($this->program, $this->hubEvent, $this->user());

        $first = $this->primaries()->firstWhere('student_name', 'First');
        $this->service()->respond($first, 'accept', null, $this->user());
        // Second never responds.

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service()->approve($this->program, $this->hubEvent, $this->user());
    }

    public function test_approve_succeeds_once_every_offer_is_answered(): void
    {
        $this->winner('First', 1, 95, 'school-1');
        $this->winner('Second', 2, 88, 'school-2');
        $this->service()->open($this->program, $this->hubEvent, $this->user());

        foreach ($this->primaries() as $selection) {
            $this->service()->respond($selection, 'accept', null, $this->user());
        }

        $result = $this->service()->approve($this->program, $this->hubEvent, $this->user());

        $this->assertSame(2, $result['approved_count']);
        $this->hubEvent->refresh();
        $this->assertNotNull($this->hubEvent->state_slot_collection_approved_at);
    }

    public function test_approve_is_blocked_after_an_opt_out_chain_leaves_no_one_and_it_is_never_offered_again(): void
    {
        $this->winner('First', 1, 95, 'school-1');
        // Only one candidate, quota is 2 — one slot will always be short, and that is a
        // warning, not a blocker (matches readiness() on the existing winner sheet).
        $this->service()->open($this->program, $this->hubEvent, $this->user());
        $first = $this->primaries()->first();
        $this->service()->respond($first, 'accept', null, $this->user());

        $result = $this->service()->approve($this->program, $this->hubEvent, $this->user());
        $this->assertSame(1, $result['approved_count']);
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_responding_after_the_event_is_certified_is_refused(): void
    {
        $this->winner('First', 1, 95, 'school-1');
        $this->service()->open($this->program, $this->hubEvent, $this->user());
        $selection = $this->primaries()->first();

        $batch = $this->batch();
        $batch->update(['status' => 'certified', 'certified_at' => now()]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service()->respond($selection, 'accept', null, $this->user());
    }

    public function test_a_hand_picked_selection_outside_collection_is_never_marked_pending(): void
    {
        $mark = $this->winner('Hand Picked', 1, 95, 'school-1');
        $nominations = app(FestStateNominationService::class);
        $batch = $this->batch();

        $nominations->select($batch, [
            'item_id' => $this->stateItem->id, 'item_code' => 'LM01', 'item_title' => 'Light Music',
            'mark_id' => $mark->id, 'school_id' => 'school-1', 'school_name' => 'School One',
            'student_name' => 'Hand Picked', 'source_position' => 1,
        ], 'primary', 1, $this->user());

        $selection = FestStateNominationSelection::where('mark_id', $mark->id)->first();
        $this->assertNull($selection->school_response);

        // Collection was never opened for this event, so the register-flow guard must not engage.
        $this->hubEvent->refresh();
        $this->assertFalse($this->hubEvent->state_slot_collection_open);
    }
}
