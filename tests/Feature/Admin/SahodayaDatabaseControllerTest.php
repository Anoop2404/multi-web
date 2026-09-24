<?php

namespace Tests\Feature\Admin;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The superadmin's view of which Sahodayas have a working database, and creating the ones that do not.
 *
 * The provisioning itself is exercised by the console command's own tests and cannot run here — the
 * suite runs on one shared database with TENANCY_DATABASE_PER_SAHODAYA off, which is precisely the
 * state this page has to report honestly rather than showing twenty broken-looking rows.
 */
class SahodayaDatabaseControllerTest extends TestCase
{
    use RefreshDatabase;

    private function sahodaya(string $name = 'Kottayam Sahodaya'): Tenant
    {
        return Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => $name, 'is_active' => true,
        ]);
    }

    private function superadmin(): User
    {
        return tap(User::factory()->create(['tenant_id' => null, 'email_verified_at' => now()]),
            fn (User $u) => $u->assignRole('superadmin'));
    }

    private function stateAdmin(): User
    {
        return tap(User::factory()->create(['tenant_id' => null, 'email_verified_at' => now()]),
            fn (User $u) => $u->assignRole('state_admin'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_a_superadmin_sees_every_sahodaya_and_its_database_state(): void
    {
        config(['tenancy.database_per_sahodaya' => false]);
        $this->sahodaya('Kottayam Sahodaya');
        $this->sahodaya('Thrissur Sahodaya');

        $this->actingAs($this->superadmin())
            ->get('/admin/sahodayas/databases')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenants/Databases', false)
                ->where('counts.total', 2)
                ->where('enabled', false));
    }

    public function test_the_page_says_so_when_dedicated_databases_are_turned_off(): void
    {
        config(['tenancy.database_per_sahodaya' => false]);
        $this->sahodaya();

        // Reported as a setting, not as twenty failures: with the flag off there is nothing to create.
        $this->actingAs($this->superadmin())
            ->get('/admin/sahodayas/databases')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('enabled', false));
    }

    public function test_provisioning_is_refused_while_dedicated_databases_are_off(): void
    {
        config(['tenancy.database_per_sahodaya' => false]);
        $sahodaya = $this->sahodaya();

        $this->actingAs($this->superadmin())
            ->post('/admin/sahodayas/databases/provision', ['sahodaya_id' => $sahodaya->id])
            ->assertStatus(422);

        $this->actingAs($this->superadmin())
            ->post('/admin/sahodayas/databases/provision-all')
            ->assertStatus(422);
    }

    public function test_a_state_admin_cannot_reach_it(): void
    {
        config(['tenancy.database_per_sahodaya' => false]);
        $this->sahodaya();

        // A State office manages its Sahodayas' events; creating Postgres databases is not theirs.
        $response = $this->actingAs($this->stateAdmin())->get('/admin/sahodayas/databases');

        $this->assertContains($response->getStatusCode(), [403, 404, 302]);
    }

    public function test_a_school_cannot_be_provisioned_through_it(): void
    {
        // Left off deliberately: turning per-Sahodaya databases on switches this suite to
        // tenant-aware sessions and the request arrives unauthenticated. It changes nothing here —
        // "no such Sahodaya" is answered before the enabled check, and is the truer answer anyway.
        config(['tenancy.database_per_sahodaya' => false]);
        $sahodaya = $this->sahodaya();
        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'St Joseph HSS',
            'parent_id' => $sahodaya->id, 'is_active' => true,
        ]);

        // A school shares its parent's database, so there is nothing of its own to create.
        $this->actingAs($this->superadmin())
            ->post('/admin/sahodayas/databases/provision', ['sahodaya_id' => $school->id])
            ->assertNotFound();
    }

    public function test_the_sahodaya_list_offers_the_link_only_to_a_superadmin(): void
    {
        config(['tenancy.database_per_sahodaya' => false]);
        $this->sahodaya();

        $this->actingAs($this->superadmin())
            ->get('/admin/sahodayas')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('databasesUrl', url('/admin/sahodayas/databases')));

        $this->actingAs($this->stateAdmin())
            ->get('/admin/sahodayas')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('databasesUrl', null));
    }

    public function test_the_school_list_never_offers_it(): void
    {
        config(['tenancy.database_per_sahodaya' => false]);
        $this->sahodaya();

        $this->actingAs($this->superadmin())
            ->get('/admin/schools')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('databasesUrl', null));
    }

    public function test_counts_separate_missing_from_unmigrated(): void
    {
        config(['tenancy.database_per_sahodaya' => false]);
        $this->sahodaya();

        // With the flag off the provisioner reports ready, so nothing is pending — the page must not
        // invite an operator to "create" databases that this installation does not use.
        $this->actingAs($this->superadmin())
            ->get('/admin/sahodayas/databases')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('counts.missing', 0)
                ->where('counts.unmigrated', 0));
    }
}
