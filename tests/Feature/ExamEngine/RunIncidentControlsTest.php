<?php

namespace Tests\Feature\ExamEngine;

use App\Models\AuditLog;
use App\Models\McqExam;
use App\Services\ExamEngine\ExamEngineEventProcessor;
use App\Services\ExamEngine\ExamEngineRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\McqFixtures;
use Tests\TestCase;

/** Whole-exam incident controls: add time for everyone, hold auto-submit (runbook incident procedures). */
class RunIncidentControlsTest extends TestCase
{
    use McqFixtures;
    use RefreshDatabase;

    private McqExam $exam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMcqTenants();
        config(['exam_engine.internal_url' => 'http://engine.test', 'exam_engine.internal_token' => 'test-token']);
        Http::fake(['engine.test/*' => Http::response(['ok' => true])]);

        $this->exam = $this->makeOnlineExam([
            'scheduled_at'  => now()->subMinutes(30),
            'settings_json' => ['engine' => 'engine', 'engine_state' => 'armed'],
        ]);
    }

    private function url(string $path): string
    {
        return "/sahodaya-admin/{$this->sahodaya->id}/mcq-exams/{$this->exam->id}/live{$path}";
    }

    public function test_sahodaya_admin_adds_time_for_everyone_with_a_reason(): void
    {
        $admin = $this->userWithRole('sahodaya_admin');

        $this->actingAs($admin)->postJson($this->url('/extend-all'), ['minutes' => 15])->assertStatus(422);
        $this->actingAs($admin)->postJson($this->url('/extend-all'), ['minutes' => 15, 'reason' => 'KSEB power cut in Tirur'])
            ->assertOk()->assertJson(['ok' => true]);

        $run = rawurlencode(ExamEngineRun::for($this->exam));
        Http::assertSent(fn (Request $r) => $r->url() === "http://engine.test/internal/runs/{$run}/extend"
            && $r['minutes'] === 15 && str_starts_with($r['actor'], "user:{$admin->id}"));
        $this->assertTrue(AuditLog::where('action', 'mcq.engine.extend_all')->exists());
    }

    public function test_hold_needs_a_reason_and_can_be_resumed(): void
    {
        $admin = $this->userWithRole('sahodaya_admin');

        $this->actingAs($admin)->postJson($this->url('/hold'), ['on' => true])->assertStatus(422);
        $this->actingAs($admin)->postJson($this->url('/hold'), ['on' => true, 'reason' => 'Engine node restart'])->assertOk();
        $this->actingAs($admin)->postJson($this->url('/hold'), ['on' => false])->assertOk();

        $run = rawurlencode(ExamEngineRun::for($this->exam));
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => $r->url() === "http://engine.test/internal/runs/{$run}/hold" && $r['on'] === true);
        Http::assertSent(fn (Request $r) => $r->url() === "http://engine.test/internal/runs/{$run}/hold" && $r['on'] === false);
    }

    public function test_school_coordinators_cannot_use_whole_exam_controls(): void
    {
        $coordinator = $this->userWithRole('school_mcq_coordinator', $this->school->id);
        $staff = $this->userWithRole('exam_staff');

        $this->actingAs($coordinator)->postJson("/school-admin/{$this->school->id}/mcq/{$this->exam->id}/live/extend-all", ['minutes' => 5, 'reason' => 'x'])
            ->assertStatus(404); // route doesn't exist for schools
        $this->actingAs($staff)->postJson("/portal/exam/{$this->sahodaya->id}/exams/{$this->exam->id}/live/extend-all", ['minutes' => 5, 'reason' => 'x'])
            ->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_run_extended_event_is_recorded_at_exam_level_not_per_candidate(): void
    {
        [, $a] = $this->registerStudent($this->exam);
        [, $b] = $this->registerStudent($this->exam);
        $b->update(['extra_time_minutes' => 5]);

        app(ExamEngineEventProcessor::class)->process([
            ['id' => '1-0', 'type' => 'run.extended', 'run' => ExamEngineRun::for($this->exam), 'reg_id' => null, 'at' => now()->toIso8601String(), 'data' => ['minutes' => 10, 'actor' => 'ops:on-call']],
            ['id' => '2-0', 'type' => 'run.hold', 'run' => ExamEngineRun::for($this->exam), 'reg_id' => null, 'at' => now()->toIso8601String(), 'data' => ['on' => true, 'actor' => 'ops:on-call']],
        ]);

        // Not folded into per-candidate minutes (those are re-sent to the engine as roster extra_minutes).
        $this->assertSame(0, $a->fresh()->extra_time_minutes);
        $this->assertSame(5, $b->fresh()->extra_time_minutes);
        $this->assertSame(10, $this->exam->fresh()->settings_json['engine_extended_minutes']);
        $this->assertTrue($this->exam->fresh()->settings_json['engine_hold']);
        $this->assertTrue(AuditLog::where('action', 'mcq.engine.extend_all')->exists(), 'Engine-side (non-Laravel) extensions are audited when they arrive.');
    }
}
