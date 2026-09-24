<?php

namespace Tests\Feature\State;

use App\Models\PlatformState;
use App\Models\PlatformUser;
use App\Models\Tenant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * admin.sahodayas.* sits behind EnsureStateAdmin (unlike admin.schools.* and admin.tenants.*,
 * which are superadmin-only), so a state admin can open the Sahodaya list by design — but nothing
 * scoped that list, so they saw every other state's Sahodayas too. Same class of data-isolation gap
 * FRD-13 Finding A closed for fest programs and remittances, and it got sharper once the promotion
 * pipeline (docs/STATE_ADMIN_TENANT_PROMOTION_AND_UAT_PLAN_2026_09_23.md §4) started turning the
 * state master list into real tenants carrying contact details.
 */
class StateAdminTenantScopeTest extends TestCase
{
    use RefreshDatabase;

    private PlatformState $kerala;

    private PlatformState $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->kerala = PlatformState::create(['code' => 'KL', 'name' => 'Kerala', 'is_active' => true]);
        $this->other = PlatformState::create(['code' => 'TN', 'name' => 'Tamil Nadu', 'is_active' => true]);

        $this->sahodaya('Kerala Sahodaya', $this->kerala->id);
        $this->sahodaya('Tamil Nadu Sahodaya', $this->other->id);
        $this->sahodaya('Unassigned Sahodaya', null);
    }

    private function sahodaya(string $name, ?string $stateId): Tenant
    {
        return Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'state_id'  => $stateId,
            'name'      => $name,
            'is_active' => true,
        ]);
    }

    private function stateAdmin(?string $stateId): PlatformUser
    {
        $user = PlatformUser::create([
            'name'              => 'State Admin',
            'email'             => 'sa-'.Str::random(6).'@example.test',
            'username'          => 'sa_'.Str::random(6),
            'password'          => 'password',
            'state_id'          => $stateId,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('state_admin');

        return $user;
    }

    private function superadmin(): PlatformUser
    {
        $user = PlatformUser::create([
            'name'              => 'Super',
            'email'             => 'super-'.Str::random(6).'@example.test',
            'username'          => 'super_'.Str::random(6),
            'password'          => 'password',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('superadmin');

        return $user;
    }

    public function test_a_state_admin_only_sees_their_own_states_sahodayas(): void
    {
        $this->actingAs($this->stateAdmin($this->kerala->id), 'platform')
            ->get('http://superadmin.test/admin/sahodayas')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tenants.total', 1)
                ->where('tenants.data.0.name', 'Kerala Sahodaya'));
    }

    public function test_a_state_admin_with_no_state_assigned_sees_nothing(): void
    {
        // Fail closed, matching Support\StateScope everywhere else in the state admin.
        $this->actingAs($this->stateAdmin(null), 'platform')
            ->get('http://superadmin.test/admin/sahodayas')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('tenants.total', 0));
    }

    public function test_superadmin_still_sees_every_sahodaya_including_unassigned_ones(): void
    {
        $this->actingAs($this->superadmin(), 'platform')
            ->get('http://superadmin.test/admin/sahodayas')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('tenants.total', 3));
    }

    public function test_the_schools_list_stays_superadmin_only(): void
    {
        $this->actingAs($this->stateAdmin($this->kerala->id), 'platform')
            ->get('http://superadmin.test/admin/schools')
            ->assertForbidden();
    }
}
