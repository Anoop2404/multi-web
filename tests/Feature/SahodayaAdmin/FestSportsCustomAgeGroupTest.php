<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestSportsAgeGroupConfig;
use App\Models\SahodayaProfile;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Support\FestSportsAgeGroup;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestSportsCustomAgeGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_age_group_can_be_created_and_assigned_to_sports_item(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Custom Age Group Test Sahodaya',
            'domain'    => Str::uuid().'.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'         => $sahodaya->id,
            'prefix'            => 'CAG',
            'student_data_mode' => 'counts_only',
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        // 1. Create a custom age category via the age groups controller
        $response = $this->actingAs($admin)->post("/sahodaya-admin/{$sahodaya->id}/sports/age-groups", [
            'group_key'   => 'u13',
            'label'       => 'Under 13 Boys & Girls',
            'under_age'   => 13,
            'sort_order'  => 55,
            'default_fee' => 150,
        ]);

        $response->assertSessionHas('success', 'Age category added.');

        $this->assertDatabaseHas('fest_sports_age_group_configs', [
            'tenant_id' => $sahodaya->id,
            'group_key' => 'u13',
            'under_age' => 13,
        ]);

        $this->assertTrue(FestSportsAgeGroup::isValid('u13', $sahodaya->id));
        $this->assertSame('u13', FestSportsAgeGroup::resolveForItem('u13', null, 'sports', $sahodaya->id));

        // 2. Create a sports event and add an item with this new custom age group
        $event = FestEvent::create([
            'tenant_id'   => $sahodaya->id,
            'title'       => 'Athletics Meet',
            'event_type'  => 'sports',
            'event_start' => '2026-06-01',
            'event_end'   => '2026-06-03',
        ]);

        $itemResponse = $this->actingAs($admin)->post("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/items", [
            'title'            => '100m Sprint U13',
            'age_group'        => 'u13',
            'gender'           => 'male',
            'participant_type' => 'individual',
        ]);

        $itemResponse->assertSessionHas('success', 'Item added.');

        $item = FestEventItem::where('event_id', $event->id)->where('title', '100m Sprint U13')->firstOrFail();
        $this->assertSame('u13', $item->age_group);

        // 3. Update the item
        $updateResponse = $this->actingAs($admin)->put("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/items/{$item->id}", [
            'title'            => '100m Sprint U13 (Boys)',
            'age_group'        => 'u13',
            'gender'           => 'male',
            'participant_type' => 'individual',
        ]);

        $updateResponse->assertSessionHas('success', 'Item updated.');
        $item->refresh();
        $this->assertSame('100m Sprint U13 (Boys)', $item->title);
        $this->assertSame('u13', $item->age_group);

        // 4. Test eligibility with the custom age group
        $eligible = new Student(['dob' => '2014-01-01', 'gender' => 'male']);
        $tooOld = new Student(['dob' => '2012-01-01', 'gender' => 'male']);

        $this->assertTrue(FestSportsAgeGroup::qualifiesForItem($eligible, $item, $event));
        $this->assertFalse(FestSportsAgeGroup::qualifiesForItem($tooOld, $item, $event));
    }
}
