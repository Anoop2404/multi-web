<?php

namespace Tests\Feature\ExamEngine;

use App\Models\AuditLog;
use App\Models\ExamEngineBooking;
use App\Models\McqExam;
use App\Models\McqExamStaff;
use App\Models\McqRegistration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ExamEngine\ExamEngineRun;
use App\Services\Mcq\McqExamPasscodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\McqFixtures;
use Tests\TestCase;

/** Phase 3 of docs/ONLINE_EXAM_ENGINE_PLAN_2026_10_15.md: live monitor, role-scoped actions, close. */
class LiveMonitorTest extends TestCase
{
    use McqFixtures;
    use RefreshDatabase;

    private McqExam $exam;

    private Tenant $otherSchool;

    private McqRegistration $ownReg;

    private McqRegistration $otherReg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMcqTenants();

        config([
            'exam_engine.internal_url'   => 'http://engine.test',
            'exam_engine.internal_token' => 'test-token',
        ]);
        Http::preventStrayRequests();

        $this->otherSchool = Tenant::create([
            'id' => (string) Str::uuid(), 'name' => 'Other School', 'type' => 'school',
            'parent_id' => $this->sahodaya->id, 'is_active' => true, 'membership_status' => 'approved',
        ]);

        $this->exam = $this->makeOnlineExam([
            'scheduled_at'  => now()->addHour(),
            'settings_json' => [
                'engine'          => 'engine',
                'engine_state'    => 'armed',
                'engine_settings' => ['coordinator_extend_cap_minutes' => 10],
            ],
        ]);

