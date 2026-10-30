<?php

namespace Tests\Feature;

use App\Models\McqExam;
use App\Models\McqQuestion;
use App\Models\McqRegistration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Mcq\McqConcurrencyLimitService;
use App\Services\Mcq\McqExamSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\McqFixtures;
use Tests\TestCase;

class McqConcurrencyLimitTest extends TestCase
{
    use McqFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMcqTenants();
    }

    private function makeSuperadmin(): User
    {
        $user = \App\Models\User::factory()->create(['tenant_id' => null, 'email_verified_at' => now()]);
        $user->assignRole('superadmin');

        return $user;
    }

    private function createOnlineExam(array $attributes = []): McqExam
    {
        $exam = McqExam::create(array_merge([
            'tenant_id'        => $this->sahodaya->id,
            'title'            => 'Concurrency Test Exam',
            'status'           => 'published',
            'delivery_mode'    => 'online',
            'duration_minutes' => 60,
            'fee_type'         => 'none',
        ], $attributes));

        $bank = $this->makeBank('active');
        $this->addSingle($bank, 'a');
        $exam->questionBanks()->attach($bank->id);

        return $exam;
    }

    private function createStudentRegistration(McqExam $exam, ?Tenant $school = null): McqRegistration
    {
        $school ??= $this->school;
        $user = $this->userWithRole('student', $school->id);
        $class = SchoolClass::firstOrCreate(['tenant_id' => $school->id, 'name' => 'Class 8']);
        $student = Student::create([
            'tenant_id' => $school->id,
            'school_class_id' => $class->id,
            'name' => 'Student '.uniqid(),
            'user_id' => $user->id,
        ]);

        return McqRegistration::create([
            'exam_id' => $exam->id,
            'school_id' => $school->id,
            'student_id' => $student->id,
            'status' => 'registered',
            'approval_status' => 'approved',
        ]);
    }

    public function test_superadmin_can_view_concurrency_settings_page(): void
    {
        $superadmin = $this->makeSuperadmin();

        $this->actingAs($superadmin)
            ->get('/admin/mcq/concurrency-settings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Mcq/Settings')
                ->has('globalSettings')
                ->has('liveStats')
                ->has('sahodayas')
            );
    }

    public function test_non_superadmin_cannot_access_concurrency_settings(): void
    {
        $schoolAdmin = $this->userWithRole('school_admin', $this->school->id);

        $this->actingAs($schoolAdmin)
            ->get('/admin/mcq/concurrency-settings')
            ->assertForbidden();
    }

    public function test_superadmin_can_update_global_concurrency_limits(): void
    {
        $superadmin = $this->makeSuperadmin();

        $this->actingAs($superadmin)
            ->put('/admin/mcq/concurrency-settings', [
                'enabled'                     => true,
                'max_concurrent_per_school'   => 50,
                'max_concurrent_per_sahodaya' => 500,
                'queue_message_school'        => 'Custom school queue :current/:max',
                'queue_message_sahodaya'      => 'Custom cluster queue :current/:max',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $service = app(McqConcurrencyLimitService::class);
        $settings = $service->globalSettings();

        $this->assertTrue($settings['enabled']);
        $this->assertSame(50, $settings['max_concurrent_per_school']);
        $this->assertSame(500, $settings['max_concurrent_per_sahodaya']);
        $this->assertSame('Custom school queue :current/:max', $settings['queue_message_school']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'platform.mcq.concurrency_limits.updated',
        ]);
    }

    public function test_superadmin_can_set_and_delete_tenant_override(): void
    {
        $superadmin = $this->makeSuperadmin();

        $this->actingAs($superadmin)
            ->put("/admin/mcq/concurrency-settings/override/{$this->sahodaya->id}", [
                'max_concurrent_per_school'   => 120,
                'max_concurrent_per_sahodaya' => 1200,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $service = app(McqConcurrencyLimitService::class);
        $override = $service->overrideForTenant($this->sahodaya->id);

        $this->assertNotNull($override);
        $this->assertSame(120, $override['max_concurrent_per_school']);
        $this->assertSame(1200, $override['max_concurrent_per_sahodaya']);

        // Delete override
        $this->actingAs($superadmin)
            ->delete("/admin/mcq/concurrency-settings/override/{$this->sahodaya->id}")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNull($service->overrideForTenant($this->sahodaya->id));
    }

    public function test_school_concurrency_limit_blocks_new_start_when_capacity_reached(): void
    {
        $service = app(McqConcurrencyLimitService::class);
        $service->saveGlobalSettings([
            'enabled'                     => true,
            'max_concurrent_per_school'   => 2,
            'max_concurrent_per_sahodaya' => 100,
            'queue_message_school'        => 'School cap reached (:current/:max writing).',
        ]);

        $exam = $this->createOnlineExam();

        // 2 candidates started and writing
        $reg1 = $this->createStudentRegistration($exam);
        $reg1->update(['status' => 'started', 'started_at' => now()->subMinutes(5)]);

        $reg2 = $this->createStudentRegistration($exam);
        $reg2->update(['status' => 'started', 'started_at' => now()->subMinutes(10)]);

        // 3rd candidate tries to start
        $reg3 = $this->createStudentRegistration($exam);
        $sessions = app(McqExamSessionService::class);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('School cap reached (2/2 writing).');

        $sessions->assertCanStart($reg3);
    }

    public function test_already_started_student_is_never_blocked_by_concurrency_limit(): void
    {
        $service = app(McqConcurrencyLimitService::class);
        $service->saveGlobalSettings([
            'enabled'                   => true,
            'max_concurrent_per_school' => 1,
        ]);

        $exam = $this->createOnlineExam();

        // Student is already started
        $reg = $this->createStudentRegistration($exam);
        $reg->update(['status' => 'started', 'started_at' => now()->subMinutes(15)]);

        $sessions = app(McqExamSessionService::class);

        // Does not throw
        $sessions->assertCanStart($reg);
        $this->assertTrue(true);
    }

    public function test_sahodaya_concurrency_limit_blocks_new_start_when_cluster_capacity_reached(): void
    {
        $service = app(McqConcurrencyLimitService::class);
        $service->saveGlobalSettings([
            'enabled'                     => true,
            'max_concurrent_per_school'   => 10,
            'max_concurrent_per_sahodaya' => 2,
            'queue_message_sahodaya'      => 'Cluster cap reached (:current/:max writing).',
        ]);

        $exam = $this->createOnlineExam();

        // School 1 has 1 student
        $reg1 = $this->createStudentRegistration($exam, $this->school);
        $reg1->update(['status' => 'started', 'started_at' => now()->subMinutes(5)]);

        // School 2 has 1 student
        $otherSchool = Tenant::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Other School',
            'type' => 'school',
            'parent_id' => $this->sahodaya->id,
            'is_active' => true,
        ]);
        $reg2 = $this->createStudentRegistration($exam, $otherSchool);
        $reg2->update(['status' => 'started', 'started_at' => now()->subMinutes(5)]);

        // 3rd candidate in School 1 tries to start
        $reg3 = $this->createStudentRegistration($exam, $this->school);
        $sessions = app(McqExamSessionService::class);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cluster cap reached (2/2 writing).');

        $sessions->assertCanStart($reg3);
    }

    public function test_tenant_override_takes_precedence_over_global_limits(): void
    {
        $service = app(McqConcurrencyLimitService::class);
        $service->saveGlobalSettings([
            'enabled'                   => true,
            'max_concurrent_per_school' => 1, // global cap = 1
        ]);

        // Sahodaya override cap = 5
        $service->saveTenantOverride($this->sahodaya->id, [
            'max_concurrent_per_school' => 5,
        ]);

        $exam = $this->createOnlineExam();

        // 1 student started
        $reg1 = $this->createStudentRegistration($exam);
        $reg1->update(['status' => 'started', 'started_at' => now()->subMinutes(5)]);

        // 2nd student can start because of Sahodaya override cap = 5
        $reg2 = $this->createStudentRegistration($exam);
        $sessions = app(McqExamSessionService::class);

        $sessions->assertCanStart($reg2);
        $this->assertTrue(true);
    }
}
