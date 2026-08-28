<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\FestPhaseRegion;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A region created from the Phases page (FestEventPhaseController::storeRegion) is scoped
 * to one FestEvent via regions.fest_event_id, so it can be picked for that event's phases
 * without polluting Membership -> Regions, Rounds & Levels, or any other Sahodaya-wide
 * region list. See docs/MCS_FOUR_PHASE_COMPLETION_PLAN.md and Region::globalOnly()/
 * visibleToEvent().
 */
class EventScopedRegionTest extends TestCase
{
    use RefreshDatabase;

    private function makeSahodayaAndEvent(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Scoped Region Sahodaya',
            'domain' => 'scoped-region-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'SR', 'student_data_mode' => 'counts_only']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Scoped Region Kalotsav', 'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned', 'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        return compact('sahodaya', 'admin', 'event');
    }

    public function test_event_scoped_region_stays_out_of_membership_regions_page(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->makeSahodayaAndEvent();
        Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Tirur Region', 'code' => 'TIRUR', 'is_active' => true]);

        $this->actingAs($admin)->post(
            route('sahodaya.events.phases.regions.store', ['tenantId' => $sahodaya->id, 'event' => $event->id]),
            ['name' => 'Sargadhara Zone A'],
        )->assertSessionDoesntHaveErrors();

        $scoped = Region::where('name', 'Sargadhara Zone A')->firstOrFail();
        $this->assertSame($event->id, $scoped->fest_event_id);
        $this->assertSame('sargadhara-zone-a', $scoped->code);

        // Shows up in this event's Phases page regions prop.
        $phasesResponse = $this->actingAs($admin)->get(
            route('sahodaya.events.phases.index', ['tenantId' => $sahodaya->id, 'event' => $event->id])
        );
        $phasesResponse->assertInertia(fn ($page) => $page->has('regions', 2));

        // Does NOT show up on the Sahodaya-wide Membership -> Regions page.
        $regionsResponse = $this->actingAs($admin)->get(
            route('sahodaya.regions.index', ['tenantId' => $sahodaya->id])
        );
        $regionsResponse->assertInertia(fn ($page) => $page->has('regions', 1)
            ->where('regions.0.name', 'Tirur Region'));

        // Confirmed via the model scope directly too (what every other tenant-wide
        // consumer -- FestRegionPartitionService, reports, staff assignment, etc. -- uses).
        $this->assertNull(Region::forTenant($sahodaya->id)->globalOnly()->find($scoped->id));
        $this->assertNotNull(Region::forTenant($sahodaya->id)->visibleToEvent($event->id)->find($scoped->id));
    }

    public function test_event_scoped_region_code_is_unique_per_event_not_sahodaya_wide(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->makeSahodayaAndEvent();
        $otherEvent = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Another Kalotsav', 'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned', 'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        $this->actingAs($admin)->post(
            route('sahodaya.events.phases.regions.store', ['tenantId' => $sahodaya->id, 'event' => $event->id]),
            ['name' => 'Zone A'],
        )->assertSessionDoesntHaveErrors();

        $this->actingAs($admin)->post(
            route('sahodaya.events.phases.regions.store', ['tenantId' => $sahodaya->id, 'event' => $otherEvent->id]),
            ['name' => 'Zone A'],
        )->assertSessionDoesntHaveErrors();

        $codes = Region::where('tenant_id', $sahodaya->id)->whereNotNull('fest_event_id')->pluck('code', 'fest_event_id');
        $this->assertSame('zone-a', $codes[$event->id]);
        $this->assertSame('zone-a', $codes[$otherEvent->id]);
    }

    public function test_sync_phase_regions_accepts_own_event_scoped_region_and_rejects_another_events(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->makeSahodayaAndEvent();
        $otherEvent = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Another Kalotsav', 'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned', 'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        $phase = FestEventPhase::create(['event_id' => $event->id, 'name' => 'Sargadhara', 'code' => 'SARG', 'sort_order' => 1, 'is_regional' => true]);
        $otherPhase = FestEventPhase::create(['event_id' => $otherEvent->id, 'name' => 'Off Stage', 'code' => 'OFF', 'sort_order' => 1, 'is_regional' => true]);

        $ownRegion = Region::create(['tenant_id' => $sahodaya->id, 'fest_event_id' => $event->id, 'name' => 'Zone A', 'code' => 'zone-a', 'is_active' => true]);
        $foreignRegion = Region::create(['tenant_id' => $sahodaya->id, 'fest_event_id' => $otherEvent->id, 'name' => 'Zone B', 'code' => 'zone-b', 'is_active' => true]);

        // Own event's phase can use its own event-scoped region.
        $this->actingAs($admin)->post(
            route('sahodaya.events.phases.regions.sync', ['tenantId' => $sahodaya->id, 'event' => $event->id, 'phase' => $phase->id]),
            ['region_ids' => [$ownRegion->id]],
        )->assertSessionDoesntHaveErrors();

        $this->assertTrue(FestPhaseRegion::where('phase_id', $phase->id)->where('region_id', $ownRegion->id)->exists());

        // A region scoped to a DIFFERENT event is rejected by validation.
        $this->actingAs($admin)->post(
            route('sahodaya.events.phases.regions.sync', ['tenantId' => $sahodaya->id, 'event' => $event->id, 'phase' => $phase->id]),
            ['region_ids' => [$foreignRegion->id]],
        )->assertSessionHasErrors(['region_ids.0']);

        $this->assertFalse(FestPhaseRegion::where('phase_id', $phase->id)->where('region_id', $foreignRegion->id)->exists());

        // Sanity: the other event's own phase CAN use its own scoped region.
        $this->actingAs($admin)->post(
            route('sahodaya.events.phases.regions.sync', ['tenantId' => $sahodaya->id, 'event' => $otherEvent->id, 'phase' => $otherPhase->id]),
            ['region_ids' => [$foreignRegion->id]],
        )->assertSessionDoesntHaveErrors();
    }

    public function test_globally_scoped_region_query_excludes_event_scoped_rows_for_general_lookups(): void
    {
        ['sahodaya' => $sahodaya, 'event' => $event] = $this->makeSahodayaAndEvent();

        $global = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Manjeri Region', 'code' => 'MANJERI', 'is_active' => true]);
        $scoped = Region::create(['tenant_id' => $sahodaya->id, 'fest_event_id' => $event->id, 'name' => 'Zone A', 'code' => 'zone-a', 'is_active' => true]);

        // This is exactly what FestRegionPartitionService, the school's annual/general
        // region picker (FestRegistrationController::schoolRegionContext/
        // selectSchoolRegion), and every other Sahodaya-wide consumer now do.
        $ids = Region::forTenant($sahodaya->id)->globalOnly()->pluck('id')->all();
        $this->assertContains($global->id, $ids);
        $this->assertNotContains($scoped->id, $ids);

        // A client-submitted event-scoped region id must not resolve for a general
        // (non-event) lookup -- mirrors FestRegistrationController::selectSchoolRegion().
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Region::forTenant($sahodaya->id)->globalOnly()->findOrFail($scoped->id);
    }
}
