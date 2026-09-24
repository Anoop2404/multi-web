<?php

namespace Tests\Feature\State;

use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\PlatformState;
use App\Models\PlatformUser;
use App\Models\State\StateConductAudit;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestMark;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateItemResult;
use App\Models\State\StateSahodaya;
use App\Services\State\Fest\StateConductService;
use App\Services\State\Fest\StateEventSettings;
use App\Services\State\Fest\StateResultService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phases 6 and 7 of the State Kalotsav module — conduct and results.
 *
 * The defining rule of Phase 7 is that points aggregate to the Sahodaya, which is the competing
 * organization at State level, while the School each participant came from is carried through so a
 * Sahodaya's total can be broken down by the Schools that earned it.
 */
class StateConductResultTest extends TestCase
{
    use RefreshDatabase;

    private PlatformState $state;

    private FestStateProgram $program;

    private StateFestEvent $event;

    private FestStateProgramItem $item;

    private array $sahodayas = [];

    private array $users = [];

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
            'item_code' => 'LM01', 'qualify_count' => 3,
        ]);
    }

    private function sahodaya(string $name): StateSahodaya
    {
        return $this->sahodayas[$name] ??= StateSahodaya::create([
            'id' => (string) Str::uuid(), 'state_id' => $this->state->id, 'name' => $name,
            'tenant_id' => (string) Str::uuid(), 'origin' => StateSahodaya::ORIGIN_MANAGED,
        ]);
    }

    private function entry(string $sahodayaName, string $school, string $participant): StateFestParticipant
    {
        $registration = StateFestRegistration::create([
            'state_event_id' => $this->event->id,
            'sahodaya_id' => $this->sahodaya($sahodayaName)->id,
            'sahodaya_name' => $sahodayaName,
            'school_id' => Str::slug($school), 'school_name' => $school,
            'item_id' => $this->item->id, 'item_code' => 'LM01', 'status' => 'approved',
        ]);

        return StateFestParticipant::create([
            'state_event_id' => $this->event->id, 'registration_id' => $registration->id,
            'student_name' => $participant, 'class_name' => 'Class 10',
        ]);
    }

    private function conduct(): StateConductService
    {
        return app(StateConductService::class);
    }

    private function results(): StateResultService
    {
        return app(StateResultService::class);
    }

    private function scoreOf(StateFestParticipant $p, float $score): void
    {
        StateFestMark::updateOrCreate(
            ['state_event_id' => $this->event->id, 'registration_id' => $p->registration_id, 'participant_id' => $p->id],
            ['score' => $score, 'grade' => 'A', 'status' => 'aggregated'],
        );
    }

    private function user(string $role = 'state_admin'): PlatformUser
    {
        return $this->users[$role] ??= tap(PlatformUser::create([
            'name' => 'Registrar', 'email' => $role.'@example.test', 'username' => $role,
            'password' => 'password', 'state_id' => $this->state->id, 'email_verified_at' => now(),
        ]), fn ($u) => $u->assignRole($role));
    }

    // ── Conduct ────────────────────────────────────────────────────────────────────────────

    public function test_changing_attendance_is_recorded_but_marking_it_first_is_not(): void
    {
        $participant = $this->entry('Malappuram Sahodaya', 'St Joseph HSS', 'Athira');
        $registration = $participant->registration;

        $this->conduct()->markAttendance($this->event, $registration, ['status' => 'present']);
        $this->assertSame(0, StateConductAudit::where('kind', 'attendance')->count(), 'Marking for the first time is not a correction.');

        $this->conduct()->markAttendance($this->event, $registration, [
            'status' => 'absent', 'reason' => 'Did not report', 'user_name' => 'Marshal',
        ]);

        $audit = StateConductAudit::where('kind', 'attendance')->sole();
        $this->assertSame('present', $audit->value_from);
        $this->assertSame('absent', $audit->value_to);
        $this->assertSame('Marshal', $audit->changed_by_name);
    }

    public function test_an_absent_competitor_cannot_be_scored(): void
    {
        // The commonest way a disputed result is created.
        $participant = $this->entry('Malappuram Sahodaya', 'St Joseph HSS', 'Athira');
        $this->conduct()->markAttendance($this->event, $participant->registration, ['status' => 'absent']);

        try {
            $this->conduct()->enterJudgeScore($this->event, $participant->registration, $participant->id, 1, ['score' => 80]);
            $this->fail('A mark for an absent competitor must be refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('marked absent', collect($e->errors())->flatten()->first());
        }
    }

    public function test_scoring_is_refused_while_the_event_is_locked(): void
    {
        $participant = $this->entry('Malappuram Sahodaya', 'St Joseph HSS', 'Athira');
        $this->event->forceFill(['scoring_locked' => true])->save();

        $this->expectException(ValidationException::class);
        $this->conduct()->enterJudgeScore($this->event->fresh(), $participant->registration, $participant->id, 1, ['score' => 80]);
    }

    public function test_a_changed_mark_is_recorded(): void
    {
        $participant = $this->entry('Malappuram Sahodaya', 'St Joseph HSS', 'Athira');

        $this->conduct()->enterJudgeScore($this->event, $participant->registration, $participant->id, 1, ['score' => 70]);
        $this->conduct()->enterJudgeScore($this->event, $participant->registration, $participant->id, 1, [
            'score' => 85, 'reason' => 'Transcription error', 'user_name' => 'Coordinator',
        ]);

        $audit = StateConductAudit::where('kind', 'mark')->sole();
        $this->assertSame('70', substr($audit->value_from, 0, 2));
        $this->assertSame('85', substr($audit->value_to, 0, 2));
        $this->assertSame('Transcription error', $audit->reason);
    }

    public function test_a_panel_is_averaged_and_can_drop_the_extremes(): void
    {
        $participant = $this->entry('Malappuram Sahodaya', 'St Joseph HSS', 'Athira');

        foreach ([60, 80, 100] as $judge => $score) {
            $this->conduct()->enterJudgeScore($this->event, $participant->registration, $participant->id, $judge + 1, ['score' => $score]);
        }

        $this->conduct()->aggregateItem($this->event, $this->item->id);
        $this->assertSame('80.00', (string) StateFestMark::sole()->score, 'Mean of 60, 80 and 100.');

        // Dropping the extremes leaves only the middle score.
        app(StateEventSettings::class)->update($this->event, ['drop_high_low' => true]);
        $this->conduct()->aggregateItem($this->event->fresh(), $this->item->id);
        $this->assertSame('80.00', (string) StateFestMark::sole()->fresh()->score);
    }

    public function test_an_incomplete_panel_is_aggregated_but_named(): void
    {
        // A panel that lost a judge still has to produce a result; the office decides whether to accept it.
        app(StateEventSettings::class)->update($this->event, ['judge_count' => 3]);
        $participant = $this->entry('Malappuram Sahodaya', 'St Joseph HSS', 'Athira');
        $this->conduct()->enterJudgeScore($this->event, $participant->registration, $participant->id, 1, ['score' => 75]);

        $result = $this->conduct()->aggregateItem($this->event->fresh(), $this->item->id);

        $this->assertSame(1, $result['aggregated']);
        $this->assertSame(['Athira'], $result['incomplete']);
    }

    // ── Results ────────────────────────────────────────────────────────────────────────────

    public function test_equal_scores_share_a_position_and_the_next_is_skipped(): void
    {
        // Two firsts are followed by a third, which is how a result sheet reads.
        $a = $this->entry('Malappuram Sahodaya', 'St Joseph HSS', 'A');
        $b = $this->entry('Kasaragod Sahodaya', 'Govt HSS', 'B');
        $c = $this->entry('Thrissur Sahodaya', 'Model HSS', 'C');
        $this->scoreOf($a, 90);
        $this->scoreOf($b, 90);
        $this->scoreOf($c, 80);

        $computed = $this->results()->computeItem($this->event, $this->item);

        $this->assertSame(3, $computed['ranked']);
        $this->assertSame(1, $computed['ties']);
        $this->assertSame(1, StateFestMark::where('participant_id', $a->id)->value('position'));
        $this->assertSame(1, StateFestMark::where('participant_id', $b->id)->value('position'));
        $this->assertSame(3, StateFestMark::where('participant_id', $c->id)->value('position'));
    }

    public function test_standings_count_only_published_items(): void
    {
        // A provisional ranking is the office's working view; letting it move the table would change
        // a Sahodaya's position with nothing announced.
        $a = $this->entry('Malappuram Sahodaya', 'St Joseph HSS', 'A');
        $this->scoreOf($a, 90);
        $this->results()->computeItem($this->event, $this->item);

        $this->assertCount(0, $this->results()->sahodayaStandings($this->event));
        $this->assertCount(1, $this->results()->sahodayaStandings($this->event, includeProvisional: true));

        $this->results()->publishItem($this->event, $this->item);
        $this->assertCount(1, $this->results()->sahodayaStandings($this->event));
    }

    public function test_points_aggregate_to_the_sahodaya_and_break_down_by_school(): void
    {
        // The defining rule of the State module.
        $a = $this->entry('Malappuram Sahodaya', 'St Joseph HSS', 'A');
        $b = $this->entry('Malappuram Sahodaya', 'Govt HSS', 'B');
        $c = $this->entry('Kasaragod Sahodaya', 'Model HSS', 'C');
        $this->scoreOf($a, 95);
        $this->scoreOf($b, 85);
        $this->scoreOf($c, 75);

        $this->results()->computeItem($this->event, $this->item);
        $this->results()->publishItem($this->event, $this->item);

        $standings = $this->results()->sahodayaStandings($this->event);

        // Two Sahodayas, not three Schools — the School is never a row in the ranking.
        $this->assertCount(2, $standings);
        $this->assertSame('Malappuram Sahodaya', $standings[0]['sahodaya']);

        $contribution = $this->results()->schoolContribution($this->event, $this->sahodaya('Malappuram Sahodaya')->id);
        $this->assertCount(2, $contribution);
        // The parts reconcile with the whole.
        $this->assertSame($standings[0]['points'], $contribution->sum('points'));
    }

    public function test_a_locked_result_refuses_recomputation(): void
    {
        $a = $this->entry('Malappuram Sahodaya', 'St Joseph HSS', 'A');
        $this->scoreOf($a, 90);
        $this->results()->computeItem($this->event, $this->item);
        $this->results()->publishItem($this->event, $this->item);
        $this->results()->lockItem($this->event, $this->item);

        $this->expectException(ValidationException::class);
        $this->results()->computeItem($this->event, $this->item);
    }

    public function test_withdrawing_a_published_result_requires_a_reason_and_is_recorded(): void
    {
        $a = $this->entry('Malappuram Sahodaya', 'St Joseph HSS', 'A');
        $this->scoreOf($a, 90);
        $this->results()->computeItem($this->event, $this->item);
        $this->results()->publishItem($this->event, $this->item);

        try {
            $this->results()->unpublishItem($this->event, $this->item, []);
            $this->fail('Withdrawing something already seen must say why.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('already been seen', collect($e->errors())->flatten()->first());
        }

        $this->results()->unpublishItem($this->event, $this->item, ['reason' => 'Appeal upheld', 'user_name' => 'Registrar']);

        $this->assertSame(StateItemResult::PROVISIONAL, StateItemResult::sole()->status);
        $audit = StateConductAudit::where('kind', 'result')->latest('id')->first();
        $this->assertSame('Appeal upheld', $audit->reason);
    }

    public function test_publishing_before_computing_is_refused(): void
    {
        $this->expectException(ValidationException::class);
        $this->results()->publishItem($this->event, $this->item);
    }

    // ── Screens and access ─────────────────────────────────────────────────────────────────

    public function test_the_conduct_screens_render_and_publishing_is_its_own_capability(): void
    {
        $a = $this->entry('Malappuram Sahodaya', 'St Joseph HSS', 'A');
        $this->scoreOf($a, 90);
        $url = "http://superadmin.test/admin/state/fest/{$this->event->id}";

        foreach (['attendance', 'marks', 'results', 'leaderboard'] as $path) {
            $this->actingAs($this->user(), 'platform')->get("{$url}/{$path}")->assertOk();
        }

        // A mark operator may enter marks but must never publish a result.
        $operator = $this->user('state_mark_operator');
        $this->actingAs($operator, 'platform')->get("{$url}/attendance")->assertOk();
        $this->actingAs($operator, 'platform')->get("{$url}/marks")->assertOk();
        $this->actingAs($operator, 'platform')->get("{$url}/results")->assertForbidden();
        $this->actingAs($operator, 'platform')
            ->post("{$url}/results/publish", ['item_id' => $this->item->id])->assertForbidden();
    }

    public function test_the_result_reports_are_now_available(): void
    {
        $a = $this->entry('Malappuram Sahodaya', 'St Joseph HSS', 'A');
        $this->scoreOf($a, 90);
        $this->results()->computeItem($this->event, $this->item);
        $this->results()->publishItem($this->event, $this->item);

        foreach (['overall-sahodaya-ranking', 'school-contribution', 'item-wise-results', 'individual-championship'] as $report) {
            $this->actingAs($this->user(), 'platform')
                ->get("http://superadmin.test/admin/state/fest/{$this->event->id}/reports/{$report}")
                ->assertOk();
        }
    }
}
