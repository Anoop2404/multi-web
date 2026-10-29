<?php

namespace Tests\Feature;

use App\Models\McqExam;
use App\Models\McqQuestion;
use App\Models\McqRegistration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\Mcq\McqExamSessionService;
use App\Support\Mcq\McqProductMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\McqFixtures;
use Tests\TestCase;

/** Portal in-app path supports multiple-correct and numeric answers (not engine-only). */
class McqPortalMultiAnswerTest extends TestCase
{
    use McqFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMcqTenants();
    }

    public function test_portal_can_start_and_submit_multiple_and_numeric_paper(): void
    {
        $admin = $this->userWithRole('school_admin', $this->school->id);
        $bank = $this->makeBank('active', 'Multi bank');
        $bank->update(['school_id' => $this->school->id]);

        $multi = $bank->questions()->create([
            'type' => McqQuestion::TYPE_MULTIPLE,
            'body_text' => 'Pick two',
            'options_json' => [
                ['key' => 'a', 'label' => 'A'],
                ['key' => 'b', 'label' => 'B'],
                ['key' => 'c', 'label' => 'C'],
            ],
            'answer_json' => ['keys' => ['a', 'b'], 'show_count' => true],
            'marks' => 2,
            'display_order' => 1,
        ]);
        $numeric = $bank->questions()->create([
            'type' => McqQuestion::TYPE_NUMERIC,
            'body_text' => 'What is 3+4?',
            'options_json' => [],
            'answer_json' => ['value' => 7, 'tolerance' => 0],
            'marks' => 1,
            'display_order' => 2,
        ]);

        $class = SchoolClass::firstOrCreate(['tenant_id' => $this->school->id, 'name' => 'Class 8']);
        $studentUser = $this->userWithRole('student', $this->school->id);
        $student = Student::create([
            'tenant_id' => $this->school->id,
            'school_class_id' => $class->id,
            'name' => 'Multi Student',
            'user_id' => $studentUser->id,
        ]);

        $exam = McqExam::create([
            'tenant_id' => $this->sahodaya->id,
            'school_id' => $this->school->id,
            'conductor_level' => 'school',
            'title' => 'Multi numeric model',
            'status' => 'published',
            'delivery_mode' => 'online',
            'fee_type' => 'none',
            'scheduled_at' => now()->subMinute(),
            'duration_minutes' => 30,
            'settings_json' => array_merge(McqProductMode::presetSettings(McqProductMode::SCHOOL_MODEL), [
                'require_verified_students' => false,
            ]),
        ]);
        $exam->questionBanks()->attach($bank->id, ['pick_mode' => 'all', 'display_order' => 1]);

        $registration = McqRegistration::create([
            'exam_id' => $exam->id,
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'status' => 'approved',
            'approval_status' => 'approved',
        ]);

        $sessions = app(McqExamSessionService::class);
        $this->assertTrue($sessions->canTakeOnline($registration));

        $this->actingAs($studentUser)
            ->post("/portal/student/{$this->school->id}/mcq/{$registration->id}/start")
            ->assertRedirect();

        $registration->refresh();
        $this->assertNotNull($registration->started_at);

        $paper = $sessions->paperForStudent($registration);
        $this->assertSame('multiple', collect($paper)->firstWhere('id', $multi->id)['type']);
        $this->assertSame(2, collect($paper)->firstWhere('id', $multi->id)['choose_count']);
        $this->assertSame('numeric', collect($paper)->firstWhere('id', $numeric->id)['type']);

        $this->actingAs($studentUser)
            ->postJson("/portal/student/{$this->school->id}/mcq/{$registration->id}/save-answers", [
                'answers' => [
                    (string) $multi->id => ['a', 'b'],
                    (string) $numeric->id => '7',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('saved', true);

        $draft = $registration->fresh()->draft_answers;
        $this->assertEqualsCanonicalizing(['a', 'b'], $draft[(string) $multi->id]);
        $this->assertSame('7', $draft[(string) $numeric->id]);

        $this->actingAs($studentUser)
            ->post("/portal/student/{$this->school->id}/mcq/{$registration->id}/submit", [
                'answers' => [
                    (string) $multi->id => ['a', 'b'],
                    (string) $numeric->id => '7',
                ],
            ])
            ->assertRedirect();

        $registration->refresh();
        $this->assertSame('submitted', $registration->status);
        $this->assertEquals(3.0, (float) $registration->mark->score);
    }
}
