<?php

namespace Tests\Feature\State;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\State\FestStateNominationService;
use App\Services\State\FestStateWinnerSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The Sahodaya's item-driven winner sheet for State registration.
 *
 * The three behaviours it exists for: a tie is a choice the Sahodaya makes, a winner who cannot travel
 * is recorded as such, and the sheet knows how many seats each item has.
 */
class FestStateWinnerSheetTest extends TestCase
{
    use RefreshDatabase;

    private FestStateProgram $program;

    private FestEvent $hubEvent;

    private FestEventItem $item;

    private FestStateProgramItem $stateItem;

    private Tenant $school;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('state:migrate');

        Tenant::create(['id' => 'sahodaya-1', 'name' => 'Sahodaya One', 'type' => 'sahodaya']);
        $this->school = Tenant::create(['id' => 'school-1', 'name' => 'School One', 'type' => 'school', 'parent_id' => 'sahodaya-1']);
        Tenant::create(['id' => 'school-2', 'name' => 'School Two', 'type' => 'school', 'parent_id' => 'sahodaya-1']);

        $this->program = FestStateProgram::create([
            'title' => 'Kerala State Kalotsavam 2026', 'event_type' => 'kalolsavam',
            'conduct_levels' => ['sahodaya', 'state'], 'status' => 'published',
        ]);

        // The State gives two seats per item — the "top 2" the Sahodaya sends.
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

    private function sheets(): FestStateWinnerSheetService
    {
        return app(FestStateWinnerSheetService::class);
    }

    private function nominations(): FestStateNominationService
    {
        return app(FestStateNominationService::class);
    }

    private function batch()
    {
        return $this->nominations()->openBatch($this->program, $this->hubEvent);
    }

    private function row(): array
    {
        return $this->sheets()->sheet($this->program, $this->hubEvent)->firstWhere('item_code', 'LM01');
    }

    private array $users = [];

    private function user(string $name = 'Convener'): User
    {
        // Cached: username is unique, and several tests call this more than once.
        return $this->users[$name] ??= User::create([
            'name' => $name, 'email' => \Str::slug($name).'@example.test', 'username' => \Str::slug($name),
            'password' => 'password', 'tenant_id' => 'sahodaya-1', 'email_verified_at' => now(),
        ]);
    }

    // ── The sheet ──────────────────────────────────────────────────────────────────────────

    public function test_the_sheet_is_one_row_per_state_item_with_its_seats(): void
    {
        $this->winner('First', 1, 95);
        $this->winner('Second', 2, 88);

        $row = $this->row();

        $this->assertSame('Light Music', $row['title']);
        $this->assertSame(2, $row['quota']);
        $this->assertSame(0, $row['chosen_count']);
        $this->assertSame(2, $row['seats_left']);
        $this->assertCount(2, $row['candidates']);
    }

    public function test_candidates_are_listed_in_rank_order(): void
    {
        $this->winner('Third', 3, 80);
        $this->winner('First', 1, 95);
        $this->winner('Second', 2, 88);

        $this->assertSame(
            ['First', 'Second', 'Third'],
            array_column($this->row()['candidates'], 'student_name'),
        );
    }

    public function test_an_item_we_have_no_results_for_is_left_off_the_sheet(): void
    {
        FestStateProgramItem::create([
            'state_program_id' => $this->program->id, 'title' => 'Mono Act',
            'item_code' => 'MA01', 'qualify_count' => 2,
        ]);
        $this->winner('First', 1, 95);

        $sheet = $this->sheets()->sheet($this->program, $this->hubEvent);

        // The page is the work remaining, not a catalog.
        $this->assertNull($sheet->firstWhere('item_code', 'MA01'));
        $this->assertNotNull($sheet->firstWhere('item_code', 'LM01'));
    }

    public function test_choosing_fills_a_seat_and_shows_who_is_going(): void
    {
        $mark = $this->winner('First', 1, 95);
        $candidate = collect($this->nominations()->candidatePool($this->program, $this->hubEvent))
            ->firstWhere('mark_id', $mark->id);

        $this->nominations()->select($this->batch(), $candidate, 'primary', 1, $this->user());

        $row = $this->row();

        $this->assertSame(1, $row['chosen_count']);
        $this->assertSame(1, $row['seats_left']);
        $this->assertSame('First', $row['chosen'][0]['student_name']);
        $this->assertTrue($row['candidates'][0]['is_chosen']);
    }

