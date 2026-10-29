<?php

namespace Tests\Feature;

use App\Models\McqExam;
use App\Models\McqQuestionBank;
use App\Models\McqRegistration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Mcq\McqExamSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\McqFixtures;
use Tests\TestCase;

/** Phase 1 school model exams: conductor_level=school, slim enroll, publish/results. */
class McqSchoolModelExamTest extends TestCase
{
    use McqFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMcqTenants();
    }

    private function base(): string
    {
        return "/school-admin/{$this->school->id}/model-exams";
    }

    private function schoolAdmin()
    {
        return $this->userWithRole('school_admin', $this->school->id);
    }

    public function test_school_creates_scoped_bank_and_model_exam(): void
    {
        $admin = $this->schoolAdmin();

        $this->actingAs($admin)->post("{$this->base()}/question-banks", [
            'title' => 'Class 8 Science bank', 'subject' => 'Science',
        ])->assertRedirect();

        $bank = McqQuestionBank::where('title', 'Class 8 Science bank')->firstOrFail();
        $this->assertSame($this->school->id, $bank->school_id);
        $this->assertSame($this->sahodaya->id, $bank->sahodaya_id);

        $this->actingAs($admin)->post("{$this->base()}/question-banks/{$bank->id}/questions", [
            'type' => 'single', 'body_text' => '2+2?',
            'options' => [['key' => 'a', 'label' => '3'], ['key' => 'b', 'label' => '4']],
            'correct_keys' => ['b'], 'marks' => 1,
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->put("{$this->base()}/question-banks/{$bank->id}", [
            'title' => 'Class 8 Science bank', 'subject' => 'Science', 'status' => 'active',
        ])->assertSessionHas('success');

        $this->actingAs($admin)->post($this->base(), [
            'title' => 'Science model exam',
            'delivery_mode' => 'online',
            'duration_minutes' => 45,
            'eligibility_config' => ['audience' => 'students', 'assignment_type' => 'all', 'gender' => 'open'],
        ])->assertRedirect();

        $exam = McqExam::where('title', 'Science model exam')->firstOrFail();
        $this->assertSame('school', $exam->conductor_level);
        $this->assertSame($this->school->id, $exam->school_id);
        $this->assertSame($this->sahodaya->id, $exam->tenant_id);
        $this->assertSame('none', $exam->fee_type);
        $this->assertFalse($exam->requiresHallTicket());
        $this->assertSame('partial', $exam->multiMarkingMode());
        $this->assertTrue($exam->isSchoolModelExam());
    }

    public function test_school_banks_are_isolated_between_schools(): void
    {
        $otherSchool = Tenant::create([
            'id' => (string) Str::uuid(), 'name' => 'Other School', 'type' => 'school',
            'parent_id' => $this->sahodaya->id, 'is_active' => true, 'membership_status' => 'approved',
        ]);

        $bank = McqQuestionBank::create([
            'sahodaya_id' => $this->sahodaya->id,
            'school_id'   => $otherSchool->id,
            'subject'     => 'Maths',
            'title'       => 'Other school bank',
            'status'      => 'active',
        ]);

        $admin = $this->schoolAdmin();
        $this->actingAs($admin)->get("{$this->base()}/question-banks/{$bank->id}")->assertNotFound();

        $exam = McqExam::create([
            'tenant_id' => $this->sahodaya->id,
            'school_id' => $this->school->id,
            'conductor_level' => 'school',
            'title' => 'Own exam',
            'status' => 'draft',
            'delivery_mode' => 'online',
            'fee_type' => 'none',
            'settings_json' => ['product_mode' => 'school_model_exam', 'requires_hall_ticket' => false],
        ]);

        $this->actingAs($admin)->post("{$this->base()}/{$exam->id}/paper/banks", ['bank_id' => $bank->id])
            ->assertNotFound();
    }

    public function test_publish_auto_enrolls_and_student_can_take_online_exam(): void
    {
        $admin = $this->schoolAdmin();
        $bank = $this->makeBank('active', 'School bank');
        $bank->update(['school_id' => $this->school->id]);
        $this->addSingle($bank, 'b', 1, 2);

        $class = SchoolClass::firstOrCreate(['tenant_id' => $this->school->id, 'name' => 'Class 8']);
        $studentUser = $this->userWithRole('student', $this->school->id);
        $student = Student::create([
            'tenant_id' => $this->school->id,
            'school_class_id' => $class->id,
            'name' => 'Model Exam Student',
            'user_id' => $studentUser->id,
        ]);

        $this->actingAs($admin)->post($this->base(), [
            'title' => 'Timed model exam',
            'delivery_mode' => 'online',
            'duration_minutes' => 30,
            'scheduled_at' => now()->subMinute()->toDateTimeString(),
            'eligibility_config' => ['audience' => 'students', 'assignment_type' => 'all', 'gender' => 'open'],
        ])->assertRedirect();

        $exam = McqExam::where('title', 'Timed model exam')->firstOrFail();
        $this->actingAs($admin)->post("{$this->base()}/{$exam->id}/paper/banks", ['bank_id' => $bank->id])
            ->assertSessionHas('success');

        $this->actingAs($admin)->post("{$this->base()}/{$exam->id}/publish", ['auto_enroll' => true])
            ->assertSessionHas('success');

        $exam->refresh();
        $this->assertSame('published', $exam->status);

        $registration = McqRegistration::where('exam_id', $exam->id)->where('student_id', $student->id)->firstOrFail();
        $this->assertSame('approved', $registration->approval_status);
        $this->assertNull($registration->hall_ticket_no);

        $this->actingAs($studentUser)
            ->post("/portal/student/{$this->school->id}/mcq/{$registration->id}/start")
            ->assertRedirect();

        $registration->refresh();
        $this->assertNotNull($registration->started_at);

        $this->actingAs($studentUser)
            ->post("/portal/student/{$this->school->id}/mcq/{$registration->id}/submit", [
                'answers' => [(string) $bank->questions()->first()->id => 'b'],
            ])
            ->assertRedirect();

        $registration->refresh();
        $this->assertSame('submitted', $registration->status);

        $this->actingAs($admin)->post("{$this->base()}/{$exam->id}/publish-results")
            ->assertSessionHas('success');
        $this->assertTrue($exam->fresh()->results_published);

        $mark = $registration->fresh()->mark;
        $this->assertNotNull($mark);
        $this->assertEquals(2, (float) $mark->score);
    }

    public function test_talent_search_hub_excludes_school_model_exams(): void
    {
        McqExam::create([
            'tenant_id' => $this->sahodaya->id,
            'school_id' => $this->school->id,
            'conductor_level' => 'school',
            'title' => 'Hidden school exam',
            'status' => 'published',
            'delivery_mode' => 'online',
            'fee_type' => 'none',
        ]);

        $this->makeOnlineExam(['title' => 'Visible Talent Search', 'status' => 'published']);

        $admin = $this->schoolAdmin();
        $response = $this->actingAs($admin)->get("/school-admin/{$this->school->id}/mcq");
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('School/Mcq/Index', false)
            ->where('standaloneExams', fn ($exams) => collect($exams)->pluck('title')->contains('Visible Talent Search')
                && ! collect($exams)->pluck('title')->contains('Hidden school exam'))
        );
    }

    public function test_session_service_allows_school_model_exam_without_hall_ticket(): void
    {
        $exam = McqExam::create([
            'tenant_id' => $this->sahodaya->id,
            'school_id' => $this->school->id,
            'conductor_level' => 'school',
            'title' => 'No ticket exam',
            'status' => 'published',
            'delivery_mode' => 'online',
            'fee_type' => 'none',
            'scheduled_at' => now()->subMinute(),
            'duration_minutes' => 30,
            'settings_json' => [
                'requires_hall_ticket' => false,
                'require_verified_students' => false,
                'multi_marking' => 'partial',
            ],
        ]);
        $bank = $this->makeBank();
        $bank->update(['school_id' => $this->school->id]);
        $this->addSingle($bank);
        $exam->questionBanks()->attach($bank->id, ['pick_mode' => 'all', 'display_order' => 1]);

        [$user, $registration] = $this->registerStudent($exam);

        app(McqExamSessionService::class)->assertCanStart($registration->fresh(['exam', 'student.schoolClass']));
        $this->assertTrue(true);
    }
}
