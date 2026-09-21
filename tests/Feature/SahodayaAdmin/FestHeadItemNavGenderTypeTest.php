<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * FestHeadItemNavigationService::navigationForEvent() -- the item list backing both the
 * Results page's Item Picker tab and the Chest Numbers page -- selected columns for its
 * items query that omitted 'participant_type' and 'gender', so itemNavPayload() always
 * read those as null for any non-sports event (the sports-specific query path already
 * selected them correctly). Every item's gender/type badge on those two pages was
 * silently blank for the vast majority of events (anything that isn't event_type=sports).
 */
class FestHeadItemNavGenderTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_chest_numbers_page_exposes_gender_and_participant_type_for_a_non_sports_event(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Nav Gender Sahodaya',
            'domain' => 'nav-gender-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'NG', 'student_data_mode' => 'counts_only']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Nav Gender Fest', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Girls Group Dance', 'participant_type' => 'group',
            'gender' => 'female', 'class_group' => 'hs', 'is_enabled' => true,
        ]);

        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/chest-numbers")
            ->assertInertia(fn (Assert $page) => $page
                ->where('headItemGroups', function ($groups) use ($item) {
                    $allItems = collect($groups)->flatMap(fn ($g) => $g['items'] ?? []);
                    $row = $allItems->firstWhere('id', $item->id);

                    return $row && $row['participant_type'] === 'group' && $row['gender'] === 'female';
                }));
    }
}