    // ── Ties ───────────────────────────────────────────────────────────────────────────────

    public function test_a_tie_beyond_the_seats_is_reported_as_unresolved(): void
    {
        // Three tied firsts for two seats: the Sahodaya must choose.
        $this->winner('Tied A', 1, 95);
        $this->winner('Tied B', 1, 95, 'school-2');
        $this->winner('Tied C', 1, 95);

        $row = $this->row();

        $this->assertSame([1], $row['tied_positions']);
        $this->assertSame([1], $row['unresolved_ties']);
    }

    public function test_a_tie_that_fits_the_seats_needs_no_choice(): void
    {
        // Two tied firsts, two seats — nothing to resolve.
        $this->winner('Tied A', 1, 95);
        $this->winner('Tied B', 1, 95, 'school-2');

        $row = $this->row();

        $this->assertSame([1], $row['tied_positions']);
        $this->assertSame([], $row['unresolved_ties']);
    }

    public function test_choosing_one_of_a_tie_resolves_it(): void
    {
        $chosen = $this->winner('Tied A', 1, 95);
        $this->winner('Tied B', 1, 95, 'school-2');
        $this->winner('Tied C', 1, 95);

        $this->assertSame([1], $this->row()['unresolved_ties']);

        $candidate = collect($this->nominations()->candidatePool($this->program, $this->hubEvent))
            ->firstWhere('mark_id', $chosen->id);
        $this->nominations()->select($this->batch(), $candidate, 'primary', 1, $this->user());

        $this->assertSame([], $this->row()['unresolved_ties']);
    }

    public function test_an_unresolved_tie_blocks_registration(): void
    {
        $this->winner('Tied A', 1, 95);
        $this->winner('Tied B', 1, 95, 'school-2');
        $this->winner('Tied C', 1, 95);

        $readiness = $this->sheets()->readiness($this->program, $this->hubEvent);

        $this->assertFalse($readiness['can_register']);
        $this->assertStringContainsString('tied', $readiness['blocking'][0]);
    }

    // ── Declines ───────────────────────────────────────────────────────────────────────────

    public function test_a_winner_who_cannot_travel_is_recorded_against_their_own_name(): void
    {
        $mark = $this->winner('Cannot travel', 1, 95);
        $this->winner('Next in line', 2, 88);

        $candidate = collect($this->nominations()->candidatePool($this->program, $this->hubEvent))
            ->firstWhere('mark_id', $mark->id);

        $this->sheets()->decline($this->batch(), $candidate, 'Away at a national camp', $this->user());

        $row = $this->row();

        $this->assertCount(1, $row['declined']);
        $this->assertSame('Cannot travel', $row['declined'][0]['student_name']);
        $this->assertSame('Away at a national camp', $row['declined'][0]['note']);
        // A decline does not consume a seat — the point is that someone else goes.
        $this->assertSame(2, $row['seats_left']);
        $this->assertTrue($row['candidates'][0]['is_declined']);
    }

    public function test_a_decline_needs_a_reason(): void
    {
        $mark = $this->winner('Cannot travel', 1, 95);
        $candidate = collect($this->nominations()->candidatePool($this->program, $this->hubEvent))
            ->firstWhere('mark_id', $mark->id);

        $this->expectException(ValidationException::class);
        $this->sheets()->decline($this->batch(), $candidate, '   ', $this->user());
    }

