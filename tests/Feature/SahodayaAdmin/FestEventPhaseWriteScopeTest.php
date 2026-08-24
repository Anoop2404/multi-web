<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\FestEventStaff;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantUserCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression test: FestEventPhaseController's write actions (store/update/quickStatus/
 * destroy/assignItems) only ever checked `$phase->event_id === $event->id` — never the
 * requesting admin's own region_admin/phase_admin scope against the specific phase. Since
 * '/phases' was missing from TenantUserCatalog::pathRequiresFestSettings()'s segment list,
 * writePermissionForPath() fell through to 'fest.manage', which region_admin/phase_admin
 * both hold — so a phase_admin assigned to only ONE phase of a hub (e.g. "Off Stage") could
 * open the hub's own /phases route (reachable because their scope matches the hub for at
 * least one leaf) and edit/delete every OTHER phase's fee, dates, and payment instructions
 * too, including ones they have no assignment for. Fixed by adding '/phases' to the
 * fest.settings-gated segment list, matching every other event-setup surface
 * (fee-settings, venues, grade-configs, point-rules, ...) — region_admin/phase_admin never
 * hold fest.settings, only event_admin and full admins do.
 */
class FestEventPhaseWriteScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_admin_cannot_write_to_phases_even_their_own_assigned_phase(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Phase Write Scope Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'PW',
            'student_data_mode' => 'counts_only',
            'active_academic_year' => '2025-26',
        ]);

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Phase Write Scope Kalotsav', 'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned', 'workflow_mode' => 'phased_regional_billing',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        $ownPhase = FestEventPhase::create(['event_id' => $hub->id, 'name' => 'Off Stage', 'code' => 'OS', 'sort_order' => 1, 'is_regional' => true]);
        $otherPhase = FestEventPhase::create(['event_id' => $hub->id, 'name' => 'Sargadhara', 'code' => 'SG', 'sort_order' => 2, 'is_regional' => true]);

        $phaseAdmin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $phaseAdmin->assignRole('phase_admin');
        $phaseAdmin->givePermissionTo(TenantUserCatalog::defaultPermissionsForRole('phase_admin'));
        FestEventStaff::create([
            'event_id' => $hub->id, 'user_id' => $phaseAdmin->id, 'duty' => 'phase_admin',
            'region_id' => null, 'source_phase_id' => $ownPhase->id,
        ]);

        // Before the fix: this succeeded (302 back()) via 'fest.manage', silently editing a
        // phase (Sargadhara) the phase_admin was never assigned to.
        $response = $this->actingAs($phaseAdmin)->put(
            route('sahodaya.events.phases.update', ['tenantId' => $sahodaya->id, 'event' => $hub->id, 'phase' => $otherPhase->id]),
            ['school_registration_fee_share' => 999],
        );
        $response->assertForbidden();
        $this->assertSame(0.0, (float) $otherPhase->fresh()->school_registration_fee_share);

        // The gate is deliberately blanket, not per-phase: phase_admin's role is scoped to
        // operational duties (marks, registrations, finance, catering), not event setup —
        // matching every other fest.settings surface — so even their OWN assigned phase is
        // not writable through this endpoint.
        $response = $this->actingAs($phaseAdmin)->put(
            route('sahodaya.events.phases.update', ['tenantId' => $sahodaya->id, 'event' => $hub->id, 'phase' => $ownPhase->id]),
            ['school_registration_fee_share' => 999],
        );
        $response->assertForbidden();

        // event_admin (holds fest.settings) is unaffected.
        $eventAdmin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $eventAdmin->assignRole('event_admin');
        $eventAdmin->givePermissionTo(TenantUserCatalog::defaultPermissionsForRole('event_admin'));
        FestEventStaff::create([
            'event_id' => $hub->id, 'user_id' => $eventAdmin->id, 'duty' => 'event_admin',
        ]);

        $response = $this->actingAs($eventAdmin)->put(
            route('sahodaya.events.phases.update', ['tenantId' => $sahodaya->id, 'event' => $hub->id, 'phase' => $otherPhase->id]),
            ['school_registration_fee_share' => 999],
        );
        $response->assertRedirect();
        $this->assertSame(999.0, (float) $otherPhase->fresh()->school_registration_fee_share);
    }
}
