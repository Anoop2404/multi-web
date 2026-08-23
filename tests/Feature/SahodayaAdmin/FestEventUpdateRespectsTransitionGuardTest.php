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
 * Regression test for LIFE-01 (functional audit, 2026-08-11/12):
 * FestEvent status transitions were enforced by StatusTransitionGuard in
 * quickStatus() but NOT in update() (the main "Edit Event" settings-form
 * save) — the same role, same model, two independently reachable code paths
 * that could disagree on what transitions are allowed. A completed event
 * could be pushed back to 'ongoing' or 'draft' through the update() form,
 * bypassing the guard entirely.
 */
class FestEventUpdateRespectsTransitionGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_rejects_a_transition_out_of_the_completed_terminal_state(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Guard Test Sahodaya',
            'domain'    => 'guard-test.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'         => $sahodaya->id,
            'prefix'            => 'GT',
            'student_data_mode' => 'counts_only',
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id'   => $sahodaya->id,
            'title'       => 'Completed Event',
            'event_type'  => 'kalotsavam',
            'level_round' => 'sahodaya',
            'status'      => 'completed',
        ]);

        $response = $this->actingAs($admin)->put(route('sahodaya.events.update', [
            'tenantId' => $sahodaya->id,
            'event'    => $event->id,
        ]), [
            'title'  => 'Completed Event',
            'status' => 'draft',
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertSame('completed', $event->fresh()->status);
    }

    public function test_update_allows_resaving_the_form_without_changing_status(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Guard Test Sahodaya 2',
            'domain'    => 'guard-test-2.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'         => $sahodaya->id,
            'prefix'            => 'GT2',
            'student_data_mode' => 'counts_only',
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id'   => $sahodaya->id,
            'title'       => 'Draft Event',
            'event_type'  => 'kalotsavam',
            'level_round' => 'sahodaya',
            'status'      => 'draft',
        ]);

        $response = $this->actingAs($admin)->put(route('sahodaya.events.update', [
            'tenantId' => $sahodaya->id,
            'event'    => $event->id,
        ]), [
            'title'  => 'Draft Event (renamed)',
            'status' => 'draft',
        ]);

        $response->assertSessionDoesntHaveErrors('status');
        $this->assertSame('Draft Event (renamed)', $event->fresh()->title);
    }

    public function test_update_allows_transition_from_completed_back_to_ongoing(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Guard Test Sahodaya 3',
            'domain'    => 'guard-test-3.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'         => $sahodaya->id,
            'prefix'            => 'GT3',
            'student_data_mode' => 'counts_only',
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id'   => $sahodaya->id,
            'title'       => 'Completed Event',
            'event_type'  => 'kalotsavam',
            'level_round' => 'sahodaya',
            'status'      => 'completed',
        ]);

        $response = $this->actingAs($admin)->put(route('sahodaya.events.update', [
            'tenantId' => $sahodaya->id,
            'event'    => $event->id,
        ]), [
            'title'  => 'Completed Event',
            'status' => 'ongoing',
        ]);

        $response->assertSessionDoesntHaveErrors('status');
        $this->assertSame('ongoing', $event->fresh()->status);
    }
}
