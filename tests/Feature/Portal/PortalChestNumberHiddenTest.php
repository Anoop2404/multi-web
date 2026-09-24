<?php

namespace Tests\Feature\Portal;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Same chest-number-hiding rule as SchoolReportsHideChestNumberTest, applied to the
 * Student and Teacher portals: StudentDashboardController::festDaySlots()/festResults()
 * and TeacherDashboardController's equivalents hard-code chest_no to null on every
 * response for Sahodaya events, regardless of the event's chest_reveal_mode.
 */
class PortalChestNumberHiddenTest extends TestCase
{
    use RefreshDatabase;

    private const CHEST_NO = 4217;

    /** @return array{sahodaya: Tenant, school: Tenant, event: FestEvent, schoolClass: SchoolClass} */
    private function baseFixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Chest Hide Portal Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'CH', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Chest Hide Portal School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Chest Hide Portal Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open', 'results_published' => true,
        ]);

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10']);

        return compact('sahodaya', 'school', 'event', 'schoolClass');
    }

    private function registerParticipant(FestEvent $event, Tenant $school, ?int $studentId = null, ?int $teacherId = null): void
    {
        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Solo Song', 'participant_type' => 'individual', 'is_enabled' => true,
        ]);

        $registration = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved',
        ]);

        FestParticipant::create(array_filter([
            'registration_id' => $registration->id,
            'student_id' => $studentId,
            'teacher_id' => $teacherId,
            'participant_role' => 'main',
            'chest_no' => self::CHEST_NO,
        ]));
    }

    public function test_student_dashboard_does_not_leak_chest_number(): void
    {
        $f = $this->baseFixture();

        $user = User::factory()->create(['tenant_id' => $f['school']->id]);
        $user->assignRole('student');
        $student = Student::create([
            'tenant_id' => $f['school']->id, 'user_id' => $user->id, 'school_class_id' => $f['schoolClass']->id,
            'name' => 'Chest Hide Student', 'admission_no' => 'CHS1',
        ]);
        $this->registerParticipant($f['event'], $f['school'], studentId: $student->id);

        $response = $this->actingAs($user)->get(
            route('portal.student.dashboard', ['tenantId' => $f['school']->id]),
        );

        $response->assertOk();
        $response->assertDontSee(self::CHEST_NO);
    }

    public function test_student_fest_schedule_does_not_leak_chest_number(): void
    {
        $f = $this->baseFixture();

        $user = User::factory()->create(['tenant_id' => $f['school']->id]);
        $user->assignRole('student');
        $student = Student::create([
            'tenant_id' => $f['school']->id, 'user_id' => $user->id, 'school_class_id' => $f['schoolClass']->id,
            'name' => 'Chest Hide Student', 'admission_no' => 'CHS1',
        ]);
        $this->registerParticipant($f['event'], $f['school'], studentId: $student->id);

        $response = $this->actingAs($user)->get(
            route('portal.student.fest.schedule', ['tenantId' => $f['school']->id]),
        );

        $response->assertOk();
        $response->assertDontSee(self::CHEST_NO);
    }

    public function test_student_results_does_not_leak_chest_number(): void
    {
        $f = $this->baseFixture();

        $user = User::factory()->create(['tenant_id' => $f['school']->id]);
        $user->assignRole('student');
        $student = Student::create([
            'tenant_id' => $f['school']->id, 'user_id' => $user->id, 'school_class_id' => $f['schoolClass']->id,
            'name' => 'Chest Hide Student', 'admission_no' => 'CHS1',
        ]);
        $this->registerParticipant($f['event'], $f['school'], studentId: $student->id);

        $response = $this->actingAs($user)->get(
            route('portal.student.results', ['tenantId' => $f['school']->id]),
        );

        $response->assertOk();
        $response->assertDontSee(self::CHEST_NO);
    }

    public function test_teacher_dashboard_does_not_leak_chest_number(): void
    {
        $f = $this->baseFixture();

        $user = User::factory()->create(['tenant_id' => $f['school']->id]);
        $user->assignRole('teacher');
        $teacher = Teacher::create([
            'tenant_id' => $f['school']->id, 'user_id' => $user->id, 'name' => 'Chest Hide Teacher',
        ]);
        $this->registerParticipant($f['event'], $f['school'], teacherId: $teacher->id);

        $response = $this->actingAs($user)->get(
            route('portal.teacher.dashboard', ['tenantId' => $f['school']->id]),
        );

        $response->assertOk();
        $response->assertDontSee(self::CHEST_NO);
    }

    public function test_teacher_fest_schedule_does_not_leak_chest_number(): void
    {
        $f = $this->baseFixture();

        $user = User::factory()->create(['tenant_id' => $f['school']->id]);
        $user->assignRole('teacher');
        $teacher = Teacher::create([
            'tenant_id' => $f['school']->id, 'user_id' => $user->id, 'name' => 'Chest Hide Teacher',
        ]);
        $this->registerParticipant($f['event'], $f['school'], teacherId: $teacher->id);

        $response = $this->actingAs($user)->get(
            route('portal.teacher.fest.schedule', ['tenantId' => $f['school']->id]),
        );

        $response->assertOk();
        $response->assertDontSee(self::CHEST_NO);
    }

    public function test_teacher_results_does_not_leak_chest_number(): void
    {
        $f = $this->baseFixture();

        $user = User::factory()->create(['tenant_id' => $f['school']->id]);
        $user->assignRole('teacher');
        $teacher = Teacher::create([
            'tenant_id' => $f['school']->id, 'user_id' => $user->id, 'name' => 'Chest Hide Teacher',
        ]);
        $this->registerParticipant($f['event'], $f['school'], teacherId: $teacher->id);

        $response = $this->actingAs($user)->get(
            route('portal.teacher.results', ['tenantId' => $f['school']->id]),
        );

        $response->assertOk();
        $response->assertDontSee(self::CHEST_NO);
    }
}
