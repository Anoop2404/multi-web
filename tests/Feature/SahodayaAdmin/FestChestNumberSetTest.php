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

/**
 * Admin manual chest-number override (FestChestNumberController::setChest()) —
 * the only prior way to affect chest_no was bulk sequential assignment or clearing;
 * this lets an admin pin an exact number (e.g. matching one already printed).
 */
class FestChestNumberSetTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, admin: User, event: FestEvent, item: FestEventItem, participant: FestParticipant} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Chest Set Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'CST', 'student_data_mode' => 'counts_only']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Chest Set Event', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Chest Set School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);
        $schoolClass = SchoolClass::firstOrCreate(
            ['tenant_id' => $school->id, 'name' => '8'],
            ['display_order' => 1, 'is_active' => true],
        );

        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Chest Set Item', 'participant_type' => 'individual', 'is_enabled' => true,
        ]);
        $registration = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id,
            'status' => 'approved', 'submitted_at' => now(),
        ]);

        $studentA = Student::create([
            'tenant_id' => $school->id, 'school_class_id' => $schoolClass->id,
            'admission_number' => 'ADMA', 'reg_no' => 'REGA', 'name' => 'Chest Set Student A', 'status' => 'active',
        ]);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'student_id' => $studentA->id,
            'participant_type' => 'student', 'participant_role' => 'performer',
        ]);

        $studentB = Student::create([
            'tenant_id' => $school->id, 'school_class_id' => $schoolClass->id,
            'admission_number' => 'ADMB', 'reg_no' => 'REGB', 'name' => 'Chest Set Student B', 'status' => 'active',
        ]);
        FestParticipant::create([
            'registration_id' => $registration->id, 'student_id' => $studentB->id,
            'participant_type' => 'student', 'participant_role' => 'performer',
            'chest_no' => 150, 'chest_head_id' => $item->id, 'event_id' => $event->id,
        ]);

        return compact('sahodaya', 'admin', 'event', 'item', 'participant');
    }

    public function test_admin_can_manually_set_a_chest_number_for_a_never_assigned_participant(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event, 'participant' => $participant] = $this->fixture();

        $response = $this->actingAs($admin)->post(route('sahodaya.events.chest-numbers.set', [
            'tenantId' => $sahodaya->id, 'event' => $event->id, 'participant' => $participant->id,
        ]), ['chest_no' => 777]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $participant->refresh();
        $this->assertSame(777, $participant->chest_no);
        $this->assertSame($participant->registration->item_id, $participant->chest_head_id);
    }

    public function test_setting_a_chest_number_already_taken_in_scope_is_rejected(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event, 'participant' => $participant] = $this->fixture();

        $response = $this->actingAs($admin)->post(route('sahodaya.events.chest-numbers.set', [
            'tenantId' => $sahodaya->id, 'event' => $event->id, 'participant' => $participant->id,
        ]), ['chest_no' => 150]);

        $response->assertSessionHasErrors('chest_no');

        $participant->refresh();
        $this->assertNull($participant->chest_no);
    }

    public function test_cannot_set_chest_number_for_a_participant_in_another_tenants_event(): void
    {
        ['admin' => $admin, 'participant' => $participant] = $this->fixture();

        $otherSahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Other Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        $otherEvent = FestEvent::create([
            'tenant_id' => $otherSahodaya->id, 'title' => 'Other Event', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.chest-numbers.set', [
            'tenantId' => $otherSahodaya->id, 'event' => $otherEvent->id, 'participant' => $participant->id,
        ]), ['chest_no' => 999]);

        $response->assertForbidden();
    }
}
