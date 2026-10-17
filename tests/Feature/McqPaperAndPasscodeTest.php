<?php

namespace Tests\Feature;

use App\Models\McqRegistration;
use App\Services\Mcq\McqExamPasscodeService;
use App\Services\Mcq\McqHallTicketService;
use App\Services\Mcq\McqPaperService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\McqFixtures;
use Tests\TestCase;

/** Phase 1 of docs/ONLINE_EXAM_ENGINE_PLAN_2026_10_15.md: paper builder, in-app delivery, passcodes. */
class McqPaperAndPasscodeTest extends TestCase
{
    use McqFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMcqTenants();
    }

    private function examUrl(int $examId): string
    {
        return "/sahodaya-admin/{$this->sahodaya->id}/mcq-exams/{$examId}";
    }

    public function test_paper_sections_pick_all_first_or_a_stable_random_set(): void
    {
        $admin = $this->userWithRole('sahodaya_admin');
        $exam = $this->makeOnlineExam();
        $bankA = $this->makeBank('active', 'Bank A');
        $bankB = $this->makeBank('active', 'Bank B');
        $draft = $this->makeBank('draft', 'Draft bank');
        foreach (range(1, 6) as $i) {
            $this->addSingle($bankA, 'a', $i);
            $this->addSingle($bankB, 'b', $i, 2);
        }

        $this->actingAs($admin)->post("{$this->examUrl($exam->id)}/question-banks", ['bank_id' => $draft->id])->assertSessionHas('error');
        $this->actingAs($admin)->post("{$this->examUrl($exam->id)}/question-banks", ['bank_id' => $bankA->id])->assertSessionHas('success');
        $this->actingAs($admin)->post("{$this->examUrl($exam->id)}/question-banks", ['bank_id' => $bankB->id])->assertSessionHas('success');

        $this->actingAs($admin)->put("{$this->examUrl($exam->id)}/question-banks/{$bankA->id}", ['pick_mode' => 'first', 'pick_count' => 2])
            ->assertSessionHas('success');
        $this->actingAs($admin)->put("{$this->examUrl($exam->id)}/question-banks/{$bankB->id}", ['pick_mode' => 'random', 'pick_count' => 3])
            ->assertSessionHas('success');

        $papers = app(McqPaperService::class);
        $paper = $papers->questionsForExam($exam->fresh());
        $this->assertCount(5, $paper);
        $this->assertSame(['Question 1', 'Question 2'], $paper->take(2)->pluck('body_text')->all());
        $randomIds = $paper->slice(2)->pluck('id')->all();
        $this->assertSame($randomIds, $papers->questionsForExam($exam->fresh())->slice(2)->pluck('id')->all(), 'Random pick must be the same every time.');

        $summary = $papers->summary($exam->fresh());
        $this->assertSame(5, $summary['total']);
        $this->assertEquals(8, $summary['max_score']);

        // Move Bank B to the top.
        $this->actingAs($admin)->post("{$this->examUrl($exam->id)}/question-banks/{$bankB->id}/move", ['direction' => 'up']);
        $this->assertSame($bankB->id, $exam->fresh()->questionBanks->first()->id);

        $this->actingAs($admin)->get("{$this->examUrl($exam->id)}/question-banks")->assertOk();
        $this->actingAs($admin)->get("{$this->examUrl($exam->id)}/paper-preview")->assertOk();
    }

    public function test_staff_need_the_mcq_permission_to_change_a_paper(): void
    {
        $exam = $this->makeOnlineExam();
        $bank = $this->makeBank();
        $this->addSingle($bank);
        $staff = $this->userWithRole('sahodaya_staff');
        $staff->givePermissionTo('fest.manage'); // Kalotsav rights alone must not reach exam papers.

        $this->actingAs($staff)->post("{$this->examUrl($exam->id)}/question-banks", ['bank_id' => $bank->id])->assertForbidden();
        $this->actingAs($staff)->put("{$this->examUrl($exam->id)}/paper-settings", ['shuffle_questions' => true, 'multi_marking' => 'partial'])
            ->assertForbidden();
        $this->assertSame(0, $exam->questionBanks()->count());

        $staff->givePermissionTo('mcq.manage');
        $this->actingAs($staff)->post("{$this->examUrl($exam->id)}/question-banks", ['bank_id' => $bank->id])->assertSessionHas('success');
        $this->assertSame(1, $exam->questionBanks()->count());
    }

    public function test_in_app_exam_keeps_a_stable_shuffled_order_and_grades_through_the_shared_grader(): void
    {
        $exam = $this->makeOnlineExam(['settings_json' => ['shuffle_questions' => true]]);
        $bank = $this->makeBank();
        $questions = collect(range(1, 8))->map(fn ($i) => $this->addSingle($bank, 'b', $i));
        $exam->questionBanks()->attach($bank->id, ['pick_mode' => 'all', 'display_order' => 1]);
        [$user, $registration] = $this->registerStudent($exam);
        $base = "/portal/student/{$this->school->id}/mcq/{$registration->id}";

        $this->actingAs($user)->post("{$base}/start")->assertRedirect();

        $sessions = app(\App\Services\Mcq\McqExamSessionService::class);
        $order = array_column($sessions->paperForStudent($registration->fresh()), 'id');
        $this->assertSame($order, array_column($sessions->paperForStudent($registration->fresh()), 'id'));
        $this->assertEqualsCanonicalizing($questions->pluck('id')->all(), $order);
        $this->assertArrayNotHasKey('correct_option_key', $sessions->paperForStudent($registration->fresh())[0]);

        $answers = $questions->take(5)->mapWithKeys(fn ($q) => [$q->id => 'b'])->all() + [$questions[5]->id => 'a'];
        $this->actingAs($user)->post("{$base}/submit", ['answers' => $answers])->assertRedirect();

        $mark = $registration->fresh()->mark;
        $this->assertSame(5, $mark->correct_count);
        $this->assertSame(1, $mark->wrong_count);
        $this->assertSame(2, $mark->unanswered_count);
        $this->assertEquals(62.5, $mark->percentage);
    }

    public function test_in_app_exam_refuses_papers_that_need_the_exam_engine(): void
    {
        $exam = $this->makeOnlineExam();
        $bank = $this->makeBank();
        $this->addSingle($bank);
        $bank->questions()->create(['type' => 'numeric', 'body_text' => '2+2', 'answer_json' => ['value' => 4, 'tolerance' => 0], 'display_order' => 2]);
        $exam->questionBanks()->attach($bank->id, ['pick_mode' => 'all', 'display_order' => 1]);
        [$user, $registration] = $this->registerStudent($exam);

        $this->actingAs($user)->post("/portal/student/{$this->school->id}/mcq/{$registration->id}/start")
            ->assertSessionHasErrors('exam');
        $this->assertNull($registration->fresh()->started_at);

        $warnings = app(McqPaperService::class)->summary($exam->fresh())['warnings'];
        $this->assertNotEmpty(array_filter($warnings, fn ($w) => str_contains($w, 'exam engine')));
    }

    public function test_student_sees_question_images_only_for_their_own_started_paper(): void
    {
        $exam = $this->makeOnlineExam();
        $bank = $this->makeBank();
        $question = $this->addSingle($bank);
        $question->update(['document_path' => 'sahodayas/x/mcq-banks/1/q.png']);
        $exam->questionBanks()->attach($bank->id, ['pick_mode' => 'all', 'display_order' => 1]);
        [$user, $registration] = $this->registerStudent($exam);
        [$otherUser] = $this->registerStudent($exam);
        $url = "/portal/student/{$this->school->id}/mcq/{$registration->id}/questions/{$question->id}/image";

        $this->actingAs($user)->get($url)->assertForbidden(); // not started yet
        $registration->update(['started_at' => now(), 'status' => 'started']);
        $this->actingAs($otherUser)->get($url)->assertForbidden();

        $unrelated = $this->addSingle($this->makeBank('active', 'Other'));
        $this->actingAs($user)->get("/portal/student/{$this->school->id}/mcq/{$registration->id}/questions/{$unrelated->id}/image")
            ->assertNotFound();
    }

    public function test_hall_tickets_get_unique_passcodes_that_identify_the_candidate(): void
    {
        $exam = $this->makeOnlineExam();
        [, $first] = $this->registerStudent($exam);
        [, $second] = $this->registerStudent($exam);

        app(McqHallTicketService::class)->issueBulk($exam);
        $first->refresh();
        $second->refresh();

        $this->assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]{10}$/', $first->exam_passcode);
        $this->assertNotSame($first->exam_passcode, $second->exam_passcode);
        $this->assertArrayNotHasKey('exam_passcode', $first->toArray(), 'Passcodes must never be serialised to pages.');

        $service = app(McqExamPasscodeService::class);
        $typed = strtolower(McqExamPasscodeService::format($first->exam_passcode));
        $this->assertSame($first->id, $service->findRegistration($exam, $typed)?->id);
        $this->assertNull($service->findRegistration($exam, 'AAAAA-AAAAA'));

        // The passcode prints on the online exam's hall tickets.
        $admin = $this->userWithRole('sahodaya_admin');
        $this->actingAs($admin)->get("{$this->examUrl($exam->id)}/hall-tickets/print-all")
            ->assertOk()
            ->assertSee(McqExamPasscodeService::format($first->exam_passcode));

        // Reissue: the old code stops working.
        $old = $first->exam_passcode;
        $this->actingAs($admin)->post("{$this->examUrl($exam->id)}/registrations/{$first->id}/passcode")->assertSessionHas('success');
        $first->refresh();
        $this->assertNotSame($old, $first->exam_passcode);
        $this->assertNull($service->findRegistration($exam, $old));
        $this->assertSame($first->id, $service->findRegistration($exam, $first->exam_passcode)?->id);
    }

    public function test_offline_exam_hall_tickets_do_not_print_a_passcode(): void
    {
        $exam = $this->makeOnlineExam(['delivery_mode' => 'offline']);
        [, $registration] = $this->registerStudent($exam);
        app(McqHallTicketService::class)->issueBulk($exam);
        $code = McqRegistration::find($registration->id)->exam_passcode;

        $this->actingAs($this->userWithRole('sahodaya_admin'))->get("{$this->examUrl($exam->id)}/hall-tickets/print-all")
            ->assertOk()
            ->assertDontSee('Online exam passcode')
            ->assertDontSee(McqExamPasscodeService::format($code));
    }
}
