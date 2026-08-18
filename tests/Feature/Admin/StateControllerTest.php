<?php

namespace Tests\Feature\Admin;

use App\Models\FestStateProgram;
use App\Models\PlatformState;
use App\Models\PlatformUser;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StateControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingSuperadmin(): PlatformUser
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $superadmin = PlatformUser::query()->create([
            'name' => 'State CRUD Super',
            'email' => 'state-crud-super@example.com',
            'username' => 'state_crud_super',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $superadmin->assignRole('superadmin');

        return $superadmin;
    }

    public function test_superadmin_can_create_a_state(): void
    {
        $superadmin = $this->actingSuperadmin();

        $this->actingAs($superadmin, 'platform')
            ->post('http://superadmin.test/admin/states', ['code' => 'KL', 'name' => 'Kerala'])
            ->assertRedirect();

        $this->assertDatabaseHas('states', ['code' => 'KL', 'name' => 'Kerala', 'is_active' => true]);
    }

    public function test_state_code_must_be_unique(): void
    {
        $superadmin = $this->actingSuperadmin();
        PlatformState::create(['code' => 'KL', 'name' => 'Kerala']);

        $this->actingAs($superadmin, 'platform')
            ->post('http://superadmin.test/admin/states', ['code' => 'KL', 'name' => 'Kerala Duplicate'])
            ->assertSessionHasErrors('code');
    }

    public function test_superadmin_can_update_a_state(): void
    {
        $superadmin = $this->actingSuperadmin();
        $state = PlatformState::create(['code' => 'KL', 'name' => 'Kerala']);

        $this->actingAs($superadmin, 'platform')
            ->put("http://superadmin.test/admin/states/{$state->id}", [
                'code' => 'KL',
                'name' => 'Kerala',
                'contact_email' => 'state-office@example.com',
                'default_academic_year' => '2026-27',
                'is_active' => true,
            ])
            ->assertRedirect();

        $state->refresh();
        $this->assertSame('state-office@example.com', $state->contact_email);
        $this->assertSame('2026-27', $state->default_academic_year);
    }

    public function test_state_index_shows_assigned_user_count(): void
    {
        $superadmin = $this->actingSuperadmin();
        $state = PlatformState::create(['code' => 'KL', 'name' => 'Kerala']);
        User::factory()->create(['tenant_id' => null, 'state_id' => $state->id])->assignRole('state_admin');

        $this->actingAs($superadmin, 'platform')
            ->get('http://superadmin.test/admin/states')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/States/Index', false)
                ->where('states.0.platform_users_count', 1));
    }

    public function test_state_with_assigned_users_cannot_be_deleted(): void
    {
        $superadmin = $this->actingSuperadmin();
        $state = PlatformState::create(['code' => 'KL', 'name' => 'Kerala']);
        User::factory()->create(['tenant_id' => null, 'state_id' => $state->id])->assignRole('state_admin');

        $this->actingAs($superadmin, 'platform')
            ->delete("http://superadmin.test/admin/states/{$state->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('states', ['id' => $state->id]);
    }

    public function test_state_with_fest_programs_cannot_be_deleted(): void
    {
        $superadmin = $this->actingSuperadmin();
        $state = PlatformState::create(['code' => 'KL', 'name' => 'Kerala']);
        FestStateProgram::create(['state_id' => $state->id, 'title' => 'Program', 'event_type' => 'kalolsavam', 'conduct_levels' => ['state']]);

        $this->actingAs($superadmin, 'platform')
            ->delete("http://superadmin.test/admin/states/{$state->id}")
            ->assertStatus(422);
    }

    public function test_empty_state_can_be_deleted(): void
    {
        $superadmin = $this->actingSuperadmin();
        $state = PlatformState::create(['code' => 'KL', 'name' => 'Kerala']);

        $this->actingAs($superadmin, 'platform')
            ->delete("http://superadmin.test/admin/states/{$state->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('states', ['id' => $state->id]);
    }

    public function test_state_admin_cannot_access_states_management(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $state = PlatformState::create(['code' => 'KL', 'name' => 'Kerala']);

        // A real state_admin authenticated on the central host resolves via the
        // 'platform' guard (ResolveAuthenticationGuard forces it there) — a plain
        // tenant User on the 'web' guard wouldn't even reach this far.
        $stateAdmin = PlatformUser::query()->create([
            'name' => 'State Admin Only',
            'email' => 'state-admin-only@example.com',
            'username' => 'state_admin_only',
            'password' => 'password',
            'email_verified_at' => now(),
            'state_id' => $state->id,
        ]);
        $stateAdmin->assignRole('state_admin');

        $this->actingAs($stateAdmin, 'platform')
            ->get('http://superadmin.test/admin/states')
            ->assertForbidden();
    }
}
