<?php

namespace Tests\Feature;

use App\Models\FestEvent;
use App\Models\FestEventStaff;
use App\Models\McqExam;
use App\Models\McqMark;
use App\Models\McqRegistration;
use App\Models\SahodayaProfile;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\FestQrVerificationService;
use App\Support\Mcq\McqResultPresenter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class GoLiveReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_list_boots_cleanly(): void
    {
        $this->artisan('route:list')->assertExitCode(0);
    }

    public function test_plain_password_not_mass_assignable(): void
    {
        $user = new User;
        $this->assertNotContains('plain_password', $user->getFillable());
    }

    public function test_inactive_tenant_blocks_school_admin_routes(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Inactive Sahodaya',
            'is_active' => true,
        ]);

        $school = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'school',
            'name'      => 'Inactive School',
            'parent_id' => $sahodaya->id,
            'is_active' => false,
        ]);

        $admin = User::factory()->create([
            'tenant_id'         => $school->id,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('school_admin');

        $this->actingAs($admin)
            ->get("/school-admin/{$school->id}")
            ->assertForbidden();
    }

    public function test_mcq_result_presenter_hides_scores_before_publish(): void
    {
        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'MCQ Sahodaya',
            'is_active' => true,
        ]);

        $school = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'school',
            'name'      => 'MCQ School',
            'parent_id' => $sahodaya->id,
            'is_active' => true,
        ]);

        $class = \App\Models\SchoolClass::create([
            'tenant_id'     => $school->id,
            'name'          => '10',
            'display_order' => 10,
        ]);

        $student = Student::create([
            'tenant_id'       => $school->id,
            'school_class_id' => $class->id,
            'name'            => 'Exam Student',
            'status'          => 'active',
        ]);

        $exam = McqExam::create([
            'tenant_id'         => $sahodaya->id,
            'title'             => 'Sample Exam',
            'status'            => 'completed',
            'results_published' => false,
        ]);

        $registration = McqRegistration::create([
            'exam_id'    => $exam->id,
            'school_id'  => $school->id,
            'student_id' => $student->id,
            'status'     => 'submitted',
            'submitted_at' => now(),
        ]);

        McqMark::create([
            'registration_id' => $registration->id,
            'score'           => 95,
            'percentage'      => 95,
            'grade'           => 'A',
        ]);

        $registration->load('exam', 'mark');
        $presented = McqResultPresenter::forRegistration($registration, $registration->mark);

        $this->assertTrue($presented['pending'] ?? false);
        $this->assertArrayNotHasKey('score', $presented);
    }

    public function test_qr_scan_detects_duplicate_within_ttl(): void
    {
        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'QR Sahodaya',
            'is_active' => true,
        ]);

        $event = FestEvent::create([
            'tenant_id'  => $sahodaya->id,
            'title'      => 'QR Fest',
            'event_type' => 'kalolsavam',
            'status'     => 'ongoing',
        ]);

        $staffUser = User::factory()->create(['tenant_id' => $sahodaya->id]);
        $staff = FestEventStaff::create([
            'event_id' => $event->id,
            'user_id'  => $staffUser->id,
            'duty'     => 'gate',
        ]);

        $service = app(FestQrVerificationService::class);
        $payload = "FEST|{$event->id}|staff|{$staff->id}|STAFF-001";

        $first = $service->verify($event, $payload);
        $second = $service->verify($event, $payload);

        $this->assertFalse($first['duplicate']);
        $this->assertTrue($first['valid']);
        $this->assertTrue($second['duplicate']);
    }

    public function test_house_admin_cannot_access_other_school_portal(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'House Sahodaya',
            'is_active' => true,
        ]);

        $schoolA = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'school',
            'name'      => 'School A',
            'parent_id' => $sahodaya->id,
            'is_active' => true,
        ]);

        $schoolB = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'school',
            'name'      => 'School B',
            'parent_id' => $sahodaya->id,
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'tenant_id'       => $schoolA->id,
            'school_house_id' => 1,
        ]);
        $user->assignRole('house_admin');

        $this->actingAs($user)
            ->get("/portal/house-admin/{$schoolB->id}")
            ->assertForbidden();
    }
}
