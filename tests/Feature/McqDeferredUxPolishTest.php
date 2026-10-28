<?php

namespace Tests\Feature;

use App\Models\McqExam;
use App\Models\McqMark;
use App\Services\ExamEngine\ExamEngineBridge;
use App\Services\Mcq\McqPaperService;
use App\Support\Mcq\McqProductMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\McqFixtures;
use Tests\TestCase;

/** Hard fullscreen, staff leaderboard preview, portal system-check route. */
class McqDeferredUxPolishTest extends TestCase
{
    use McqFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMcqTenants();
    }

    public function test_school_model_exam_can_require_fullscreen(): void
    {
        $admin = $this->userWithRole('school_admin', $this->school->id);
        $exam = McqExam::create([
            'tenant_id' => $this->sahodaya->id,
            'school_id' => $this->school->id,
            'conductor_level' => 'school',
            'title' => 'Fullscreen model',
            'status' => 'published',
            'delivery_mode' => 'online',
            'fee_type' => 'none',
            'duration_minutes' => 40,
            'settings_json' => McqProductMode::presetSettings(McqProductMode::SCHOOL_MODEL),
        ]);

        $this->actingAs($admin)->put("/school-admin/{$this->school->id}/model-exams/{$exam->id}", [
            'title' => 'Fullscreen model',
            'status' => 'published',
            'delivery_mode' => 'online',
            'duration_minutes' => 40,
            'eligibility_config' => ['audience' => 'students', 'assignment_type' => 'all', 'gender' => 'open'],
            'require_fullscreen' => true,
            'result_visibility' => 'score_grade',
        ])->assertSessionHasNoErrors();

        $exam->refresh();
        $this->assertTrue($exam->requiresFullscreen());
    }

    public function test_portal_exam_passes_require_fullscreen_and_system_check_url(): void
    {
        $bank = $this->makeBank('active', 'FS bank');
        $bank->update(['school_id' => $this->school->id]);
        $this->addSingle($bank);

        $exam = McqExam::create([
            'tenant_id' => $this->sahodaya->id,
            'school_id' => $this->school->id,
            'conductor_level' => 'school',
            'title' => 'Portal FS',
            'status' => 'published',
            'delivery_mode' => 'online',
            'fee_type' => 'none',
            'scheduled_at' => now()->subMinute(),
            'duration_minutes' => 30,
            'settings_json' => array_merge(McqProductMode::presetSettings(McqProductMode::SCHOOL_MODEL), [
                'require_fullscreen' => true,
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
                ->where('exam.require_fullscreen', true)
                ->has('systemCheckUrl'));

        $this->actingAs($user)
            ->get("/portal/student/{$this->school->id}/mcq/system-check")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Portal/Student/McqSystemCheck', false));
    }

    public function test_engine_meta_includes_require_fullscreen(): void
    {
        $exam = $this->makeOnlineExam([
            'scheduled_at' => now()->addHour(),
            'settings_json' => [
                'engine' => 'engine',
                'require_fullscreen' => true,
                'shuffle_questions' => false,
            ],
        ]);
        $bank = $this->makeBank();
        $this->addSingle($bank);
        $exam->questionBanks()->attach($bank->id, ['pick_mode' => 'all', 'display_order' => 1]);

        $paper = app(McqPaperService::class)->questionsForExam($exam->fresh());
        $meta = app(ExamEngineBridge::class)->meta($exam, $paper, 1);

        $this->assertTrue($meta['require_fullscreen']);
    }

    public function test_staff_can_preview_leaderboard_before_publish(): void
    {
        $admin = $this->userWithRole('sahodaya_admin');
        $exam = McqExam::create([
            'tenant_id' => $this->sahodaya->id,
            'title' => 'Preview LB',
            'status' => 'completed',
            'delivery_mode' => 'online',
            'fee_type' => 'none',
            'results_published' => false,
            'settings_json' => McqProductMode::presetSettings(McqProductMode::SAHODAYA_MODEL),
        ]);

        [, $registration] = $this->registerStudent($exam);
        $registration->update(['status' => 'submitted', 'submitted_at' => now()]);
        McqMark::create([
            'registration_id' => $registration->id,
            'score' => 18,
            'max_score' => 20,
            'percentage' => 90,
            'grade' => 'A',
            'rank' => 1,
        ]);

        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$this->sahodaya->id}/mcq-exams/{$exam->id}/leaderboard")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Sahodaya/Mcq/Leaderboard', false)
                ->where('isPreview', true)
                ->has('rows', 1));

        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$this->sahodaya->id}/mcq-exams/{$exam->id}/leaderboard/export")
            ->assertStatus(403);
    }
}