    public function test_someone_already_going_cannot_be_declined_without_removing_them_first(): void
    {
        $mark = $this->winner('First', 1, 95);
        $candidate = collect($this->nominations()->candidatePool($this->program, $this->hubEvent))
            ->firstWhere('mark_id', $mark->id);

        $this->nominations()->select($this->batch(), $candidate, 'primary', 1, $this->user());

        try {
            $this->sheets()->decline($this->batch(), $candidate, 'Changed their mind', $this->user());
            $this->fail('A chosen candidate was declined without being removed.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('currently chosen', $e->errors()['reason'][0]);
        }
    }

    public function test_a_decline_can_be_withdrawn(): void
    {
        $mark = $this->winner('Changed mind', 1, 95);
        $candidate = collect($this->nominations()->candidatePool($this->program, $this->hubEvent))
            ->firstWhere('mark_id', $mark->id);

        $selection = $this->sheets()->decline($this->batch(), $candidate, 'Exam clash', $this->user());
        $this->sheets()->withdrawDecline($this->batch(), $selection);

        $this->assertCount(0, $this->row()['declined']);
        $this->assertFalse($this->row()['candidates'][0]['is_declined']);
    }

    // ── Filling from the top ───────────────────────────────────────────────────────────────

    public function test_filling_from_the_top_takes_the_ranked_candidates(): void
    {
        $this->winner('First', 1, 95);
        $this->winner('Second', 2, 88);
        $this->winner('Third', 3, 80);

        $result = $this->sheets()->autoFill($this->program, $this->hubEvent, $this->user());

        $this->assertSame(2, $result['filled']);
        $this->assertSame(
            ['First', 'Second'],
            array_column($this->row()['chosen'], 'student_name'),
        );
    }

    public function test_filling_from_the_top_skips_a_declined_winner(): void
    {
        $declined = $this->winner('Cannot travel', 1, 95);
        $this->winner('Second', 2, 88);
        $this->winner('Third', 3, 80);

        $candidate = collect($this->nominations()->candidatePool($this->program, $this->hubEvent))
            ->firstWhere('mark_id', $declined->id);
        $this->sheets()->decline($this->batch(), $candidate, 'Away', $this->user());

        $this->sheets()->autoFill($this->program, $this->hubEvent, $this->user());

        // The next ranks go instead, and the record still says why.
        $this->assertSame(
            ['Second', 'Third'],
            array_column($this->row()['chosen'], 'student_name'),
        );
    }

    public function test_filling_from_the_top_leaves_an_unresolved_tie_alone(): void
    {
        $this->winner('Tied A', 1, 95);
        $this->winner('Tied B', 1, 95, 'school-2');
        $this->winner('Tied C', 1, 95);

        $result = $this->sheets()->autoFill($this->program, $this->hubEvent, $this->user());

        // Nothing is chosen by coin-toss: the committee decides.
        $this->assertSame(0, $result['filled']);
        $this->assertSame(['LM01 Light Music'], $result['skipped_ties']);
    }

    // ── Readiness ──────────────────────────────────────────────────────────────────────────

    public function test_unfilled_seats_warn_but_do_not_block(): void
    {
        $this->winner('Only one', 1, 95);

        $candidate = collect($this->nominations()->candidatePool($this->program, $this->hubEvent))->first();
        $this->nominations()->select($this->batch(), $candidate, 'primary', 1, $this->user());

        $readiness = $this->sheets()->readiness($this->program, $this->hubEvent);

        // A Sahodaya with nobody for a seat sends nobody; blocking the whole sheet over it would
        // strand every other item.
        $this->assertTrue($readiness['can_register']);
        $this->assertStringContainsString('1 of 2 seat(s) unfilled', $readiness['warnings'][0]);
    }

    public function test_the_quota_refuses_a_third_pick_for_two_seats(): void
    {
        $this->winner('First', 1, 95);
        $this->winner('Second', 2, 88);
        $this->winner('Third', 3, 80);

        $pool = collect($this->nominations()->candidatePool($this->program, $this->hubEvent));
        $batch = $this->batch();

        foreach ($pool->take(2) as $i => $candidate) {
            $this->nominations()->select($batch, $candidate, 'primary', $i + 1, $this->user());
        }

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->nominations()->select($batch, $pool[2], 'primary', 3, $this->user());
    }

    public function test_nothing_chosen_means_nothing_to_register(): void
    {
        $this->winner('First', 1, 95);

        $this->assertSame(0, $this->sheets()->readiness($this->program, $this->hubEvent)['chosen']);
    }
}
