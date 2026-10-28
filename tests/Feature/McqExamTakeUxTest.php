<?php

namespace Tests\Feature;

use App\Models\McqExam;
use App\Support\Mcq\McqProductMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\McqFixtures;
use Tests\TestCase;

/** Portal + exam-engine student take-exam UX contracts. */
class McqExamTakeUxTest extends TestCase
{
    use McqFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMcqTenants();
    }

    public function test_portal_exam_page_exposes_take_ux_props(): void
    {
        $bank = $this->makeBank('active', 'Take UX bank');
        $bank->update(['school_id' => $this->school->id]);
        $this->addSingle($bank);
        $bank->questions()->create([
            'type' => 'multiple', 'body_text' => 'Primes', 'display_order' => 2, 'marks' => 2,
            'options_json' => [['key' => 'a', 'label' => '2'], ['key' => 'b', 'label' => '3'], ['key' => 'c', 'label' => '4']],
            'answer_json' => ['keys' => ['a', 'b']],
        ]);
        $bank->questions()->create([
            'type' => 'true_false', 'body_text' => 'Earth is round', 'display_order' => 3, 'marks' => 1,
            'options_json' => [['key' => 'a', 'label' => 'True'], ['key' => 'b', 'label' => 'False']],
            'correct_option_key' => 'a',
        ]);
        $bank->questions()->create([
            'type' => 'numeric', 'body_text' => '2+2', 'display_order' => 4, 'marks' => 1,
            'answer_json' => ['value' => 4, 'tolerance' => 0],
        ]);

        $exam = McqExam::create([
            'tenant_id' => $this->sahodaya->id,
            'school_id' => $this->school->id,
            'conductor_level' => 'school',
            'title' => 'Take UX model',
            'status' => 'published',
            'delivery_mode' => 'online',
            'fee_type' => 'none',
            'scheduled_at' => now()->subMinute(),
            'duration_minutes' => 45,
            'settings_json' => array_merge(McqProductMode::presetSettings(McqProductMode::SCHOOL_MODEL), [
                'require_verified_students' => false,
                'shuffle_questions' => false,
                'shuffle_options' => false,
            ]),
        ]);
        $exam->questionBanks()->attach($bank->id, ['pick_mode' => 'all', 'display_order' => 1]);

        [$user, $registration] = $this->registerStudent($exam);

        $this->actingAs($user)
            ->get("/portal/student/{$this->school->id}/mcq/{$registration->id}/exam")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Student/McqExam', false)
                ->where('exam.no_pin_required', true)
                ->where('started', false)
                ->has('systemCheckUrl')
                ->has('questions', 4));
    }

    public function test_portal_mcq_exam_vue_has_attempt_chrome_contracts(): void
    {
        $src = file_get_contents(resource_path('js/Pages/Admin/Portal/Student/McqExam.vue'));
        $this->assertNotFalse($src);
        $this->assertStringContainsString('syncPillText', $src);
        $this->assertStringContainsString('beforeunload', $src);
        $this->assertStringContainsString('type === \'numeric\'', $src);
        $this->assertStringContainsString('type === \'multiple\'', $src);
        $this->assertStringContainsString('type === \'true_false\'', $src);
        $this->assertStringContainsString('fullscreen_exit', $src);
        $this->assertStringContainsString('Wait until answers are saved', $src);
        $this->assertStringContainsString('started ? [] : navItems', $src);
        $this->assertStringContainsString('focus-mode', $src);
        $this->assertStringContainsString('currentIndex', $src);
        $this->assertStringContainsString('← Prev', $src);
        $this->assertStringContainsString('Next →', $src);
        $this->assertStringContainsString('Extra monitors are not detected', $src);
    }

    public function test_exam_engine_screens_have_pin_and_proctor_copy(): void
    {
        $prepare = file_get_contents(base_path('exam-engine/client/src/screens/PrepareScreen.vue'));
        $login = file_get_contents(base_path('exam-engine/client/src/screens/LoginScreen.vue'));
        $exam = file_get_contents(base_path('exam-engine/client/src/screens/ExamScreen.vue'));
        $session = file_get_contents(base_path('exam-engine/client/src/lib/session.ts'));

        $this->assertStringContainsString('start_pin_required', $prepare);
        $this->assertStringContainsString('No Start PIN for this exam', $prepare);
        $this->assertStringContainsString('not the Start PIN', $login);
        $this->assertStringContainsString('proctorSignalCount', $exam);
        $this->assertStringContainsString('Less than 5 minutes left', $exam);
        $this->assertStringContainsString('proctorSignalCount', $session);
        $this->assertStringContainsString('beforeunload', $exam);
    }
}
