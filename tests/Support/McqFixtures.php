<?php

namespace Tests\Support;

use App\Models\McqExam;
use App\Models\McqQuestion;
use App\Models\McqQuestionBank;
use App\Models\McqRegistration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;

/** Shared Sahodaya / school / exam / bank fixtures for the online exam Phase 1 tests. */
trait McqFixtures
{
    protected Tenant $sahodaya;

    protected Tenant $school;

    protected function setUpMcqTenants(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->sahodaya = Tenant::create(['id' => (string) Str::uuid(), 'name' => 'Phase One Sahodaya', 'type' => 'sahodaya', 'is_active' => true]);
        $this->school = Tenant::create([
            'id' => (string) Str::uuid(), 'name' => 'Phase One School', 'type' => 'school',
            'parent_id' => $this->sahodaya->id, 'is_active' => true, 'membership_status' => 'approved',
        ]);
    }

    protected function userWithRole(string $role, ?string $tenantId = null): User
    {
        $user = User::factory()->create(['tenant_id' => $tenantId ?? $this->sahodaya->id, 'email_verified_at' => now()]);
        $user->assignRole($role);

        return $user;
    }

    protected function makeBank(string $status = 'active', string $title = 'Science bank'): McqQuestionBank
    {
        return McqQuestionBank::create([
            'sahodaya_id' => $this->sahodaya->id,
            'subject'     => 'Science',
            'title'       => $title,
            'status'      => $status,
        ]);
    }

    protected function addSingle(McqQuestionBank $bank, string $key = 'b', int $order = 1, float $marks = 1): McqQuestion
    {
        return $bank->questions()->create([
            'type'               => 'single',
            'body_text'          => "Question {$order}",
            'options_json'       => [['key' => 'a', 'label' => 'A'], ['key' => 'b', 'label' => 'B'], ['key' => 'c', 'label' => 'C']],
            'correct_option_key' => $key,
            'marks'              => $marks,
            'display_order'      => $order,
        ]);
    }

    protected function makeOnlineExam(array $attributes = []): McqExam
    {
        return McqExam::create(array_merge([
            'tenant_id'        => $this->sahodaya->id,
            'title'            => 'Online Talent Search',
            'status'           => 'published',
            'delivery_mode'    => 'online',
            'duration_minutes' => 60,
            'fee_type'         => 'none',
        ], $attributes));
    }

    /** @return array{0: User, 1: McqRegistration} a student portal login and their approved registration */
    protected function registerStudent(McqExam $exam): array
    {
        $user = $this->userWithRole('student', $this->school->id);
        $class = SchoolClass::firstOrCreate(['tenant_id' => $this->school->id, 'name' => 'Class 8']);
        $student = Student::create([
            'tenant_id' => $this->school->id, 'school_class_id' => $class->id,
            'name' => 'Asha Student '.Str::random(4), 'user_id' => $user->id,
        ]);

        $registration = McqRegistration::create([
            'exam_id'         => $exam->id,
            'school_id'       => $this->school->id,
            'student_id'      => $student->id,
            'status'          => 'registered',
            'approval_status' => 'approved',
        ]);

        return [$user, $registration];
    }
}
