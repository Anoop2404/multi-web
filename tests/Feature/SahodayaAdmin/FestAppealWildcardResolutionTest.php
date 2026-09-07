<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestAppeal;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * HTTP-level check that FestAppealController::resolve actually reaches
 * FestAppealWildcardService (see FestAppealWildcardServiceTest for the unit-level
 * coverage of the granting logic itself) — the two controllers that resolve
 * appeals (FestAppealController and FestAppealsHubController) both delegate to
 * the same service, this exercises that wiring end-to-end.
 */
class FestAppealWildcardResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_approving_a_sahodaya_wildcard_appeal_over_http_grants_a_registration(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'HTTP Wildcard Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'HW', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'HTTP Origin School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'HTTP Kalotsav', 'event_type' => 'kalolsavam']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'item_code' => 'SS1', 'participant_type' => 'individual', 'is_enabled' => true]);

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10', 'display_order' => 1, 'is_active' => true]);
        $student = Student::create([
            'tenant_id' => $school->id, 'school_class_id' => $schoolClass->id,
            'admission_number' => 'ADM1', 'reg_no' => 'REG1', 'name' => 'HTTP Appeal Student', 'status' => 'active',
        ]);

        $appeal = FestAppeal::create([
            'event_id' => $event->id, 'appeal_type' => FestAppeal::TYPE_SAHODAYA_WILDCARD,
            'student_id' => $student->id, 'item_id' => $item->id,
            'reason' => 'Not selected by school', 'status' => 'pending',
        ]);

        $this->actingAs($admin)->post(route('sahodaya.events.appeals.resolve', [
            'tenantId' => $sahodaya->id, 'event' => $event->id, 'appeal' => $appeal->id,
        ]), ['status' => 'approved'])->assertRedirect();

        $appeal->refresh();
        $this->assertSame('approved', $appeal->status);
        $this->assertNotNull($appeal->granted_registration_id);

        $registration = FestRegistration::find($appeal->granted_registration_id);
        $this->assertNotNull($registration);
        $this->assertSame($student->tenant_id, $registration->origin_school_id);
        $this->assertNotSame($school->id, $registration->school_id);
    }
}
