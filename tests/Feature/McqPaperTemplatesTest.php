<?php

namespace Tests\Feature;

use App\Models\{McqExam, McqPaperTemplate, McqQuestionBank, SchoolClass};
use App\Services\Mcq\{McqExamSessionService, McqPaperService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\McqFixtures;
use Tests\TestCase;

class McqPaperTemplatesTest extends TestCase
{
    use RefreshDatabase;
    use McqFixtures;

    protected function setUp(): void { parent::setUp(); $this->setUpMcqTenants(); }
    private function url(McqExam $exam): string { return "/sahodaya-admin/{$this->sahodaya->id}/mcq-exams/{$exam->id}"; }
    private function makeTemplate(McqExam $exam, McqQuestionBank $bank, string $title = 'Template 1', array $section = []): McqPaperTemplate
    {
        $this->actingAs($this->userWithRole('sahodaya_admin'))->post($this->url($exam).'/paper-templates', [
            'title' => $title, 'sections' => [array_merge(['bank_id' => $bank->id, 'pick_mode' => 'all'], $section)],
        ])->assertSessionHasNoErrors();
        return McqPaperTemplate::where('title', $title)->firstOrFail();
    }
    private function assign(McqExam $exam, McqPaperTemplate $template, array $classes = ['8'], array $extras = []): void
    {
        $this->put($this->url($exam).'/class-papers', ['fallback' => 'require', 'assignments' => [array_merge([
            'template_id' => $template->id, 'classes' => $classes, 'total_marks' => $template->total_marks,
            'shuffle_questions' => 'all', 'shuffle_options' => true, 'pass_mark' => 50,
        ], $extras)]])->assertSessionHasNoErrors();
    }

    public function test_shared_bank_templates_assign_to_multiple_classes_and_grade_only_assigned_paper(): void
    {
        $exam = $this->makeOnlineExam(['total_questions' => 1]);
        $bank = $this->makeBank();
        $questions = collect(range(1, 4))->map(fn ($i) => $this->addSingle($bank, 'b', $i));
        $template = $this->makeTemplate($exam, $bank, 'Template 1', ['pick_mode' => 'manual', 'question_ids' => [$questions[2]->id, $questions[0]->id], 'marks' => 5]);
        $this->assign($exam, $template, ['Class VIII', '9']);
        [$studentUser, $registration] = $this->registerStudent($exam);
        $papers = app(McqPaperService::class);
        $this->assertCount(2, $papers->questionsForRegistration($registration));
        $this->assertEqualsCanonicalizing([$questions[2]->id, $questions[0]->id], $papers->questionsForRegistration($registration)->pluck('id')->all());
        $sessions = app(McqExamSessionService::class);
        $registration = $sessions->start($registration);
        $this->assertNotNull($registration->paper_snapshot);
        $this->assertArrayNotHasKey('paper_snapshot', $registration->toArray());
        $this->assertArrayNotHasKey('questions', $exam->fresh()->settings_json['paper_assignments'][0]);
        $this->assertSame(10, $exam->fresh()->totalQuestionsForClass('Class 8'));
        $this->get($this->url($exam).'/paper-preview?class=VIII')->assertOk()->assertInertia(fn ($page) => $page->where('paper.total', 2)->where('paper.max_score', 10));
        $this->get($this->url($exam).'/paper-preview?class=10')->assertNotFound();
        $mark = $sessions->submit($registration, [$questions[2]->id => 'b', $questions[0]->id => 'b', $questions[1]->id => 'b']);
        $this->assertEquals(10, $mark->score);
        $this->assertEquals(100, $mark->percentage);
        $this->assertSame(2, $mark->correct_count);
    }

    public function test_versions_are_immutable_and_started_attempts_keep_answer_keys_and_option_order(): void
    {
        $exam = $this->makeOnlineExam(); $bank = $this->makeBank();
        $q = $this->addSingle($bank, 'b');
        $template = $this->makeTemplate($exam, $bank);
        $this->assign($exam, $template);
        [, $registration] = $this->registerStudent($exam);
        $sessions = app(McqExamSessionService::class);
        $registration = $sessions->start($registration);
        $paper = app(McqPaperService::class)->questionsForRegistration($registration)->map->toArray()->all();
        $q->update(['body_text' => 'Changed bank question', 'correct_option_key' => 'a', 'marks' => 50]);
        $this->put($this->url($exam).'/paper-templates/'.$template->id, ['title' => 'Template 1', 'sections' => [['bank_id'=>$bank->id,'pick_mode'=>'all']]])->assertSessionHasNoErrors();
        $this->assertSame(2, $template->fresh()->version);
        $this->assertEquals($paper, app(McqPaperService::class)->questionsForRegistration($registration->fresh())->map->toArray()->all());
        $this->assertSame('Question 1', $registration->fresh()->paper_snapshot['questions'][0]['body_text']);
        $mark = $sessions->submit($registration->fresh(), [$q->id => 'b']);
        $this->assertEquals(1, $mark->score);
        $this->assertEquals(100, $mark->percentage);
        $this->put($this->url($exam).'/class-papers', ['fallback'=>'require','assignments'=>[]])->assertSessionHasErrors('paper_templates');
    }

    public function test_missing_duplicate_classes_and_inconsistent_total_marks_are_rejected(): void
    {
        $exam = $this->makeOnlineExam(); $bank = $this->makeBank(); $this->addSingle($bank);
        $template = $this->makeTemplate($exam, $bank);
        $row = ['template_id'=>$template->id,'classes'=>['8'],'total_marks'=>1,'shuffle_questions'=>'none','shuffle_options'=>false];
        $this->put($this->url($exam).'/class-papers', ['fallback'=>'require','assignments'=>[$row, array_merge($row,['classes'=>['Class VIII']])]])->assertSessionHasErrors('paper_templates');
        $this->put($this->url($exam).'/class-papers', ['fallback'=>'require','assignments'=>[array_merge($row,['total_marks'=>100])]])->assertSessionHasErrors('paper_templates');
        $this->assign($exam, $template, ['9']);
        [$user, $registration] = $this->registerStudent($exam);
        $this->actingAs($user)->post("/portal/student/{$this->school->id}/mcq/{$registration->id}/start")->assertSessionHasErrors('paper_templates');
        $this->assertNull($registration->fresh()->started_at);
    }

    public function test_bank_ownership_invalid_counts_and_manual_selection_are_validated(): void
    {
        $exam = $this->makeOnlineExam(); $bank = $this->makeBank(); $q = $this->addSingle($bank);
        $admin = $this->userWithRole('sahodaya_admin');
        $this->actingAs($admin)->post($this->url($exam).'/paper-templates', ['title'=>'Bad','sections'=>[['bank_id'=>$bank->id,'pick_mode'=>'random','pick_count'=>10]]])->assertSessionHasErrors('paper_templates');
        $other = McqQuestionBank::create(['sahodaya_id'=>'other-tenant','title'=>'Other','subject'=>'Science','status'=>'active']);
        $this->post($this->url($exam).'/paper-templates', ['title'=>'Bad','sections'=>[['bank_id'=>$other->id,'pick_mode'=>'all']]])->assertNotFound();
        $this->post($this->url($exam).'/paper-templates', ['title'=>'Bad','sections'=>[['bank_id'=>$bank->id,'pick_mode'=>'manual','question_ids'=>[999999]]]])->assertSessionHasErrors('paper_templates');
        $this->actingAs($this->userWithRole('student', $this->school->id))->post($this->url($exam).'/paper-templates', ['title'=>'Bad'])->assertForbidden();
    }

    public function test_question_and_option_shuffle_are_stable_without_changing_answer_keys(): void
    {
        $exam = $this->makeOnlineExam(); $bank = $this->makeBank();
        $questions = collect(range(1, 12))->map(fn ($i) => $this->addSingle($bank, 'b', $i));
        $template = $this->makeTemplate($exam, $bank);
        $this->assign($exam, $template);
        [, $one] = $this->registerStudent($exam); [, $two] = $this->registerStudent($exam);
        $papers = app(McqPaperService::class);
        $a = $papers->questionsForRegistration($one);
        $this->assertSame($a->map->toArray()->all(), $papers->questionsForRegistration($one)->map->toArray()->all());
        $this->assertNotSame($a->pluck('id')->all(), $papers->questionsForRegistration($two)->pluck('id')->all());
        $sessions = app(McqExamSessionService::class);
        foreach ($sessions->paperForStudent($one) as $row) {
            $this->assertArrayNotHasKey('correct_option_key', $row);
            $this->assertSame(['a','b','c'], array_column($row['options'],'display_key'));
        }
        $one = $sessions->start($one);
        $mark = $sessions->submit($one, $questions->mapWithKeys(fn ($q) => [$q->id=>'b'])->all());
        $this->assertEquals(100, $mark->percentage);
    }
    public function test_archiving_and_new_versions_do_not_rewrite_saved_class_papers(): void
    {
        $exam = $this->makeOnlineExam(); $bank = $this->makeBank();
        foreach (range(1, 6) as $i) $this->addSingle($bank, 'b', $i);
        $template = $this->makeTemplate($exam, $bank, 'Random template', ['pick_mode'=>'random','pick_count'=>3,'marks'=>1.25]);
        $this->assign($exam, $template, ['8'], ['shuffle_questions'=>'none']);
        [, $registration] = $this->registerStudent($exam);
        $papers = app(McqPaperService::class);
        $original = $papers->questionsForRegistration($registration)->map->toArray()->all();
        $this->assertCount(3, $original);
        $this->assertEquals(3.75, $exam->fresh()->totalQuestionsForClass('VIII'));
        $this->put($this->url($exam).'/paper-templates/'.$template->id, ['title'=>'Changed','sections'=>[['bank_id'=>$bank->id,'pick_mode'=>'first','pick_count'=>1]]])->assertSessionHasNoErrors();
        $this->delete($this->url($exam).'/paper-templates/'.$template->id)->assertSessionHasNoErrors();
        $this->assertEquals($original, $papers->questionsForRegistration($registration->fresh())->map->toArray()->all());
        $this->assertArrayNotHasKey('questions_json', $template->fresh()->toArray());
        $started = app(McqExamSessionService::class)->start($registration);
        $this->assertEquals(3.75, $started->paper_snapshot['total_marks']);
    }

    public function test_template_image_bytes_are_frozen_even_after_bank_image_changes(): void
    {
        $exam = $this->makeOnlineExam(); $bank = $this->makeBank();
        $q = $this->addSingle($bank, 'b');
        $path = 'tests/template-source.png';
        \App\Support\TenantStorage::put($path, 'original image bytes');
        try {
            $q->update(['document_path'=>$path]);
            $template = $this->makeTemplate($exam, $bank);
            $copied = $template->questions_json[0]['document_path'];
            $this->assertNotSame($path, $copied);
            \App\Support\TenantStorage::put($path, 'replacement image bytes');
            $this->assertSame('original image bytes', \App\Support\TenantStorage::get($copied));
        } finally {
            \App\Support\TenantStorage::disk()->delete($path);
            if (isset($copied)) \App\Support\TenantStorage::disk()->delete($copied);
        }
    }

}
