<?php

namespace Tests\Feature;

use App\Models\McqExam;
use App\Models\McqProctorEvent;
use App\Services\Mcq\McqExamSessionService;
use App\Services\Mcq\McqVenueKeycodeService;
use App\Support\Mcq\McqProductMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\McqFixtures;
use Tests\TestCase;

class McqVenueKeycodeReattemptStrictTabTest extends TestCase
{
    use McqFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMcqTenants();
    }

    public function test_venue_keycode_is_issued_and_formatted(): void
    {
        $exam = $this->modelExam(['venue_keycode_join' => true]);
        [, $registration] = $this->registerStudent($exam);

        $code = app(McqVenueKeycodeService::class)->ensure($registration->fresh());

        $this->assertNotSame('', $code);
        $this->assertSame(8, strlen($code));
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $code);
        $this->assertSame(
            substr($code, 0, 4).'-'.substr($code, 4),
            McqVenueKeycodeService::format($code),
        );
        $this->assertTrue($exam->fresh()->allowsVenueKeycodeJoin());
    }

    public function test_reattempt_resets_session_and_opens_exam_again(): void
    {
        $bank = $this->makeBank('active', 'Reattempt bank');
        $bank->update(['school_id' => $this->school->id]);
        $this->addSingle($bank);

        $exam = $this->modelExam([
            'allow_reattempt' => true,
            'max_attempts' => 2,
        ]);
        $exam->questionBanks()->attach($bank->id, ['pick_mode' => 'all', 'display_order' => 1]);

        [$user, $registration] = $this->registerStudent($exam);
        $sessions = app(McqExamSessionService::class);
        $sessions->start($registration->fresh());
        $sessions->submit($registration->fresh(), []);

        $registration->refresh();
        $this->assertSame('submitted', $registration->status);
        $this->assertTrue($registration->canReattempt());

        $this->actingAs($user)
            ->post("/portal/student/{$this->school->id}/mcq/{$registration->id}/reattempt")
            ->assertRedirect("/portal/student/{$this->school->id}/mcq/{$registration->id}/exam");

        $registration->refresh();
        $this->assertSame('registered', $registration->status);
        $this->assertSame(2, $registration->attemptNumber());
        $this->assertNull($registration->started_at);
        $this->assertCount(1, $registration->attempt_history ?? []);

        $this->actingAs($user)
            ->get("/portal/student/{$this->school->id}/mcq/{$registration->id}/exam")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Student/McqExam', false)
                ->where('started', false)
                ->where('attemptNumber', 2));
    }

    public function test_strict_tab_auto_submits_after_limit(): void
    {
        $bank = $this->makeBank('active', 'Strict bank');
        $bank->update(['school_id' => $this->school->id]);
        $this->addSingle($bank);

        $exam = $this->modelExam([
            'proctor_mode' => 'strict_tab',
            'proctor_max_tab_violations' => 2,
        ]);
        $exam->questionBanks()->attach($bank->id, ['pick_mode' => 'all', 'display_order' => 1]);

        [$user, $registration] = $this->registerStudent($exam);
        app(McqExamSessionService::class)->start($registration->fresh());

        $this->actingAs($user)
            ->postJson("/portal/student/{$this->school->id}/mcq/{$registration->id}/proctor-event", ['type' => 'tab_hidden'])
            ->assertOk()
            ->assertJson(['recorded' => true, 'auto_submitted' => false]);

        $this->assertSame(1, McqProctorEvent::where('registration_id', $registration->id)->count());
        $this->assertSame('started', $registration->fresh()->status);

        $this->actingAs($user)
            ->postJson("/portal/student/{$this->school->id}/mcq/{$registration->id}/proctor-event", ['type' => 'tab_hidden'])
            ->assertOk()
            ->assertJson([
                'recorded' => true,
                'auto_submitted' => true,
            ]);

        $registration->refresh();
        $this->assertSame('submitted', $registration->status);
        $this->assertSame('auto_tab', $registration->submit_mode);
        $this->assertNotNull($registration->mark);
    }

    public function test_school_admin_can_save_new_online_settings(): void
    {
        $admin = $this->userWithRole('school_admin', $this->school->id);
        $exam = $this->modelExam();

        $this->actingAs($admin)->put("/school-admin/{$this->school->id}/model-exams/{$exam->id}", [
            'title' => $exam->title,
            'status' => 'published',
            'delivery_mode' => 'online',
            'duration_minutes' => 40,
            'eligibility_config' => ['audience' => 'students', 'assignment_type' => 'all', 'gender' => 'open'],
            'require_fullscreen' => false,
            'venue_keycode_join' => true,
            'allow_reattempt' => true,
            'max_attempts' => 3,
            'proctor_mode' => 'strict_tab',
            'proctor_max_tab_violations' => 4,
            'result_visibility' => 'score_grade',
        ])->assertSessionHasNoErrors();

        $exam->refresh();
        $this->assertTrue($exam->allowsVenueKeycodeJoin());
        $this->assertTrue($exam->allowsReattempt());
        $this->assertSame(3, $exam->maxAttempts());
        $this->assertSame('strict_tab', $exam->proctorMode());
        $this->assertSame(4, $exam->proctorMaxTabViolations());
    }

    /** @param  array<string, mixed>  $settings */
    private function modelExam(array $settings = []): McqExam
    {
        return McqExam::create([
            'tenant_id' => $this->sahodaya->id,
            'school_id' => $this->school->id,
            'conductor_level' => 'school',
            'title' => 'Portal venue exam',
            'status' => 'published',
            'delivery_mode' => 'online',
            'fee_type' => 'none',
            'scheduled_at' => now()->subMinute(),
            'duration_minutes' => 30,
            'settings_json' => array_merge(McqProductMode::presetSettings(McqProductMode::SCHOOL_MODEL), [
                'require_verified_students' => false,
                'shuffle_questions' => false,
                'shuffle_options' => false,
            ], $settings),
        ]);
    }
}
