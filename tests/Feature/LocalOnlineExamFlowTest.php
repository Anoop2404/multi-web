<?php

namespace Tests\Feature;

use App\Services\Mcq\McqExamSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\McqFixtures;
use Tests\TestCase;

class LocalOnlineExamFlowTest extends TestCase
{
    use McqFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMcqTenants();
    }

    public function test_candidate_can_resume_drafts_submit_and_cannot_submit_twice_or_access_another_candidate(): void
    {
        $exam = $this->makeOnlineExam();
        $bank = $this->makeBank();
        $questions = collect(range(1, 5))->map(fn ($i) => $this->addSingle($bank, 'b', $i));
        $exam->questionBanks()->attach($bank->id, ['pick_mode' => 'all', 'display_order' => 1]);
        [$user, $registration] = $this->registerStudent($exam);
        [, $other] = $this->registerStudent($exam);
        $base = "/portal/student/{$this->school->id}/mcq/{$registration->id}";
        $this->actingAs($user)->post("$base/start")->assertRedirect();
        $started = $registration->fresh()->started_at->toIso8601String();
        $answers = $questions->take(3)->mapWithKeys(fn ($q) => [$q->id => 'b'])->all();
        $this->post("$base/save-answers", ['answers' => $answers])->assertSuccessful();
        $this->assertEquals($answers, $registration->fresh()->draft_answers);
        $this->get("$base/exam")->assertSuccessful();
        $this->post("$base/start")->assertRedirect();
        $this->assertSame($started, $registration->fresh()->started_at->toIso8601String());
        $this->post("/portal/student/{$this->school->id}/mcq/{$other->id}/start")->assertForbidden();
        $answers[$questions[3]->id] = 'a';
        $this->post("$base/submit", ['answers' => $answers])->assertRedirect()
            ->assertSessionHas('success', 'Exam submitted.');
        $mark = $registration->fresh()->mark;
        $this->assertSame(3, $mark->correct_count);
        $this->assertSame(1, $mark->wrong_count);
        $this->assertSame(1, $mark->unanswered_count);
        $this->assertEquals(60, $mark->percentage);
        $this->post("$base/submit", ['answers' => $answers])->assertSessionHasErrors('exam');
        $this->assertEquals(60, $mark->fresh()->percentage);
    }

    public function test_future_start_is_blocked_and_expired_attempt_cannot_save_answers(): void
    {
        $exam = $this->makeOnlineExam(['scheduled_at' => now()->addHour(), 'duration_minutes' => 1]);
        $bank = $this->makeBank();
        $question = $this->addSingle($bank);
        $exam->questionBanks()->attach($bank->id, ['pick_mode' => 'all', 'display_order' => 1]);
        [$user, $registration] = $this->registerStudent($exam);
        $base = "/portal/student/{$this->school->id}/mcq/{$registration->id}";
        $this->actingAs($user)->post("$base/start")->assertSessionHasErrors('exam');
        $this->assertNull($registration->fresh()->started_at);
        $exam->update(['scheduled_at' => now()->subHour()]);
        $this->post("$base/start")->assertRedirect();
        $this->travel(2)->minutes();
        $this->assertTrue(app(McqExamSessionService::class)->isExpired($registration->fresh()));
        $this->postJson("$base/save-answers", ['answers' => [$question->id => 'b']])->assertUnprocessable();
        $this->travelBack();
    }

    public function test_an_expired_unanswered_exam_can_auto_submit_without_leaking_its_score(): void
    {
        $exam = $this->makeOnlineExam(['duration_minutes' => 1]);
        $bank = $this->makeBank();
        $this->addSingle($bank);
        $exam->questionBanks()->attach($bank->id, ['pick_mode' => 'all', 'display_order' => 1]);
        [$user, $registration] = $this->registerStudent($exam);
        $base = "/portal/student/{$this->school->id}/mcq/{$registration->id}";
        $this->actingAs($user)->post("$base/start")->assertRedirect();
        $this->travel(2)->minutes();
        $this->post("$base/submit", ['answers' => []])->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Time expired — your answers were auto-submitted.');
        $this->assertSame('submitted', $registration->fresh()->status);
        $this->assertSame(1, $registration->fresh()->mark->unanswered_count);
        $this->assertEquals(0, $registration->fresh()->mark->score);
        $this->travelBack();
    }
    public function test_cancelled_rejected_and_blocked_candidates_cannot_start_or_submit_after_expiry(): void
    {
        $exam = $this->makeOnlineExam();
        $bank = $this->makeBank();
        $question = $this->addSingle($bank);
        $exam->questionBanks()->attach($bank->id, ['pick_mode' => 'all', 'display_order' => 1]);
        foreach ([['status' => 'cancelled'], ['approval_status' => 'rejected'],
            ['attendance_status' => 'malpractice'], ['attendance_status' => 'withheld'], ['attendance_status' => 'absent']] as $blocked) {
            [$user, $registration] = $this->registerStudent($exam);
            $registration->update($blocked);
            $base = "/portal/student/{$this->school->id}/mcq/{$registration->id}";
            $this->actingAs($user)->post("$base/start")->assertSessionHasErrors('exam');
            $registration->update(['started_at' => now()->subHours(2)]);
            $this->post("$base/submit", ['answers' => [$question->id => 'b']])->assertSessionHasErrors('exam');
            $this->assertNull($registration->fresh()->mark);
        }
    }

    public function test_class_schedule_controls_start_time_and_attempt_duration(): void
    {
        $exam = $this->makeOnlineExam(['scheduled_at' => now()->subHour(), 'duration_minutes' => 60,
            'settings_json' => ['class_schedules' => [['class_name' => '8', 'scheduled_at' => now()->addHour()->format('Y-m-d\\TH:i'), 'duration_minutes' => 90]]]]);
        $bank = $this->makeBank();
        $this->addSingle($bank);
        $exam->questionBanks()->attach($bank->id, ['pick_mode' => 'all', 'display_order' => 1]);
        [$user, $registration] = $this->registerStudent($exam);
        $base = "/portal/student/{$this->school->id}/mcq/{$registration->id}";
        $this->actingAs($user)->post("$base/start")->assertSessionHasErrors('exam');
        $this->travel(61)->minutes();
        $this->post("$base/start")->assertSessionHasNoErrors();
        $registration->refresh();
        $this->assertEquals(90, $registration->started_at->diffInMinutes(app(McqExamSessionService::class)->expiresAt($registration)));
        $this->travelBack();
    }

    public function test_reload_after_deadline_submits_saved_draft_and_late_payload_cannot_change_answers(): void
    {
        $exam = $this->makeOnlineExam(['duration_minutes' => 1]);
        $bank = $this->makeBank();
        $question = $this->addSingle($bank);
        $exam->questionBanks()->attach($bank->id, ['pick_mode' => 'all', 'display_order' => 1]);
        [$user, $registration] = $this->registerStudent($exam);
        $base = "/portal/student/{$this->school->id}/mcq/{$registration->id}";
        $this->actingAs($user)->post("$base/start");
        $this->postJson("$base/save-answers", ['answers' => [$question->id => 'a']])->assertSuccessful();
        $this->travel(61)->seconds();
        $this->post("$base/submit", ['answers' => [$question->id => 'b']])->assertSessionHasNoErrors();
        $this->assertEquals(0, $registration->fresh()->mark->score);
        [, $reloaded] = $this->registerStudent($exam);
        $reloaded->update(['status' => 'started', 'started_at' => now()->subMinutes(2), 'draft_answers' => [$question->id => 'b']]);
        $studentUser = $reloaded->student->user;
        $this->actingAs($studentUser)->get("/portal/student/{$this->school->id}/mcq/{$reloaded->id}/exam")->assertRedirect();
        $this->assertSame('submitted', $reloaded->fresh()->status);
        $this->assertEquals(1, $reloaded->fresh()->mark->score);
        $this->travelBack();
    }

    public function test_publishing_unpublishing_and_certificates_respect_student_visibility(): void
    {
        $exam = $this->makeOnlineExam();
        $bank = $this->makeBank();
        $question = $this->addSingle($bank);
        $exam->questionBanks()->attach($bank->id, ['pick_mode' => 'all', 'display_order' => 1]);
        [$student, $registration] = $this->registerStudent($exam);
        $base = "/portal/student/{$this->school->id}/mcq/{$registration->id}";
        $adminBase = "/sahodaya-admin/{$this->sahodaya->id}/mcq-exams/{$exam->id}";
        $this->actingAs($student)->post("$base/start");
        $this->post("$base/submit", ['answers' => [$question->id => 'b']]);
        $this->get("$base/exam")->assertInertia(fn ($page) => $page->where('showResults', false)->where('mark.pending', true)->missing('mark.score'));
        $this->get("$base/certificate")->assertSessionHasErrors('results');
        $this->post("$adminBase/publish-results")->assertForbidden();
        $admin = $this->userWithRole('sahodaya_admin');
        $this->actingAs($admin)->post("$adminBase/publish-results")->assertSessionHasNoErrors();
        $this->actingAs($student)->get("$base/exam")->assertInertia(fn ($page) => $page->where('showResults', true)->where('mark.percentage', '100.00'));
        $this->get("$base/certificate")->assertSuccessful()->assertSee('A+');
        $this->actingAs($admin)->post("$adminBase/unpublish-results")->assertSessionHasNoErrors();
        $this->actingAs($student)->get("$base/exam")->assertInertia(fn ($page) => $page->where('showResults', false)->missing('mark.score'));
        $this->get("$base/certificate")->assertSessionHasErrors('results');
    }

    public function test_new_online_submission_refreshes_ranks_when_results_are_already_published(): void
    {
        $exam = $this->makeOnlineExam(['results_published' => true]);
        $bank = $this->makeBank();
        $questions = collect(range(1, 3))->map(fn ($i) => $this->addSingle($bank, 'b', $i));
        $exam->questionBanks()->attach($bank->id, ['pick_mode' => 'all', 'display_order' => 1]);
        [$firstUser, $first] = $this->registerStudent($exam);
        [$secondUser, $second] = $this->registerStudent($exam);
        $sessions = app(McqExamSessionService::class);
        $sessions->start($first);
        $sessions->submit($first, $questions->take(2)->mapWithKeys(fn ($q) => [$q->id => 'b'])->all());
        $this->assertSame(1, $first->fresh()->mark->rank);
        $sessions->start($second);
        $sessions->submit($second, $questions->mapWithKeys(fn ($q) => [$q->id => 'b'])->all());
        $this->assertSame(1, $second->fresh()->mark->rank);
        $this->assertSame(2, $first->fresh()->mark->rank);
    }

}
