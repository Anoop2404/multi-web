<?php

namespace Tests\Feature\State;

use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\PlatformState;
use App\Models\PlatformUser;
use App\Models\State\StateAttendance;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateJudgeAssignment;
use App\Models\State\StateJudgeScore;
use App\Services\State\Fest\StateConductService;
use App\Services\State\Fest\StateJudgePortalService;
use App\Services\State\Fest\StateMarkImportService;
use App\Models\State\StateSahodaya;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 6 of the State Kalotsav module — the judge portal and bulk mark import.
 *
 * The rules under test are the ones that protect a result from the way marks actually get recorded:
 * a judge must not know whose performance they are scoring, a sheet must not be declared final while
 * incomplete, and a clerk typing up paper must not be able to land marks under the wrong judge.
 */
class StateJudgePortalAndImportTest extends TestCase
{
    use RefreshDatabase;

    private PlatformState $state;

    private StateFestEvent $event;

    private FestStateProgramItem $item;

    private StateSahodaya $sahodaya;

    private PlatformUser $judge;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Artisan::call('state:migrate');

        $this->state = PlatformState::create(['code' => 'KL', 'name' => 'Kerala', 'is_active' => true]);
        $program = FestStateProgram::create([
            'title' => 'Kerala State Kalotsavam 2026', 'state_id' => $this->state->id,
            'event_type' => 'kalolsavam', 'conduct_levels' => ['state'], 'status' => 'published',
        ]);
        $this->event = StateFestEvent::create([
            'state_program_id' => $program->id, 'state_id' => $this->state->id,
            'name' => 'State Finals', 'slug' => 'finals', 'status' => 'active',
        ]);
        $this->item = FestStateProgramItem::create([
            'state_program_id' => $program->id, 'title' => 'Light Music', 'item_code' => 'LM01', 'qualify_count' => 3,
        ]);
        $this->sahodaya = StateSahodaya::create([
            'id' => (string) Str::uuid(), 'state_id' => $this->state->id, 'name' => 'Malappuram Sahodaya',
            'tenant_id' => (string) Str::uuid(), 'origin' => StateSahodaya::ORIGIN_MANAGED,
        ]);

