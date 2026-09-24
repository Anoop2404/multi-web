<?php

namespace Tests\Feature\SchoolAdmin;

use App\Models\FestEvent;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * fee_settings.id_card_downloads_disabled (FeesTab.vue's "Download gates" section) must
 * block a school from the ID Cards workspace entirely — not just the download buttons on
 * it — and must hide the sidebar link that points there, per the exact ask: "don't even
 * show that section for id card disabled school and no url access also".
 */
class FestIdCardDownloadsDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_is_blocked_from_the_id_cards_page_by_direct_url_when_disabled(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'ID Card Disable Sahodaya',
            'domain' => 'id-card-disable-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'ICD', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'ID Card Disable School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $schoolAdmin = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $schoolAdmin->assignRole('school_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'ID Card Disable Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
            'fee_settings' => ['fee_model' => 'none', 'id_card_downloads_disabled' => true],
        ]);

        $response = $this->actingAs($schoolAdmin)->get(route('school.kalotsav.reports.id-cards', [
            'tenantId' => $school->id, 'event' => $event->id,
        ]));

        $response->assertForbidden();
    }

    public function test_school_can_reach_the_id_cards_page_when_not_disabled(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'ID Card Enabled Sahodaya',
            'domain' => 'id-card-enabled-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'ICE', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'ID Card Enabled School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $schoolAdmin = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $schoolAdmin->assignRole('school_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'ID Card Enabled Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
            'fee_settings' => ['fee_model' => 'none'],
        ]);

        $response = $this->actingAs($schoolAdmin)->get(route('school.kalotsav.reports.id-cards', [
            'tenantId' => $school->id, 'event' => $event->id,
        ]));

        $response->assertOk();
    }

    public function test_sidebar_hides_the_id_cards_link_when_disabled(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'ID Card Nav Sahodaya',
            'domain' => 'id-card-nav-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'ICN', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'ID Card Nav School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $schoolAdmin = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $schoolAdmin->assignRole('school_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'ID Card Nav Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
            'fee_settings' => ['fee_model' => 'none', 'id_card_downloads_disabled' => true],
        ]);

        $response = $this->actingAs($schoolAdmin)->get(route('school.kalotsav.event.registration', [
            'tenantId' => $school->id, 'event' => $event->id,
        ]));

        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $this->assertTrue($props['idCardDownloadsDisabled']);
    }
}