        [, $this->ownReg] = $this->registerStudent($this->exam);
        $this->ownReg->update(['hall_ticket_no' => '12']);
        $this->otherReg = $this->registerOtherSchoolStudent($this->exam);
    }

    // ── fixtures ──────────────────────────────────────────────────────────

    private function registerOtherSchoolStudent(McqExam $exam): McqRegistration
    {
        $class = SchoolClass::firstOrCreate(['tenant_id' => $this->otherSchool->id, 'name' => 'Class 8']);
        $student = Student::create(['tenant_id' => $this->otherSchool->id, 'school_class_id' => $class->id, 'name' => 'Other Pupil']);

        return McqRegistration::create([
            'exam_id' => $exam->id, 'school_id' => $this->otherSchool->id, 'student_id' => $student->id,
            'status' => 'registered', 'approval_status' => 'approved', 'hall_ticket_no' => '12',
        ]);
    }

    private function sahodayaUrl(string $path = ''): string
    {
        return "/sahodaya-admin/{$this->sahodaya->id}/mcq-exams/{$this->exam->id}/live{$path}";
    }

    private function portalUrl(string $path = ''): string
    {
        return "/portal/exam/{$this->sahodaya->id}/exams/{$this->exam->id}/live{$path}";
    }

    private function schoolUrl(string $path = ''): string
    {
        return "/school-admin/{$this->school->id}/mcq/{$this->exam->id}/live{$path}";
    }

    private function summaryPayload(): array
    {
        return [
            'run'          => ExamEngineRun::for($this->exam),
            'state'        => 'live',
            'server_time'  => '2026-11-20T05:00:00Z',
            'counts'       => ['roster' => 1200, 'checked_in' => 900, 'waiting' => 40, 'writing' => 800, 'submitted' => 300, 'locked' => 1, 'offline' => 12],
            'by_school'    => [
                ['school_id' => $this->school->id, 'school_name' => 'Phase One School', 'roster' => 60, 'checked_in' => 50, 'writing' => 40, 'submitted' => 10, 'offline' => 1, 'flagged' => 2],
                ['school_id' => $this->otherSchool->id, 'school_name' => 'Other School', 'roster' => 1140, 'checked_in' => 850, 'writing' => 760, 'submitted' => 290, 'offline' => 11, 'flagged' => 5],
            ],
            'pace_seconds' => 10,
            'unfinished'   => 840,
        ];
    }

    private function attemptRow(McqRegistration $registration, array $overrides = []): array
    {
        return array_merge([
            'reg_id' => $registration->id, 'name' => 'Candidate '.$registration->id,
            'school_id' => $registration->school_id, 'school_name' => 'School', 'class_name' => 'Class 8',
            'hall_ticket' => '12', 'state' => 'writing', 'checked_in_at' => '2026-11-20T04:00:00Z',
            'started_at' => '2026-11-20T04:30:00Z', 'deadline' => '2026-11-20T05:30:00Z', 'submitted_at' => null,
            'submit_mode' => null, 'last_seen_at' => '2026-11-20T04:59:50Z', 'answered' => 31, 'flags' => 2,
            'device' => 'Chrome 131 / Android', 'ip' => '10.0.0.1', 'extra_minutes' => 0,
        ], $overrides);
    }

    /** Fakes the engine; the attempts endpoint deliberately returns rows from both schools. */
    private function fakeEngine(array $actionRow = []): void
    {
        Http::fake([
            'engine.test/internal/runs/*/attempts/*/actions' => Http::response($actionRow ?: $this->attemptRow($this->ownReg)),
            'engine.test/internal/runs/*/attempts*'          => Http::response([
                'data'     => [$this->attemptRow($this->ownReg), $this->attemptRow($this->otherReg)],
                'total'    => 2, 'page' => 1, 'per_page' => 50,
            ]),
            'engine.test/internal/runs/*/summary'   => Http::response($this->summaryPayload()),
            'engine.test/internal/runs/*/broadcast' => Http::response(['ok' => true]),
            'engine.test/internal/runs/*/pace'      => Http::response(['ok' => true]),
            'engine.test/internal/runs/*/close'     => Http::response(['ok' => true, 'unfinished' => 3]),
            'engine.test/internal/runs/*/roster/*'  => Http::response(['ok' => true]),
        ]);
    }

    private function assertPayload(array $expected, Request $request): void
    {
        $actual = $request->data();
        ksort($expected);
        ksort($actual);
        $this->assertSame($expected, $actual);
    }

    private function sentActions(): array
    {
        return collect(Http::recorded())
            ->map(fn (array $pair) => $pair[0])
            ->filter(fn (Request $r) => str_contains($r->url(), '/actions'))
            ->values()
            ->all();
    }

    // ── Sahodaya monitor ──────────────────────────────────────────────────

    public function test_sahodaya_monitor_renders_with_the_engine_summary(): void
    {
        $this->fakeEngine();
        $admin = $this->userWithRole('sahodaya_admin');

        $this->actingAs($admin)->get($this->sahodayaUrl())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ExamEngine/SahodayaLiveMonitor', false)
                ->where('engine.available', true)
                ->where('permissions.can_act', true)
                ->where('permissions.run_actions', true)
                ->where('initial.ok', true)
                ->where('initial.summary.counts.roster', 1200)
                ->where('initial.summary.counts.writing', 800)
                ->where('initial.summary.counts.flagged', 7)
                ->where('initial.summary.unfinished', 840)
                ->has('initial.summary.by_school', 2)
                ->has('initial.attempts.data', 2)
                ->where('registeredCount', 2)
                ->where('pollSeconds', 5));

        Http::assertSent(fn (Request $r) => $r->method() === 'GET'
            && str_contains($r->url(), '/internal/runs/'.rawurlencode(ExamEngineRun::for($this->exam)).'/summary')
            && $r->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_data_endpoint_passes_filters_to_the_engine(): void
    {
        $this->fakeEngine();
        $admin = $this->userWithRole('sahodaya_admin');

        $this->actingAs($admin)->getJson($this->sahodayaUrl('/data?state=writing&q=asha&flagged=1&page=2&school_id='.$this->otherSchool->id))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('summary.counts.submitted', 300)
            ->assertJsonPath('attempts.data.0.reg_id', $this->ownReg->id);

        Http::assertSent(function (Request $r) {
            if (! str_contains($r->url(), '/attempts?')) {
                return false;
            }
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $query);

            return $query['state'] === 'writing' && $query['q'] === 'asha' && $query['flagged'] === '1'
                && $query['page'] === '2' && $query['per_page'] === '50' && $query['school_id'] === $this->otherSchool->id;
        });
    }

    public function test_unreachable_engine_shows_a_friendly_message_never_a_500(): void
    {
        Http::fake(['engine.test/*' => Http::failedConnection('Connection refused')]);
        $admin = $this->userWithRole('sahodaya_admin');

        $this->actingAs($admin)->get($this->sahodayaUrl())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('initial.ok', false)
                ->where('initial.reason', 'engine_error')
                ->where('initial.error', fn (string $error) => str_contains($error, 'could not be reached')));

        $this->actingAs($admin)->getJson($this->sahodayaUrl('/data'))
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('reason', 'engine_error');

        $this->actingAs($admin)->postJson($this->sahodayaUrl("/attempts/{$this->ownReg->id}/action"), ['action' => 'unlock'])
            ->assertStatus(503)
            ->assertJsonPath('ok', false)
            ->assertJson(fn ($json) => $json->where('message', fn (string $m) => str_contains($m, 'could not be reached'))->etc());

        $this->assertSame(0, AuditLog::where('action', 'mcq.engine.unlock')->count());
    }

    public function test_engine_refusal_is_returned_as_a_readable_error(): void
    {
        Http::fake(['engine.test/internal/runs/*/attempts/*/actions' => Http::response(['error' => 'already_submitted', 'message' => 'This attempt is already submitted.'], 409)]);

        $this->actingAs($this->userWithRole('sahodaya_admin'))
            ->postJson($this->sahodayaUrl("/attempts/{$this->ownReg->id}/action"), ['action' => 'force_submit', 'reason' => 'malpractice'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This attempt is already submitted.')
            ->assertJsonPath('code', 'already_submitted');
    }

    public function test_exams_not_on_the_engine_or_not_armed_explain_themselves_without_calling_it(): void
    {
        Http::fake();
        $admin = $this->userWithRole('sahodaya_admin');

        $inApp = $this->makeOnlineExam(['title' => 'In-app exam']);
        $this->actingAs($admin)->get("/sahodaya-admin/{$this->sahodaya->id}/mcq-exams/{$inApp->id}/live")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('engine.reason', 'not_engine')->where('initial.ok', false));

        $paper = $this->makeOnlineExam(['title' => 'Paper exam', 'delivery_mode' => 'offline']);
        $this->actingAs($admin)->get("/sahodaya-admin/{$this->sahodaya->id}/mcq-exams/{$paper->id}/live")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('engine.reason', 'not_online'));

        $unarmed = $this->makeOnlineExam(['title' => 'Unarmed', 'scheduled_at' => now()->addDays(5), 'settings_json' => ['engine' => 'engine']]);
        $this->actingAs($admin)->get("/sahodaya-admin/{$this->sahodaya->id}/mcq-exams/{$unarmed->id}/live")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('engine.reason', 'not_armed')
                ->where('initial.reason', 'not_armed')
                ->where('engine.message', fn (string $m) => str_contains($m, 'armed automatically')));
        $this->actingAs($admin)->postJson("/sahodaya-admin/{$this->sahodaya->id}/mcq-exams/{$unarmed->id}/live/broadcast", ['message' => 'Hello'])
            ->assertStatus(422);

        config(['exam_engine.internal_url' => null]);
        $this->actingAs($admin)->get($this->sahodayaUrl())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('engine.reason', 'not_configured'));

        Http::assertNothingSent();
    }

    public function test_sahodaya_attempt_actions_call_the_engine_with_actor_and_are_audited(): void
    {
        $this->fakeEngine();
        $admin = $this->userWithRole('sahodaya_admin');
        $actor = "user:{$admin->id} (sahodaya_admin)";
        $url = $this->sahodayaUrl("/attempts/{$this->ownReg->id}/action");

        $this->actingAs($admin)->postJson($url, ['action' => 'extend', 'minutes' => 25, 'reason' => 'Power cut'])
            ->assertOk()->assertJsonPath('ok', true)->assertJsonPath('attempt.reg_id', $this->ownReg->id);
        $this->actingAs($admin)->postJson($url, ['action' => 'force_submit', 'reason' => 'Malpractice'])->assertOk();
        $this->actingAs($admin)->postJson($url, ['action' => 'lock', 'reason' => 'Verifying identity'])->assertOk();
        $this->actingAs($admin)->postJson($url, ['action' => 'unlock'])->assertOk();
        $this->actingAs($admin)->postJson($url, ['action' => 'allow_switch', 'reason' => 'Phone died'])->assertOk();

        // Reason is required for extend, force submit and lock.
        $this->actingAs($admin)->postJson($url, ['action' => 'force_submit'])->assertStatus(422)->assertJsonValidationErrors('reason');

        $sent = $this->sentActions();
        $this->assertCount(5, $sent);
        $expectedPath = '/internal/runs/'.rawurlencode(ExamEngineRun::for($this->exam))."/attempts/{$this->ownReg->id}/actions";
        foreach ($sent as $request) {
            $this->assertSame('POST', $request->method());
            $this->assertStringEndsWith($expectedPath, $request->url());
            $this->assertSame($actor, $request['actor']);
        }
        $this->assertPayload(['action' => 'extend', 'minutes' => 25, 'reason' => 'Power cut', 'actor' => $actor], $sent[0]);
        $this->assertPayload(['action' => 'force_submit', 'reason' => 'Malpractice', 'actor' => $actor], $sent[1]);
        $this->assertSame('lock', $sent[2]['action']);
        $this->assertPayload(['action' => 'unlock', 'actor' => $actor], $sent[3]);
        $this->assertSame('allow_switch', $sent[4]['action']);

        foreach (['extend', 'force_submit', 'lock', 'unlock', 'allow_switch'] as $action) {
            $log = AuditLog::where('action', "mcq.engine.{$action}")->latest('id')->first();
            $this->assertNotNull($log, "No audit row for {$action}");
            $this->assertSame($admin->id, $log->user_id);
            $this->assertSame($actor, $log->properties['actor']);
            $this->assertSame('sahodaya', $log->properties['via']);
            $this->assertSame($this->ownReg->id, $log->properties['registration_id']);
            $this->assertSame((string) $this->ownReg->id, $log->subject_id);
        }
        $this->assertSame(25, AuditLog::where('action', 'mcq.engine.extend')->first()->properties['minutes']);
        $this->assertSame('Power cut', AuditLog::where('action', 'mcq.engine.extend')->first()->properties['reason']);
    }

    public function test_broadcast_pace_and_close(): void
    {
        $this->fakeEngine();
        ExamEngineBooking::create([
            'tenant_id' => $this->sahodaya->id, 'exam_id' => $this->exam->id, 'title' => $this->exam->title,
            'live_starts_at' => now(), 'live_ends_at' => now()->addHours(2), 'expected_count' => 2, 'status' => 'armed',
        ]);
        $admin = $this->userWithRole('sahodaya_admin');
        $actor = "user:{$admin->id} (sahodaya_admin)";

        $this->actingAs($admin)->postJson($this->sahodayaUrl('/broadcast'), ['message' => 'Read Q14 as x = 5'])->assertOk();
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/broadcast') && $r['message'] === 'Read Q14 as x = 5' && $r['actor'] === $actor);
        $this->assertSame($actor, AuditLog::where('action', 'mcq.engine.broadcast')->first()->properties['actor']);

        $this->actingAs($admin)->postJson($this->sahodayaUrl('/pace'), ['seconds' => 30])->assertOk();
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/pace') && $r->data() === ['seconds' => 30]);
        $this->actingAs($admin)->postJson($this->sahodayaUrl('/pace'), ['seconds' => null])->assertOk();
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/pace') && $r->data() === ['seconds' => null]);
        $this->actingAs($admin)->postJson($this->sahodayaUrl('/pace'), ['seconds' => 45])->assertStatus(422);
        $this->assertSame(2, AuditLog::where('action', 'mcq.engine.pace')->count());

        // Close needs an explicit confirmation.
        $this->actingAs($admin)->postJson($this->sahodayaUrl('/close'), [])->assertStatus(422)->assertJsonValidationErrors('confirm');
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/close'));

        $this->actingAs($admin)->postJson($this->sahodayaUrl('/close'), ['confirm' => true])
            ->assertOk()
            ->assertJsonPath('unfinished', 3);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/close'));

        $this->assertSame('closed', ExamEngineRun::state($this->exam->fresh()));
        $this->assertSame('closed', ExamEngineBooking::first()->status);
        $close = AuditLog::where('action', 'mcq.engine.close')->first();
        $this->assertSame(3, $close->properties['unfinished_forced']);
        $this->assertSame($actor, $close->properties['actor']);

        // Closed: no more actions.
        $this->actingAs($admin)->postJson($this->sahodayaUrl('/broadcast'), ['message' => 'Too late'])->assertStatus(422);
    }

    public function test_sahodaya_staff_need_mcq_manage_to_act(): void
    {
        $this->fakeEngine();
        $staff = $this->userWithRole('sahodaya_staff');
        $staff->givePermissionTo('mcq.view');

        $this->actingAs($staff)->get($this->sahodayaUrl())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('permissions.can_act', false)->where('initial.ok', true));
        $this->actingAs($staff)->postJson($this->sahodayaUrl("/attempts/{$this->ownReg->id}/action"), ['action' => 'unlock'])->assertForbidden();
        $this->actingAs($staff)->postJson($this->sahodayaUrl('/broadcast'), ['message' => 'Hi'])->assertForbidden();

        $noMcq = $this->userWithRole('sahodaya_staff');
        $noMcq->givePermissionTo('fest.manage');
        $this->actingAs($noMcq)->get($this->sahodayaUrl())->assertForbidden();

        $this->assertSame([], $this->sentActions());

        $staff->givePermissionTo('mcq.manage');
        $this->actingAs($staff)->postJson($this->sahodayaUrl("/attempts/{$this->ownReg->id}/action"), ['action' => 'unlock'])->assertOk();
    }

    public function test_actions_on_a_registration_of_another_exam_are_refused(): void
    {
        $this->fakeEngine();
        $otherExam = $this->makeOnlineExam(['title' => 'Other exam']);
        [, $foreign] = $this->registerStudent($otherExam);

        $this->actingAs($this->userWithRole('sahodaya_admin'))
            ->postJson($this->sahodayaUrl("/attempts/{$foreign->id}/action"), ['action' => 'unlock'])
            ->assertNotFound();
        $this->assertSame([], $this->sentActions());
    }

    // ── exam portal ───────────────────────────────────────────────────────

    public function test_exam_staff_can_watch_but_not_act_in_the_exam_portal(): void
    {
        $this->fakeEngine();
        $staff = $this->userWithRole('exam_staff');

        $this->actingAs($staff)->get($this->portalUrl())->assertForbidden(); // not assigned to this exam

        McqExamStaff::create(['exam_id' => $this->exam->id, 'user_id' => $staff->id, 'role' => 'staff']);
        $this->actingAs($staff)->get($this->portalUrl())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ExamEngine/ExamPortalLiveMonitor', false)
                ->where('permissions.can_act', false)
                ->where('permissions.attempt_actions', [])
                ->where('initial.summary.counts.roster', 1200));
        $this->actingAs($staff)->getJson($this->portalUrl('/data'))->assertOk()->assertJsonPath('ok', true);

        $this->actingAs($staff)->postJson($this->portalUrl("/attempts/{$this->ownReg->id}/action"), ['action' => 'unlock'])->assertForbidden();
        $this->actingAs($staff)->postJson($this->portalUrl("/attempts/{$this->ownReg->id}/passcode"))->assertForbidden();
        $this->actingAs($staff)->postJson($this->portalUrl('/broadcast'), ['message' => 'Hi'])->assertForbidden();
        $this->actingAs($staff)->postJson($this->portalUrl('/close'), ['confirm' => true])->assertForbidden();
        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST');

        $controller = $this->userWithRole('exam_controller');
        $this->actingAs($controller)->postJson($this->portalUrl("/attempts/{$this->ownReg->id}/action"), ['action' => 'lock', 'reason' => 'Checking ID'])
            ->assertOk();
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/actions') && $r['actor'] === "user:{$controller->id} (exam_controller)");
        $this->assertSame('exam_portal', AuditLog::where('action', 'mcq.engine.lock')->first()->properties['via']);
    }

    // ── school coordinator ────────────────────────────────────────────────

    private function coordinator(): User
    {
        $user = $this->userWithRole('school_mcq_coordinator', $this->school->id);
        $user->givePermissionTo(['mcq.view', 'mcq.manage']);

        return $user;
    }

    public function test_school_coordinator_sees_only_their_own_school(): void
    {
        $this->fakeEngine();
        $coordinator = $this->coordinator();

        $this->actingAs($coordinator)->get($this->schoolUrl())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ExamEngine/SchoolLiveMonitor', false)
                ->where('permissions.school_only', true)
                ->where('permissions.run_actions', false)
                ->where('permissions.attempt_actions', ['extend', 'unlock', 'allow_switch', 'reissue_passcode'])
                ->where('permissions.extend_cap', 10)
                ->where('endpoints.broadcast', null)
                ->where('initial.summary.counts.roster', 60)
                ->where('initial.summary.counts.writing', 40)
                ->where('initial.summary.counts.waiting', null)
                ->where('initial.summary.unfinished', 40)
                ->has('initial.summary.by_school', 1)
                ->where('initial.summary.by_school.0.school_id', $this->school->id)
                ->has('initial.attempts.data', 1)
                ->where('initial.attempts.data.0.reg_id', $this->ownReg->id)
                ->where('initial.attempts.data.0.extend_left', 10)
                ->where('registeredCount', 1));

        // Even when asked for another school, the engine is asked for this one.
        $this->actingAs($coordinator)->getJson($this->schoolUrl('/data?school_id='.$this->otherSchool->id))
            ->assertOk()
            ->assertJsonCount(1, 'attempts.data');
        $attemptRequests = collect(Http::recorded())->map(fn ($pair) => $pair[0])->filter(fn (Request $r) => str_contains($r->url(), '/attempts?'));
        $this->assertCount(2, $attemptRequests);
        foreach ($attemptRequests as $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $this->assertSame($this->school->id, $query['school_id']);
        }
    }

    public function test_school_coordinator_actions_are_limited_to_their_school_and_role(): void
    {
        $this->fakeEngine();
        $coordinator = $this->coordinator();

        // A forged registration id from another school is refused before the engine is called.
        $this->actingAs($coordinator)->postJson($this->schoolUrl("/attempts/{$this->otherReg->id}/action"), ['action' => 'unlock'])
            ->assertForbidden();
        $this->actingAs($coordinator)->postJson($this->schoolUrl("/attempts/{$this->otherReg->id}/passcode"))->assertForbidden();
        $this->assertSame([], $this->sentActions());
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/roster/'));

        // Sahodaya-only actions.
        $this->actingAs($coordinator)->postJson($this->schoolUrl("/attempts/{$this->ownReg->id}/action"), ['action' => 'force_submit', 'reason' => 'x'])->assertForbidden();
        $this->actingAs($coordinator)->postJson($this->schoolUrl("/attempts/{$this->ownReg->id}/action"), ['action' => 'lock', 'reason' => 'x'])->assertForbidden();
        $this->actingAs($coordinator)->postJson($this->schoolUrl('/broadcast'), ['message' => 'x'])->assertNotFound();
        $this->actingAs($coordinator)->postJson($this->schoolUrl('/close'), ['confirm' => true])->assertNotFound();
        $this->assertSame([], $this->sentActions());

        // First-line support on their own candidate works.
        $this->actingAs($coordinator)->postJson($this->schoolUrl("/attempts/{$this->ownReg->id}/action"), ['action' => 'allow_switch'])->assertOk();
        $this->actingAs($coordinator)->postJson($this->schoolUrl("/attempts/{$this->ownReg->id}/action"), ['action' => 'unlock', 'reason' => 'ID checked'])->assertOk();
        $sent = $this->sentActions();
        $this->assertCount(2, $sent);
        $this->assertSame("user:{$coordinator->id} (school_mcq_coordinator)", $sent[0]['actor']);
        $this->assertSame('school', AuditLog::where('action', 'mcq.engine.unlock')->first()->properties['via']);

        // Another school's staff can't open this school's monitor.
        $outsider = $this->userWithRole('school_mcq_coordinator', $this->otherSchool->id);
        $outsider->givePermissionTo(['mcq.view', 'mcq.manage']);
        $this->actingAs($outsider)->get($this->schoolUrl())->assertForbidden();
    }

    public function test_coordinator_extend_cap_is_enforced_per_candidate(): void
    {
        $this->fakeEngine();
        $coordinator = $this->coordinator();
        $url = $this->schoolUrl("/attempts/{$this->ownReg->id}/action");

        $this->actingAs($coordinator)->postJson($url, ['action' => 'extend', 'minutes' => 6, 'reason' => 'Power cut'])->assertOk();
        $this->actingAs($coordinator)->postJson($url, ['action' => 'extend', 'minutes' => 5, 'reason' => 'Again'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('minutes');
        $this->actingAs($coordinator)->postJson($url, ['action' => 'extend', 'minutes' => 11, 'reason' => 'Too much'])->assertStatus(422);

        // Time given by the Sahodaya team doesn't use up the school's allowance.
        $this->actingAs($this->userWithRole('sahodaya_admin'))
            ->postJson($this->sahodayaUrl("/attempts/{$this->ownReg->id}/action"), ['action' => 'extend', 'minutes' => 30, 'reason' => 'Scribe'])
            ->assertOk();

        $this->actingAs($coordinator)->postJson($url, ['action' => 'extend', 'minutes' => 4, 'reason' => 'Last bit'])->assertOk();
        $this->actingAs($coordinator)->postJson($url, ['action' => 'extend', 'minutes' => 1, 'reason' => 'More'])
            ->assertStatus(422)
            ->assertJsonPath('errors.minutes.0', fn (string $m) => str_contains($m, 'already has the 10 extra minutes'));

        $minutes = collect($this->sentActions())->map(fn (Request $r) => $r['minutes'])->all();
        $this->assertSame([6, 30, 4], $minutes);

        $this->actingAs($coordinator)->getJson($this->schoolUrl('/data'))
            ->assertOk()
            ->assertJsonPath('attempts.data.0.extend_used', 10)
            ->assertJsonPath('attempts.data.0.extend_left', 0);
    }

    public function test_coordinator_reissues_a_passcode_and_it_is_pushed_to_the_engine_roster(): void
    {
        $this->fakeEngine();
        $coordinator = $this->coordinator();
        $old = app(McqExamPasscodeService::class)->ensure($this->ownReg);

        $response = $this->actingAs($coordinator)->postJson($this->schoolUrl("/attempts/{$this->ownReg->id}/passcode"))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('pushed', true);

        $fresh = $this->ownReg->fresh();
        $this->assertNotSame($old, $fresh->exam_passcode);
        $this->assertSame(McqExamPasscodeService::format($fresh->exam_passcode), $response->json('passcode'));
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && str_ends_with($r->url(), "/roster/{$this->ownReg->id}"));

        $log = AuditLog::where('action', 'mcq.engine.passcode_reissued')->first();
        $this->assertSame("user:{$coordinator->id} (school_mcq_coordinator)", $log->properties['actor']);
        $this->assertStringNotContainsString($fresh->exam_passcode, json_encode($log->properties));
    }

    public function test_reissue_still_shows_the_new_passcode_when_the_engine_is_down(): void
    {
        Http::fake(['engine.test/*' => Http::failedConnection('Connection refused')]);

        $response = $this->actingAs($this->userWithRole('sahodaya_admin'))
            ->postJson($this->sahodayaUrl("/attempts/{$this->ownReg->id}/passcode"))
            ->assertOk()
            ->assertJsonPath('pushed', false);

        $this->assertSame(McqExamPasscodeService::format($this->ownReg->fresh()->exam_passcode), $response->json('passcode'));
        $this->assertNotEmpty($response->json('warning'));
    }
}