        $this->judge = PlatformUser::create([
            'name' => 'Panel Member', 'email' => 'judge@example.test', 'username' => 'judge1',
            'password' => 'password', 'state_id' => $this->state->id, 'email_verified_at' => now(),
        ]);
        $this->judge->assignRole('state_judge');
    }

    private function competitor(string $name, string $chest, string $school = 'St Joseph HSS', ?FestStateProgramItem $item = null): StateFestParticipant
    {
        $item ??= $this->item;

        $registration = StateFestRegistration::create([
            'state_event_id' => $this->event->id, 'sahodaya_id' => $this->sahodaya->id,
            'sahodaya_name' => $this->sahodaya->name, 'school_id' => Str::slug($school), 'school_name' => $school,
            'item_id' => $item->id, 'item_code' => $item->item_code, 'status' => 'approved',
        ]);

        return StateFestParticipant::create([
            'state_event_id' => $this->event->id, 'registration_id' => $registration->id,
            'student_name' => $name, 'class_name' => 'Class 10', 'chest_number' => $chest,
        ]);
    }

    private function assign(?FestStateProgramItem $item = null, ?PlatformUser $judge = null): StateJudgeAssignment
    {
        $item ??= $this->item;

        return app(StateConductService::class)->assignJudge(
            $this->event, $item->id, $item->item_code, ($judge ?? $this->judge)->id,
        );
    }

    private function portal(): StateJudgePortalService
    {
        return app(StateJudgePortalService::class);
    }

    // ── The judge's sheet ──────────────────────────────────────────────────────────────────

    public function test_the_sheet_shows_chest_numbers_and_nothing_that_identifies_a_contingent(): void
    {
        $this->competitor('Anil', '201');
        $this->assign();

        $targets = $this->portal()->targets($this->event, $this->item->id, $this->judge->id);
        $flat = json_encode($targets);

        $this->assertStringContainsString('201', $flat);
        $this->assertStringNotContainsString('Anil', $flat);
        $this->assertStringNotContainsString('Malappuram', $flat);
        $this->assertStringNotContainsString('St Joseph', $flat);
    }

    public function test_entries_are_listed_in_chest_number_order_with_unnumbered_last(): void
    {
        $this->competitor('Bindu', '205');
        $this->competitor('Anil', '201');
        $unnumbered = $this->competitor('Chandra', '999');
        $unnumbered->forceFill(['chest_number' => null])->save();
        $this->assign();

        $order = $this->portal()->targets($this->event, $this->item->id, $this->judge->id)->pluck('chest_number')->all();

        $this->assertSame(['201', '205', null], $order);
    }

    public function test_a_judge_sees_only_their_own_scores(): void
    {
        $participant = $this->competitor('Anil', '201');
        $this->assign();

        $other = PlatformUser::create([
            'name' => 'Second Judge', 'email' => 'judge2@example.test', 'username' => 'judge2',
            'password' => 'password', 'state_id' => $this->state->id, 'email_verified_at' => now(),
        ]);
        $other->assignRole('state_judge');
        $this->assign(judge: $other);

        $this->portal()->score($this->event, $this->item->id, $participant->id, $other->id, ['score' => 88]);

        $mine = $this->portal()->targets($this->event, $this->item->id, $this->judge->id);

        $this->assertNull($mine[0]['score']);
    }

    public function test_a_judge_cannot_score_an_item_they_are_not_on(): void
    {
        $participant = $this->competitor('Anil', '201');

        $this->expectException(ValidationException::class);
        $this->portal()->score($this->event, $this->item->id, $participant->id, $this->judge->id, ['score' => 80]);
    }

    public function test_an_absent_competitor_cannot_be_scored(): void
    {
        $participant = $this->competitor('Anil', '201');
        $this->assign();

        StateAttendance::create([
            'state_event_id' => $this->event->id, 'registration_id' => $participant->registration_id,
            'participant_id' => $participant->id, 'status' => 'absent',
        ]);

        $this->expectException(ValidationException::class);
        $this->portal()->score($this->event, $this->item->id, $participant->id, $this->judge->id, ['score' => 80]);
    }

    public function test_a_sheet_cannot_be_submitted_while_an_entry_is_unscored(): void
    {
        $first = $this->competitor('Anil', '201');
        $this->competitor('Bindu', '202');
        $this->assign();

        $this->portal()->score($this->event, $this->item->id, $first->id, $this->judge->id, ['score' => 80]);

        try {
            $this->portal()->submit($this->event, $this->item->id, $this->judge->id);
            $this->fail('A half-scored sheet was accepted as final.');
        } catch (ValidationException $e) {
            // The unscored chest numbers are named, so the judge knows what is missing.
            $this->assertStringContainsString('202', $e->errors()['submit'][0]);
        }
    }

    public function test_submitting_a_complete_sheet_records_when_and_how_many(): void
    {
        $first = $this->competitor('Anil', '201');
        $second = $this->competitor('Bindu', '202');
        $assignment = $this->assign();

        $this->portal()->score($this->event, $this->item->id, $first->id, $this->judge->id, ['score' => 80]);
        $this->portal()->score($this->event, $this->item->id, $second->id, $this->judge->id, ['score' => 75]);

        $result = $this->portal()->submit($this->event, $this->item->id, $this->judge->id);

        $this->assertSame(2, $result['submitted']);
        $this->assertNotNull($assignment->fresh()->submitted_at);
    }

    public function test_editing_a_score_after_submitting_reopens_the_sheet(): void
    {
        $participant = $this->competitor('Anil', '201');
        $assignment = $this->assign();

        $this->portal()->score($this->event, $this->item->id, $participant->id, $this->judge->id, ['score' => 80]);
        $this->portal()->submit($this->event, $this->item->id, $this->judge->id);
        $this->assertNotNull($assignment->fresh()->submitted_at);

        $this->portal()->score($this->event, $this->item->id, $participant->id, $this->judge->id, ['score' => 85]);

        $this->assertNull($assignment->fresh()->submitted_at);
    }

    public function test_the_dashboard_reports_progress_per_panel(): void
    {
        $first = $this->competitor('Anil', '201');
        $this->competitor('Bindu', '202');
        $this->assign();

        $this->portal()->score($this->event, $this->item->id, $first->id, $this->judge->id, ['score' => 80]);

        $assignments = $this->portal()->assignments($this->judge->id);

        $this->assertCount(1, $assignments);
        $this->assertSame(2, $assignments[0]['total']);
        $this->assertSame(1, $assignments[0]['scored']);
    }

    public function test_the_office_can_see_which_panel_members_have_submitted(): void
    {
        $participant = $this->competitor('Anil', '201');
        $this->assign();

        $this->portal()->score($this->event, $this->item->id, $participant->id, $this->judge->id, ['score' => 80]);
        $this->portal()->submit($this->event, $this->item->id, $this->judge->id);

        $panel = $this->portal()->panel($this->event, $this->item->id);

        $this->assertCount(1, $panel);
        $this->assertNotNull($panel[0]['submitted_at']);
        $this->assertSame(1, $panel[0]['scored']);
    }

    public function test_the_judge_portal_refuses_a_panel_the_signed_in_judge_is_not_on(): void
    {
        $this->competitor('Anil', '201');

        $this->actingAs($this->judge)
            ->get("/portal/state-fest-judge/{$this->event->id}/items/{$this->item->id}")
            ->assertForbidden();
    }

    public function test_the_judge_portal_lists_a_panel_the_judge_is_on(): void
    {
        $this->competitor('Anil', '201');
        $this->assign();

        $this->actingAs($this->judge)->get('/portal/state-fest-judge')->assertOk();
        $this->actingAs($this->judge)
            ->get("/portal/state-fest-judge/{$this->event->id}/items/{$this->item->id}")
            ->assertOk();
    }

    // ── Bulk mark import ──────────────────────────────────────────────────────────────────

    private function import(): StateMarkImportService
    {
        return app(StateMarkImportService::class);
    }

    public function test_marks_import_by_item_code_and_chest_number(): void
    {
        $first = $this->competitor('Anil', '201');
        $second = $this->competitor('Bindu', '202');
        $this->assign();

        $result = $this->import()->import($this->event, $this->judge->id, [
            ['item_code', 'chest_number', 'score', 'grade'],
            ['LM01', '201', '88', 'A'],
            ['LM01', '202', '79', 'B'],
        ]);

        $this->assertSame(2, $result['applied']);
        $this->assertEqualsWithDelta(88, StateJudgeScore::where('participant_id', $first->id)->value('score'), 0.01);
        $this->assertEqualsWithDelta(79, StateJudgeScore::where('participant_id', $second->id)->value('score'), 0.01);
    }

    public function test_a_row_that_matches_no_entry_names_the_chest_and_the_item(): void
    {
        $this->competitor('Anil', '201');
        $this->assign();

        try {
            $this->import()->import($this->event, $this->judge->id, [
                ['item_code', 'chest_number', 'score'],
                ['LM01', '999', '88'],
            ]);
            $this->fail('An unmatched row was imported.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('999', $e->errors()['file'][0]);
            $this->assertStringContainsString('LM01', $e->errors()['file'][0]);
        }
    }

    public function test_nothing_is_written_when_any_row_fails(): void
    {
        $this->competitor('Anil', '201');
        $this->assign();

        try {
            $this->import()->import($this->event, $this->judge->id, [
                ['item_code', 'chest_number', 'score'],
                ['LM01', '201', '88'],
                ['LM01', '999', '70'],
            ]);
        } catch (ValidationException) {
            // Expected.
        }

        // The valid row must not have landed: a half-applied import cannot be reconciled.
        $this->assertSame(0, StateJudgeScore::count());
    }

    public function test_an_import_cannot_land_marks_under_a_judge_who_is_not_on_the_panel(): void
    {
        $this->competitor('Anil', '201');
        // No assignment created for this judge.

        try {
            $this->import()->import($this->event, $this->judge->id, [
                ['item_code', 'chest_number', 'score'],
                ['LM01', '201', '88'],
            ]);
            $this->fail('Marks were accepted for a judge who is not on the panel.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('not on the panel', $e->errors()['file'][0]);
        }
    }

    public function test_a_duplicated_chest_number_in_the_file_is_refused(): void
    {
        $this->competitor('Anil', '201');
        $this->assign();

        try {
            $this->import()->import($this->event, $this->judge->id, [
                ['item_code', 'chest_number', 'score'],
                ['LM01', '201', '88'],
                ['LM01', '201', '60'],
            ]);
            $this->fail('A duplicated row was accepted.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('twice', $e->errors()['file'][0]);
        }
    }

    public function test_blank_scores_are_skipped_so_a_partly_typed_sheet_can_be_uploaded(): void
    {
        $first = $this->competitor('Anil', '201');
        $this->competitor('Bindu', '202');
        $this->assign();

        $result = $this->import()->import($this->event, $this->judge->id, [
            ['item_code', 'chest_number', 'score'],
            ['LM01', '201', '88'],
            ['LM01', '202', ''],
        ]);

        $this->assertSame(1, $result['applied']);
        $this->assertSame(1, StateJudgeScore::whereNotNull('score')->count());
    }

    public function test_a_dry_run_reports_matches_without_writing(): void
    {
        $this->competitor('Anil', '201');
        $this->assign();

        $result = $this->import()->import($this->event, $this->judge->id, [
            ['item_code', 'chest_number', 'score'],
            ['LM01', '201', '88'],
        ], dryRun: true);

        $this->assertSame(0, $result['applied']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame(0, StateJudgeScore::count());
    }

    public function test_a_file_without_the_required_headers_says_which_are_missing(): void
    {
        try {
            $this->import()->import($this->event, $this->judge->id, [['name', 'marks'], ['Anil', '80']]);
            $this->fail('A file with the wrong headers was accepted.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('chest_number', $e->errors()['file'][0]);
        }
    }

    public function test_the_template_lists_every_numbered_entry(): void
    {
        $this->competitor('Anil', '201');
        $this->competitor('Bindu', '202');

        $rows = $this->import()->template($this->event);

        $this->assertCount(2, $rows);
        $this->assertSame(['LM01', '201', '', '', ''], $rows[0]);
    }
}
