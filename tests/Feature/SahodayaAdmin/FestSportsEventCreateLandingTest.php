<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A brand-new top-level sports event used to be treated as a "season container" the
 * moment it was created -- even with zero child sport events under it -- landing the
 * admin on /setup with a "Season ... created" message instead of the normal event page
 * every other event type gets. FestEvent::isSportsSeasonEvent() only actually starts
 * treating an event as a season once child sport events exist, so this was a landing/
 * copy bug, not a real requirement: an admin who just wants one plain sports event
 * should land on the same page a Kalotsav event would.
 */
class FestSportsEventCreateLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_standalone_sports_event_lands_on_the_normal_event_page(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Sports Landing Test Sahodaya',
            'domain'    => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id'         => $sahodaya->id,
            'prefix'            => 'SLT',
            'student_data_mode' => 'counts_only',
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $response = $this->actingAs($admin)->post(route('sahodaya.events.store', ['tenantId' => $sahodaya->id]), [
            'title'      => 'Annual Sports Meet',
            'event_type' => 'sports',
        ]);

        $event = FestEvent::where('tenant_id', $sahodaya->id)->where('title', 'Annual Sports Meet')->firstOrFail();

        $response->assertRedirect("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}");
        $response->assertSessionHas('success', "Event \"{$event->title}\" created.");
        $this->assertNull($event->parent_event_id);
        $this->assertFalse($event->isSportsSeasonEvent(), 'a fresh event with no children must not be treated as a season');
    }
}
