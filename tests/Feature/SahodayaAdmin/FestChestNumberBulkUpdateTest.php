<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
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

class FestChestNumberBulkUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_bulk_update_chest_and_order_numbers(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Bulk Update Sahodaya',
            'domain' => Str::random(10).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'BUS', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Bulk Update School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Bulk Update Fest', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Recitation', 'participant_type' => 'individual', 'is_enabled' => true,
        ]);

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10']);

        $student1 = Student::create(['tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => 'Student 1']);
        $reg1 = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
        $p1 = FestParticipant::create(['registration_id' => $reg1->id, 'student_id' => $student1->id, 'chest_no' => 101, 'order_no' => 1]);

        $student2 = Student::create(['tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => 'Student 2']);
        $reg2 = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
        $p2 = FestParticipant::create(['registration_id' => $reg2->id, 'student_id' => $student2->id, 'chest_no' => 102, 'order_no' => 2]);

        // Swap their chest numbers and orders in bulk
        $response = $this->actingAs($admin)->post("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/chest-numbers/bulk-update", [
            'item_id' => $item->id,
            'updates' => [
                ['id' => $p1->id, 'chest_no' => 102, 'order_no' => 2],
                ['id' => $p2->id, 'chest_no' => 101, 'order_no' => 1],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame(102, $p1->fresh()->chest_no);
        $this->assertSame(2, $p1->fresh()->order_no);
        $this->assertSame(101, $p2->fresh()->chest_no);
        $this->assertSame(1, $p2->fresh()->order_no);
    }

    public function test_rejects_duplicate_chest_numbers_in_bulk_update(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Bulk Update Sahodaya',
            'domain' => Str::random(10).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'BUS', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Bulk Update School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Bulk Update Fest', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Recitation', 'participant_type' => 'individual', 'is_enabled' => true,
        ]);

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10']);

        $student1 = Student::create(['tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => 'Student 1']);
        $reg1 = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
        $p1 = FestParticipant::create(['registration_id' => $reg1->id, 'student_id' => $student1->id]);

        $student2 = Student::create(['tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => 'Student 2']);
        $reg2 = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
        $p2 = FestParticipant::create(['registration_id' => $reg2->id, 'student_id' => $student2->id]);

        $response = $this->actingAs($admin)->post("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/chest-numbers/bulk-update", [
            'item_id' => $item->id,
            'updates' => [
                ['id' => $p1->id, 'chest_no' => 100, 'order_no' => 1],
                ['id' => $p2->id, 'chest_no' => 100, 'order_no' => 2],
            ],
        ]);

        $response->assertSessionHasErrors(['bulk']);
    }
}
