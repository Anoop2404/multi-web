<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventStaff;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantUserCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The Registrations page's "Select Region:" switcher (Registrations.vue's switchSportEvent())
 * navigates straight to a different FestEvent id — the sibling region-partition child's own
 * page — not a query-string filter. It used to list every region-partition child of the hub
 * regardless of the viewing admin's own scope, so a region_admin locked to one region saw every
 * other region as a selectable option; picking one 403'd (EnsureSahodayaAdmin denies the
 * resulting request). Covers the fix: FestRegistrationReviewController::index() narrows
 * childEvents to what the admin can actually open.
 */
class ScopedRegistrationsRegionSwitcherTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Registrations Switcher Sahodaya',
            'domain' => 'registrations-switcher.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'RS',
            'student_data_mode' => 'counts_only',
            'active_academic_year' => '2025-26',
        ]);

        $regionA = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region A', 'code' => 'RSA', 'is_active' => true]);
        $regionB = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region B', 'code' => 'RSB', 'is_active' => true]);

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Switcher Kalotsav',
            'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        $childA = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Switcher Kalotsav — Region A',
            'event_type' => 'kalolsavam',
            'parent_event_id' => $hub->id,
            'partition_key' => 'region-a',
            'partition_role' => 'region',
            'region_id' => $regionA->id,
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        $childB = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Switcher Kalotsav — Region B',
            'event_type' => 'kalolsavam',
            'parent_event_id' => $hub->id,
            'partition_key' => 'region-b',
            'partition_role' => 'region',
            'region_id' => $regionB->id,
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        return compact('sahodaya', 'regionA', 'regionB', 'hub', 'childA', 'childB');
    }

    public function test_region_admin_scoped_to_one_child_only_sees_that_child_in_the_switcher(): void
    {
        $f = $this->fixture();

        $admin = User::factory()->create(['tenant_id' => $f['sahodaya']->id]);
        $admin->assignRole('region_admin');
        // Matches how the admin UI actually provisions this role — without it, the
        // registrations page under test 403s before reaching the switcher logic itself.
        $admin->givePermissionTo(TenantUserCatalog::defaultPermissionsForRole('region_admin'));
        FestEventStaff::create([
            'event_id' => $f['childA']->id,
            'user_id' => $admin->id,
            'duty' => 'region_admin',
            'region_id' => $f['regionA']->id,
        ]);

        $response = $this->actingAs($admin)->get(route('sahodaya.events.registrations.index', [
            'tenantId' => $f['sahodaya']->id,
            'event' => $f['childA']->id,
        ]));

        $response->assertInertia(fn ($page) => $page
            ->where('childEvents', [[
                'id' => $f['childA']->id,
                'title' => 'Region A',
                'short_title' => 'Region A',
                'parent_event_id' => $f['hub']->id,
                'is_hub' => false,
            ]]));
    }

    public function test_region_admin_scoped_on_the_hub_sees_the_hub_and_their_own_region_only(): void
    {
        $f = $this->fixture();

        $admin = User::factory()->create(['tenant_id' => $f['sahodaya']->id]);
        $admin->assignRole('region_admin');
        // Matches how the admin UI actually provisions this role — without it, the
        // registrations page under test 403s before reaching the switcher logic itself.
        $admin->givePermissionTo(TenantUserCatalog::defaultPermissionsForRole('region_admin'));
        FestEventStaff::create([
            'event_id' => $f['hub']->id,
            'user_id' => $admin->id,
            'duty' => 'region_admin',
            'region_id' => $f['regionA']->id,
        ]);

        $response = $this->actingAs($admin)->get(route('sahodaya.events.registrations.index', [
            'tenantId' => $f['sahodaya']->id,
            'event' => $f['hub']->id,
        ]));

        $response->assertInertia(function ($page) use ($f) {
            $page->has('childEvents', 2);
            $ids = collect($page->toArray()['props']['childEvents'])->pluck('id')->all();
            $this->assertEqualsCanonicalizing([$f['hub']->id, $f['childA']->id], $ids);
        });
    }

    public function test_full_admin_sees_every_region_in_the_switcher(): void
    {
        $f = $this->fixture();

        $admin = User::factory()->create(['tenant_id' => $f['sahodaya']->id]);
        $admin->assignRole('sahodaya_admin');

        $response = $this->actingAs($admin)->get(route('sahodaya.events.registrations.index', [
            'tenantId' => $f['sahodaya']->id,
            'event' => $f['hub']->id,
        ]));

        $response->assertInertia(function ($page) use ($f) {
            $page->has('childEvents', 3);
            $ids = collect($page->toArray()['props']['childEvents'])->pluck('id')->all();
            $this->assertEqualsCanonicalizing([$f['hub']->id, $f['childA']->id, $f['childB']->id], $ids);
        });
    }
}
