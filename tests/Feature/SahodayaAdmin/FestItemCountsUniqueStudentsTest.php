<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventStaff;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantUserCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 'participants' on the Item Counts report sums participant_count per item, so a
 * student registered for multiple items is counted once per item. Requested addition:
 * a genuine distinct-student headcount (unique_students) alongside it.
 */
class FestItemCountsUniqueStudentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unique_students_counts_a_multi_item_student_once(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Unique Students Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'US', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Unique Students School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Unique Students Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        $item1 = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Item One', 'item_code' => '301',
            'stage_type' => 'on_stage', 'participant_type' => 'individual', 'category' => 'music', 'is_enabled' => true,
        ]);
        $item2 = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Item Two', 'item_code' => '302',
            'stage_type' => 'on_stage', 'participant_type' => 'individual', 'category' => 'music', 'is_enabled' => true,
        ]);

        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10']);
        $multiItemStudent = Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Multi Item Student', 'reg_no' => 'STU/MULTI']);
        $singleItemStudent = Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Single Item Student', 'reg_no' => 'STU/SINGLE']);

        $reg1 = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item1->id, 'school_id' => $school->id, 'status' => 'approved']);
        FestParticipant::create(['registration_id' => $reg1->id, 'student_id' => $multiItemStudent->id, 'participant_type' => 'student', 'event_id' => $event->id]);

        $reg2 = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item2->id, 'school_id' => $school->id, 'status' => 'approved']);
        FestParticipant::create(['registration_id' => $reg2->id, 'student_id' => $multiItemStudent->id, 'participant_type' => 'student', 'event_id' => $event->id]);

        $reg3 = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item1->id, 'school_id' => $school->id, 'status' => 'approved']);
        FestParticipant::create(['registration_id' => $reg3->id, 'student_id' => $singleItemStudent->id, 'participant_type' => 'student', 'event_id' => $event->id]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('event_admin');
        $admin->givePermissionTo(TenantUserCatalog::defaultPermissionsForRole('event_admin'));
        FestEventStaff::create(['event_id' => $event->id, 'user_id' => $admin->id, 'duty' => 'event_admin']);

        $response = $this->actingAs($admin)->get(route('sahodaya.events.reports.item-counts', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]));
        $response->assertOk();

        $totals = $response->viewData('page')['props']['totals'];

        $this->assertSame(3, $totals['participants'], 'participants sums per-item, so the multi-item student is counted twice');
        $this->assertSame(2, $totals['unique_students'], 'unique_students must count the multi-item student only once');
    }
}
