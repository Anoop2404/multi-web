<?php

namespace Tests\Feature;

use App\Models\McqQuestion;
use App\Models\McqQuestionBank;
use App\Models\McqRegistration;
use App\Services\Mcq\McqQuestionImporter;
use App\Services\Spreadsheet\SpreadsheetWriter;
use App\Support\TenantStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\McqFixtures;
use Tests\TestCase;

/** Phase 1 of docs/ONLINE_EXAM_ENGINE_PLAN_2026_10_15.md: question bank authoring and XLSX import. */
class McqQuestionBankAuthoringTest extends TestCase
{
    use McqFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMcqTenants();
    }

    private function banksUrl(): string
    {
        return "/sahodaya-admin/{$this->sahodaya->id}/mcq/question-banks";
    }

    public function test_admin_creates_a_bank_and_adds_all_four_question_types(): void
    {
        $admin = $this->userWithRole('sahodaya_admin');

        $this->actingAs($admin)->post($this->banksUrl(), ['title' => 'Class 8 Maths', 'subject' => 'Maths'])
            ->assertRedirect();
        $bank = McqQuestionBank::where('title', 'Class 8 Maths')->firstOrFail();
        $this->assertSame('draft', $bank->status);
        $this->assertNull($bank->school_id);
        $url = "{$this->banksUrl()}/{$bank->id}/questions";

        // Single: a blank option in the middle is dropped and the key re-lettered (c → b).
        $this->actingAs($admin)->post($url, [
            'type' => 'single', 'body_text' => 'Value of $\\sqrt{16}$?',
            'options' => [['key' => 'a', 'label' => '2'], ['key' => 'b', 'label' => ''], ['key' => 'c', 'label' => '4']],
            'correct_keys' => ['c'], 'marks' => 2, 'negative_mark' => 0.5,
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post($url, [
            'type' => 'multiple', 'body_text' => 'Pick the primes',
            'options' => [['key' => 'a', 'label' => '2'], ['key' => 'b', 'label' => '4'], ['key' => 'c', 'label' => '7']],
            'correct_keys' => ['a', 'c'], 'show_count' => true,
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post($url, [
            'type' => 'true_false', 'body_text' => 'π is rational.', 'correct_keys' => ['b'],
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post($url, [
            'type' => 'numeric', 'body_text' => '7 × 8 = ?', 'answer_value' => '56', 'answer_tolerance' => '0',
        ])->assertSessionHasNoErrors();

        $questions = $bank->questions()->get()->keyBy('type');
        $this->assertCount(4, $questions);

        $single = $questions['single'];
        $this->assertSame([['key' => 'a', 'label' => '2'], ['key' => 'b', 'label' => '4']], $single->options_json);
        $this->assertSame('b', $single->correct_option_key);
        $this->assertSame('Value of $\\sqrt{16}$?', $single->body_text);

        $this->assertSame(['a', 'c'], $questions['multiple']->correctKeys());
        $this->assertSame('B', $questions['true_false']->answerKeyLabel());
        $this->assertSame(['True', 'False'], array_column($questions['true_false']->options_json, 'label'));
        $this->assertSame('56', $questions['numeric']->answerKeyLabel());
        $this->assertTrue($questions->every(fn (McqQuestion $q) => $q->isGradable()));

        $this->actingAs($admin)->put("{$this->banksUrl()}/{$bank->id}", [
            'title' => 'Class 8 Maths', 'subject' => 'Maths', 'status' => 'active',
        ])->assertSessionHas('success');
        $this->assertSame('active', $bank->fresh()->status);
    }

    public function test_answer_key_rules_are_enforced(): void
    {
        $admin = $this->userWithRole('sahodaya_admin');
        $bank = $this->makeBank('draft');
        $url = "{$this->banksUrl()}/{$bank->id}/questions";
        $options = [['key' => 'a', 'label' => 'One'], ['key' => 'b', 'label' => 'Two']];

        $this->actingAs($admin)->post($url, ['type' => 'single', 'body_text' => 'Q', 'options' => $options, 'correct_keys' => ['a', 'b']])
            ->assertSessionHasErrors('correct_keys');
        $this->actingAs($admin)->post($url, ['type' => 'multiple', 'body_text' => 'Q', 'options' => $options, 'correct_keys' => []])
            ->assertSessionHasErrors('correct_keys');
        $this->actingAs($admin)->post($url, ['type' => 'single', 'body_text' => 'Q', 'options' => [['key' => 'a', 'label' => 'Only']], 'correct_keys' => ['a']])
            ->assertSessionHasErrors('options');
        $this->actingAs($admin)->post($url, ['type' => 'true_false', 'body_text' => 'Q'])
            ->assertSessionHasErrors('correct_keys');
        $this->actingAs($admin)->post($url, ['type' => 'numeric', 'body_text' => 'Q', 'answer_value' => ''])
            ->assertSessionHasErrors('answer_value');
        $this->actingAs($admin)->post($url, ['type' => 'single', 'body_text' => '', 'options' => $options, 'correct_keys' => ['a']])
            ->assertSessionHasErrors('body_text');

        $this->assertSame(0, $bank->questions()->count());

        // A bank can't be marked Ready without a gradable question.
        $this->actingAs($admin)->put("{$this->banksUrl()}/{$bank->id}", ['title' => $bank->title, 'subject' => 'Science', 'status' => 'active'])
            ->assertSessionHas('error');
        $this->assertSame('draft', $bank->fresh()->status);
    }

    public function test_image_question_with_image_only_options_is_saved_and_served(): void
    {
        Storage::fake(TenantStorage::uploadDisk());
        $admin = $this->userWithRole('sahodaya_admin');
        $bank = $this->makeBank('draft');

        $this->actingAs($admin)->post("{$this->banksUrl()}/{$bank->id}/questions", [
            'type'          => 'single',
            'body_text'     => '',
            'image'         => UploadedFile::fake()->image('diagram.png'),
            'options'       => [['key' => 'a', 'label' => ''], ['key' => 'b', 'label' => 'Text option']],
            'option_images' => ['a' => UploadedFile::fake()->image('opt-a.png')],
            'correct_keys'  => ['a'],
        ])->assertSessionHasNoErrors();

        $question = $bank->questions()->firstOrFail();
        $this->assertNotNull($question->document_path);
        $this->assertNotNull($question->options_json[0]['image_path'] ?? null);
        $this->assertSame('Text option', $question->options_json[1]['label']);
        $this->assertTrue($question->isGradable());

        $this->actingAs($admin)->get("{$this->banksUrl()}/{$bank->id}/questions/{$question->id}/image")->assertOk();
        $this->actingAs($admin)->get("{$this->banksUrl()}/{$bank->id}/questions/{$question->id}/image?option=a")->assertOk();
        $this->actingAs($admin)->get("{$this->banksUrl()}/{$bank->id}/questions/{$question->id}/image?option=b")->assertNotFound();

        // Editing keeps option A's image only when asked to; the client can't point at other files.
        $oldPath = $question->options_json[0]['image_path'];
        $this->actingAs($admin)->put("{$this->banksUrl()}/{$bank->id}/questions/{$question->id}", [
            'type'         => 'single',
            'options'      => [['key' => 'a', 'label' => 'Now text', 'keep_image' => '1', 'image_path' => 'secrets/other.png'], ['key' => 'b', 'label' => 'Text option']],
            'correct_keys' => ['b'],
        ])->assertSessionHasNoErrors();
        $this->assertSame($oldPath, $question->fresh()->options_json[0]['image_path']);

        $this->actingAs($admin)->put("{$this->banksUrl()}/{$bank->id}/questions/{$question->id}", [
            'type'         => 'single',
            'remove_image' => '1',
            'body_text'    => 'Now a text question',
            'options'      => [['key' => 'a', 'label' => 'Now text'], ['key' => 'b', 'label' => 'Text option']],
            'correct_keys' => ['b'],
        ])->assertSessionHasNoErrors();
        $fresh = $question->fresh();
        $this->assertNull($fresh->document_path);
        $this->assertArrayNotHasKey('image_path', $fresh->options_json[0]);
        Storage::disk(TenantStorage::uploadDisk())->assertMissing($oldPath);
    }

    public function test_xlsx_import_previews_rows_then_imports_only_valid_ones(): void
    {
        $admin = $this->userWithRole('sahodaya_admin');
        $bank = $this->makeBank('draft');

        $rows = McqQuestionImporter::template();
        $rows[] = ['single', 'Capital of Kerala?', 'Kochi', 'Thiruvananthapuram', 'Kozhikode', '', '', '', 'B', '', '1', '0', 'easy', ''];
        $rows[] = ['multiple', 'Which are even? $2n$', '2', '3', '4', '', '', '', 'A,C', '', '2', '', '', ''];
        $rows[] = ['true_false', 'The sun is a star.', '', '', '', '', '', '', 'True', '', '', '', '', ''];
        $rows[] = ['numeric', 'Half of 9?', '', '', '', '', '', '', '4.5', '0.01', '', '', '', ''];
        $rows[] = ['single', 'Broken row', 'Yes', 'No', '', '', '', '', 'D', '', '', '', '', ''];
        $rows[] = ['essay', 'Unsupported type', '', '', '', '', '', '', '', '', '', '', '', ''];
        $path = tempnam(sys_get_temp_dir(), 'mcq').'.xlsx';
        file_put_contents($path, SpreadsheetWriter::xlsx($rows));

        $response = $this->actingAs($admin)->post("{$this->banksUrl()}/{$bank->id}/import/preview", [
            'file' => new UploadedFile($path, 'questions.xlsx', null, null, true),
        ]);
        $response->assertSessionHas('mcqImportPreview');
        $preview = session('mcqImportPreview');

        $this->assertSame(10, $preview['total']);
        $this->assertSame(4, $preview['valid_count']);
        $this->assertSame(6, $preview['error_count']);
        $this->assertStringContainsString('example row', $preview['rows'][0]['errors'][0]);
        $broken = collect($preview['rows'])->firstWhere('question', 'Broken row');
        $this->assertStringContainsString('empty option', implode(' ', $broken['errors']));
        $this->assertSame(0, $bank->questions()->count(), 'Preview must not write anything.');

        $this->actingAs($admin)->post("{$this->banksUrl()}/{$bank->id}/import/commit", ['token' => $preview['token']])
            ->assertSessionHas('success', 'Imported 4 question(s).');

        $imported = $bank->questions()->get();
        $this->assertSame(['single', 'multiple', 'true_false', 'numeric'], $imported->pluck('type')->all());
        $this->assertSame('a', $imported[2]->correct_option_key);
        $this->assertSame(['value' => 4.5, 'tolerance' => 0.01], $imported[3]->answer_json);

        // A token works once.
        $this->actingAs($admin)->post("{$this->banksUrl()}/{$bank->id}/import/commit", ['token' => $preview['token']])
            ->assertSessionHas('error');
        $this->assertSame(4, $bank->questions()->count());
        @unlink($path);
    }

    public function test_exam_controllers_author_in_the_exam_portal_but_exam_staff_cannot(): void
    {
        $controller = $this->userWithRole('exam_controller');
        $staff = $this->userWithRole('exam_staff');
        $portalUrl = "/portal/exam/{$this->sahodaya->id}/question-banks";

        $this->actingAs($controller)->get($portalUrl)->assertOk();
        $this->actingAs($controller)->post($portalUrl, ['title' => 'Controller bank', 'subject' => 'English'])->assertRedirect();
        $bank = McqQuestionBank::where('title', 'Controller bank')->firstOrFail();
        $this->actingAs($controller)->post("{$portalUrl}/{$bank->id}/questions", [
            'type' => 'true_false', 'body_text' => 'English is a language.', 'correct_keys' => ['a'],
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, $bank->questions()->count());

        $this->actingAs($staff)->get($portalUrl)->assertForbidden();
        $this->actingAs($staff)->get("{$portalUrl}/{$bank->id}")->assertForbidden();

        // Exam controllers can't use the Sahodaya admin panel itself.
        $this->actingAs($controller)->get($this->banksUrl())->assertForbidden();
    }

    public function test_exam_controller_of_another_sahodaya_cannot_reach_these_banks(): void
    {
        $otherSahodaya = \App\Models\Tenant::create(['id' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Other Sahodaya', 'type' => 'sahodaya', 'is_active' => true]);
        $outsider = $this->userWithRole('exam_controller', $otherSahodaya->id);
        $bank = $this->makeBank();
        $portalUrl = "/portal/exam/{$this->sahodaya->id}/question-banks";

        $this->actingAs($outsider)->get($portalUrl)->assertForbidden();
        $this->actingAs($outsider)->get("{$portalUrl}/{$bank->id}")->assertForbidden();
        $this->actingAs($outsider)->post("{$portalUrl}/{$bank->id}/questions", ['type' => 'true_false', 'body_text' => 'x', 'correct_keys' => ['a']])
            ->assertForbidden();
        $this->assertSame(0, $bank->questions()->count());
    }

    public function test_sahodaya_staff_need_the_mcq_permission_even_to_view_banks(): void
    {
        $staff = $this->userWithRole('sahodaya_staff');
        $bank = $this->makeBank();

        $this->actingAs($staff)->get($this->banksUrl())->assertForbidden();
        $this->actingAs($staff)->get("{$this->banksUrl()}/{$bank->id}")->assertForbidden();

        $staff->givePermissionTo('mcq.manage');
        $this->actingAs($staff)->get($this->banksUrl())->assertOk();
    }

    public function test_another_sahodayas_bank_is_not_reachable(): void
    {
        $admin = $this->userWithRole('sahodaya_admin');
        $other = McqQuestionBank::create(['sahodaya_id' => 'some-other-sahodaya', 'subject' => 'X', 'title' => 'Not yours', 'status' => 'active']);

        $this->actingAs($admin)->get("{$this->banksUrl()}/{$other->id}")->assertNotFound();
        $this->actingAs($admin)->post("{$this->banksUrl()}/{$other->id}/questions", ['type' => 'true_false', 'body_text' => 'x', 'correct_keys' => ['a']])
            ->assertNotFound();
    }

    public function test_questions_lock_once_a_student_starts_an_exam_using_the_bank(): void
    {
        $admin = $this->userWithRole('sahodaya_admin');
        $bank = $this->makeBank();
        $question = $this->addSingle($bank);
        $exam = $this->makeOnlineExam();
        $exam->questionBanks()->attach($bank->id, ['pick_mode' => 'all', 'display_order' => 1]);
        [, $registration] = $this->registerStudent($exam);
        $registration->update(['started_at' => now(), 'status' => 'started']);

        $this->actingAs($admin)->delete("{$this->banksUrl()}/{$bank->id}/questions/{$question->id}")->assertSessionHas('error');
        $this->actingAs($admin)->post("{$this->banksUrl()}/{$bank->id}/questions", ['type' => 'true_false', 'body_text' => 'x', 'correct_keys' => ['a']])
            ->assertSessionHas('error');
        $this->assertSame(1, $bank->questions()->count());
        $this->assertTrue(McqRegistration::find($registration->id)->started_at !== null);
    }

    public function test_answer_key_changes_are_audit_logged(): void
    {
        $admin = $this->userWithRole('sahodaya_admin');
        $bank = $this->makeBank('draft');
        $question = $this->addSingle($bank, 'b');

        $this->actingAs($admin)->put("{$this->banksUrl()}/{$bank->id}/questions/{$question->id}", [
            'type' => 'single', 'body_text' => 'Question 1',
            'options' => [['key' => 'a', 'label' => 'A'], ['key' => 'b', 'label' => 'B'], ['key' => 'c', 'label' => 'C']],
            'correct_keys' => ['c'],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('audit_logs', ['action' => 'mcq.question.answer_changed', 'user_id' => $admin->id]);
    }
}
