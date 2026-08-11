<?php

namespace Tests\Feature\State;

use App\Models\PlatformRole;
use App\Models\PlatformUser;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StateUserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_superadmin_can_create_state_user(): void
    {
        $superadmin = PlatformUser::where('email', 'admin@sahodaya.test')->first();

        $response = $this->actingAs($superadmin)->post('/admin/state-users', [
            'name'     => 'State Admin Test',
            'email'    => 'stateadmin@test.com',
            'password' => 'password123',
            'roles'    => ['state_admin'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'email' => 'stateadmin@test.com',
            'name'  => 'State Admin Test',
        ]);
    }

    public function test_create_state_user_fails_when_roles_empty(): void
    {
        $superadmin = PlatformUser::where('email', 'admin@sahodaya.test')->first();

        $response = $this->actingAs($superadmin)->post('/admin/state-users', [
            'name'     => 'No Role User',
            'email'    => 'norole@test.com',
            'password' => 'password123',
            'roles'    => [],
        ]);

        $response->assertSessionHasErrors('roles');
    }

    public function test_superadmin_can_update_state_user(): void
    {
        $superadmin = PlatformUser::where('email', 'admin@sahodaya.test')->first();

        $user = PlatformUser::create([
            'name'     => 'Old Name',
            'email'    => 'update@test.com',
            'password' => bcrypt('password123'),
        ]);
        $user->assignRole('state_admin');

        $response = $this->actingAs($superadmin)->put("/admin/state-users/{$user->id}", [
            'name'     => 'New Name',
            'email'    => 'update@test.com',
            'roles'    => ['state_staff'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id'   => $user->id,
            'name' => 'New Name',
        ]);
        $this->assertTrue($user->fresh()->hasRole('state_staff'));
    }

    public function test_superadmin_can_delete_state_user(): void
    {
        $superadmin = PlatformUser::where('email', 'admin@sahodaya.test')->first();

        $user = PlatformUser::create([
            'name'     => 'To Delete',
            'email'    => 'delete@test.com',
            'password' => bcrypt('password123'),
        ]);
        $user->assignRole('state_staff');

        $response = $this->actingAs($superadmin)->delete("/admin/state-users/{$user->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_create_state_user_auto_creates_missing_role(): void
    {
        $superadmin = PlatformUser::where('email', 'admin@sahodaya.test')->first();

        // Delete state_admin role from DB if present
        PlatformRole::where('name', 'state_admin')->delete();

        $response = $this->actingAs($superadmin)->post('/admin/state-users', [
            'name'     => 'Auto Role Test',
            'email'    => 'autorole@test.com',
            'password' => 'password123',
            'roles'    => ['state_admin'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'autorole@test.com']);
        $this->assertDatabaseHas('roles', ['name' => 'state_admin', 'guard_name' => 'web']);
    }
}
