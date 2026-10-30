<?php

namespace Tests\Feature;

use App\Models\McqCertificate;
use App\Models\McqExam;
use App\Models\McqMark;
use App\Models\McqRegistration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\Mcq\McqCertificateService;
use App\Services\Mcq\McqResultPublishService;
use App\Support\Mcq\McqProductMode;
use App\Support\Mcq\McqResultPresenter;
use App\Support\Mcq\McqResultReleaseSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\McqFixtures;
use Tests\TestCase;

class McqResultReleaseSettingsTest extends TestCase
{
    use McqFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMcqTenants();
    }

    private function schoolBase(): string
    {
        return "/school-admin/{$this->school->id}/model-exams";
    }

    public function test_school_can_save_result_visibility_and_certificate_settings(): void
    {
        $admin = $this->userWithRole('school_admin', $this->school->id);
        $exam = McqExam::create([
            'tenant_id' => $this->sahodaya->id,
            'school_id' => $this->school->id,
            'conductor_level' => 'school',
            'title' => 'Settings exam',
            'status' => 'published',
            'delivery_mode' => 'online',
            'fee_type' => 'none',
            'settings_json' => McqProductMode::presetSettings(McqProductMode::SCHOOL_MODEL),
        ]);

        $this->actingAs($admin)->put("{$this->schoolBase()}/{$exam->id}", [
            'title' => 'Settings exam',
            'status' => 'published',
            'delivery_mode' => 'online',
            'eligibility_config' => ['audience' => 'students', 'assignment_type' => 'all', 'gender' => 'open'],
            'result_visibility' => 'score',
            'results_auto_release_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'certificates_enabled' => true,
            'certificates_auto_issue' => true,
            'certificate_eligibility' => 'pass',
            'pass_mark' => 40,
        ])->assertSessionHas('success');

        $exam->refresh();
        $this->assertSame('score', McqResultReleaseSettings::visibility($exam));
        $this->assertTrue(McqResultReleaseSettings::certificatesEnabled($exam));
        $this->assertTrue(McqResultReleaseSettings::autoIssueOnPublish($exam));
        $this->assertSame('pass', McqResultReleaseSettings::certificates($exam)['eligibility']);
        $this->assertNotNull(McqResultReleaseSettings::autoReleaseAt($exam));
    }

    public function test_presenter_hides_grade_for_score_only_visibility(): void
    {
        $exam = McqExam::create([
            'tenant_id' => $this->sahodaya->id,
            'school_id' => $this->school->id,
            'conductor_level' => 'school',
            'title' => 'Score only',
            'status' => 'completed',
            'delivery_mode' => 'online',
            'fee_type' => 'none',
            'results_published' => true,
            'settings_json' => array_merge(McqProductMode::presetSettings(McqProductMode::SCHOOL_MODEL), [
                'result_release' => ['visibility' => 'score'],
            ]),
        ]);

        $class = SchoolClass::firstOrCreate(['tenant_id' => $this->school->id, 'name' => 'Class 8']);
        $student = Student::create([
            'tenant_id' => $this->school->id,
            'school_class_id' => $class->id,
            'name' => 'Score Student',
        ]);
        $reg = McqRegistration::create([
            'exam_id' => $exam->id,
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'approval_status' => 'approved',
        ]);
        McqMark::create([
            'registration_id' => $reg->id,
            'score' => 18,
            'percentage' => 72,
            'grade' => 'A',
            'rank' => 2,
            'correct_count' => 18,
            'wrong_count' => 7,
            'unanswered_count' => 0,
            'answers_json' => [
                ['question_id' => 1, 'chosen' => 'b', 'correct' => 'b', 'is_correct' => true],
            ],
        ]);

        $payload = McqResultPresenter::forRegistration($reg->fresh(['exam', 'mark']));
        $this->assertSame(18.0, (float) $payload['score']);
        $this->assertArrayNotHasKey('grade', $payload);
        $this->assertArrayNotHasKey('rank', $payload);
        $this->assertArrayNotHasKey('answers_json', $payload);

        $exam->update([
            'settings_json' => array_merge($exam->settings_json ?? [], [
                'result_release' => ['visibility' => 'review'],
            ]),
        ]);
        $payload = McqResultPresenter::forRegistration($reg->fresh(['exam', 'mark']));
        $this->assertSame('A', $payload['grade']);
        $this->assertNotEmpty($payload['answers_json']);
    }

    public function test_held_results_message_vs_not_submitted(): void
    {
        $exam = McqExam::create([
            'tenant_id' => $this->sahodaya->id,
            'school_id' => $this->school->id,
            'conductor_level' => 'school',
            'title' => 'Held',
            'status' => 'published',
            'delivery_mode' => 'online',
            'fee_type' => 'none',
            'results_published' => false,
            'settings_json' => McqProductMode::presetSettings(McqProductMode::SCHOOL_MODEL),
        ]);
        $class = SchoolClass::firstOrCreate(['tenant_id' => $this->school->id, 'name' => 'Class 8']);
        $student = Student::create([
            'tenant_id' => $this->school->id,
            'school_class_id' => $class->id,
            'name' => 'Held Student',
        ]);
        $submitted = McqRegistration::create([
            'exam_id' => $exam->id,
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'approval_status' => 'approved',
        ]);
        $pending = McqRegistration::create([
            'exam_id' => $exam->id,
            'school_id' => $this->school->id,
            'student_id' => Student::create([
                'tenant_id' => $this->school->id,
                'school_class_id' => $class->id,
                'name' => 'Not started',
            ])->id,
            'status' => 'approved',
            'approval_status' => 'approved',
        ]);

        $held = McqResultPresenter::forExamList($exam, $submitted);
        $this->assertSame('held', $held['result_state']);
        $this->assertSame('Results not released yet.', $held['result_message']);

        $open = McqResultPresenter::forExamList($exam, $pending);
        $this->assertSame('not_submitted', $open['result_state']);
    }

    public function test_certificate_eligibility_pass_and_disabled_for_school_model(): void
    {
        $exam = McqExam::create([
            'tenant_id' => $this->sahodaya->id,
            'school_id' => $this->school->id,
            'conductor_level' => 'school',
            'title' => 'Cert rules',
            'status' => 'completed',
            'delivery_mode' => 'online',
            'fee_type' => 'none',
            'results_published' => true,
            'pass_mark' => 50,
            'settings_json' => array_merge(McqProductMode::presetSettings(McqProductMode::SCHOOL_MODEL), [
                'certificates' => [
                    'enabled' => true,
                    'auto_issue_on_publish' => false,
                    'eligibility' => 'pass',
                    'eligible_grades' => [],
                ],
            ]),
        ]);
        $class = SchoolClass::firstOrCreate(['tenant_id' => $this->school->id, 'name' => 'Class 8']);
        $student = Student::create([
            'tenant_id' => $this->school->id,
            'school_class_id' => $class->id,
            'name' => 'Cert Student',
        ]);
        $reg = McqRegistration::create([
            'exam_id' => $exam->id,
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'approval_status' => 'approved',
            'attendance_status' => 'present',
        ]);
        McqMark::create([
            'registration_id' => $reg->id,
            'score' => 10,
            'percentage' => 40,
            'grade' => 'D',
            'correct_count' => 10,
            'wrong_count' => 15,
            'unanswered_count' => 0,
        ]);

        $service = app(McqCertificateService::class);
        $this->expectException(ValidationException::class);
        $service->assertEligible($reg->fresh(['exam', 'mark']));
    }

    public function test_publish_auto_issues_certificates_when_configured(): void
    {
        $exam = McqExam::create([
            'tenant_id' => $this->sahodaya->id,
            'school_id' => $this->school->id,
            'conductor_level' => 'school',
            'title' => 'Auto cert',
            'status' => 'completed',
            'delivery_mode' => 'online',
            'fee_type' => 'none',
            'results_published' => false,
            'settings_json' => array_merge(McqProductMode::presetSettings(McqProductMode::SCHOOL_MODEL), [
                'certificates' => [
                    'enabled' => true,
                    'auto_issue_on_publish' => true,
                    'eligibility' => 'participation',
                    'eligible_grades' => [],
                ],
            ]),
        ]);
        $class = SchoolClass::firstOrCreate(['tenant_id' => $this->school->id, 'name' => 'Class 8']);
        $student = Student::create([
            'tenant_id' => $this->school->id,
            'school_class_id' => $class->id,
            'name' => 'Auto Cert Student',
        ]);
        $reg = McqRegistration::create([
            'exam_id' => $exam->id,
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'approval_status' => 'approved',
            'attendance_status' => 'present',
        ]);
        McqMark::create([
            'registration_id' => $reg->id,
            'score' => 20,
            'percentage' => 80,
            'grade' => 'A',
            'correct_count' => 20,
            'wrong_count' => 5,
            'unanswered_count' => 0,
        ]);

        $result = app(McqResultPublishService::class)->publish($exam);
        $this->assertTrue($exam->fresh()->results_published);
        $this->assertSame(1, $result['certificates_issued']);
        $this->assertTrue(McqCertificate::where('registration_id', $reg->id)->exists());
    }

    public function test_auto_release_publishes_when_due(): void
    {
        $exam = McqExam::create([
            'tenant_id' => $this->sahodaya->id,
            'school_id' => $this->school->id,
            'conductor_level' => 'school',
            'title' => 'Auto release',
            'status' => 'completed',
            'delivery_mode' => 'online',
            'fee_type' => 'none',
            'results_published' => false,
            'settings_json' => array_merge(McqProductMode::presetSettings(McqProductMode::SCHOOL_MODEL), [
                'result_release' => [
                    'visibility' => 'score_grade',
                    'auto_release_at' => now()->subMinute()->toIso8601String(),
                ],
            ]),
        ]);

        $released = app(McqResultPublishService::class)->releaseDueExams();
        $this->assertSame(1, $released);
        $this->assertTrue($exam->fresh()->results_published);
    }

    public function test_talent_search_certificates_remain_enabled_by_default(): void
    {
        $exam = McqExam::create([
            'tenant_id' => $this->sahodaya->id,
            'title' => 'TS default certs',
            'status' => 'published',
            'delivery_mode' => 'offline',
            'fee_type' => 'flat',
            'fee_amount' => 100,
            'results_published' => true,
        ]);

        $this->assertTrue(McqResultReleaseSettings::certificatesEnabled($exam));
        $this->assertFalse(McqResultReleaseSettings::certificatesEnabled(McqExam::make([
            'conductor_level' => 'school',
            'settings_json' => McqProductMode::presetSettings(McqProductMode::SCHOOL_MODEL),
        ])));
    }

    public function test_admin_can_save_release_mode_settings(): void
    {
        $admin = $this->userWithRole('school_admin', $this->school->id);
        $exam = McqExam::create([
            'tenant_id' => $this->sahodaya->id,
            'school_id' => $this->school->id,
            'conductor_level' => 'school',
            'title' => 'Release mode exam',
            'status' => 'published',
            'delivery_mode' => 'online',
            'fee_type' => 'none',
            'settings_json' => McqProductMode::presetSettings(McqProductMode::SCHOOL_MODEL),
        ]);

        $this->actingAs($admin)->put("{$this->schoolBase()}/{$exam->id}", [
            'title' => 'Release mode exam',
            'status' => 'published',
            'delivery_mode' => 'online',
            'results_release_mode' => 'after_completion',
            'result_visibility' => 'score_grade',
        ])->assertSessionHas('success');

        $exam->refresh();
        $this->assertSame('after_completion', McqResultReleaseSettings::releaseMode($exam));

        // Switch back to on_publish
        $this->actingAs($admin)->put("{$this->schoolBase()}/{$exam->id}", [
            'title' => 'Release mode exam',
            'status' => 'published',
            'delivery_mode' => 'online',
            'results_release_mode' => 'on_publish',
            'result_visibility' => 'score_grade',
        ])->assertSessionHas('success');

        $exam->refresh();
        $this->assertSame('on_publish', McqResultReleaseSettings::releaseMode($exam));
    }

    public function test_results_visibility_respects_release_mode_on_publish_vs_after_completion(): void
    {
        $exam = McqExam::create([
            'tenant_id' => $this->sahodaya->id,
            'school_id' => $this->school->id,
            'conductor_level' => 'school',
            'title' => 'Instant vs Publish',
            'status' => 'published',
            'delivery_mode' => 'online',
            'fee_type' => 'none',
            'results_published' => false,
            'settings_json' => array_merge(McqProductMode::presetSettings(McqProductMode::SCHOOL_MODEL), [
                'result_release' => [
                    'release_mode' => 'on_publish',
                    'visibility' => 'score_grade',
                ],
            ]),
        ]);

        $class = SchoolClass::firstOrCreate(['tenant_id' => $this->school->id, 'name' => 'Class 8']);
        $student = Student::create([
            'tenant_id' => $this->school->id,
            'school_class_id' => $class->id,
            'name' => 'Instant Student',
        ]);
        $reg = McqRegistration::create([
            'exam_id' => $exam->id,
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'approval_status' => 'approved',
        ]);
        McqMark::create([
            'registration_id' => $reg->id,
            'score' => 25,
            'percentage' => 83.33,
            'grade' => 'A+',
            'correct_count' => 25,
            'wrong_count' => 5,
            'unanswered_count' => 0,
        ]);

        // In on_publish mode with results_published=false: results are held
        $this->assertFalse(McqResultReleaseSettings::isResultVisible($exam, $reg));
        $held = McqResultPresenter::forRegistration($reg->fresh(['exam', 'mark']));
        $this->assertSame('held', $held['result_state']);
        $this->assertSame('Results not released yet.', $held['result_message']);

        // Switch to after_completion: results become immediately visible upon submission
        $exam->update([
            'settings_json' => array_merge($exam->settings_json, [
                'result_release' => [
                    'release_mode' => 'after_completion',
                    'visibility' => 'score_grade',
                ],
            ]),
        ]);

        $this->assertTrue(McqResultReleaseSettings::isResultVisible($exam, $reg));
        $released = McqResultPresenter::forRegistration($reg->fresh(['exam', 'mark']));
        $this->assertSame('released', $released['result_state']);
        $this->assertSame(25.0, (float) $released['score']);
        $this->assertSame('A+', $released['grade']);
    }
}
