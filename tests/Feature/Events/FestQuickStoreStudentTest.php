<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\SchoolClass;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FestQuickStoreStudentTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_store_student_creates_student_and_returns_annotated_json(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => 'quick-sahodaya-1',
            'name' => 'Quick Sahodaya',
            'type' => 'sahodaya',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $sahodaya->id,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Quick Store Event',
            'event_type' => 'kalotsav',
            'status' => 'ongoing',
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Folk Dance',
            'participant_type' => 'individual',
            'is_enabled' => true,
        ]);

        $school = Tenant::create([
            'id' => 'quick-school-1',
            'name' => 'Quick School',
            'type' => 'school',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
            'school_prefix' => 'SCH',
        ]);

        $class = SchoolClass::create([
            'tenant_id' => $school->id,
            'name' => 'Class 10',
            'display_order' => 1,
            'is_active' => true,
        ]);

        // Test fetching classes
        $classesResponse = $this->actingAs($admin)
            ->getJson("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/school-classes/{$school->id}");

        $classesResponse->assertOk()
            ->assertJsonFragment(['name' => 'Class 10']);

        // Test quick storing a student
        $response = $this->actingAs($admin)
            ->postJson("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/quick-store-student", [
                'school_id' => $school->id,
                'name' => 'Anoop Kumar',
                'gender' => 'male',
                'dob' => '2010-05-15',
                'admission_number' => 'ADM999',
                'school_class_id' => $class->id,
                'item_id' => $item->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('student.name', 'Anoop Kumar')
            ->assertJsonPath('student.gender', 'male')
            ->assertJsonPath('student.admission_number', 'ADM999');

        $this->assertDatabaseHas('students', [
            'tenant_id' => $school->id,
            'name' => 'Anoop Kumar',
            'admission_number' => 'ADM999',
            'school_class_id' => $class->id,
        ]);
    }
}
