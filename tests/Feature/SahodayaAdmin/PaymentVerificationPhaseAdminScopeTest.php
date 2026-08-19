<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\FestEventStaff;
use App\Models\FestPhaseRegion;
use App\Models\MembershipPayment;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\SchoolRegionAssignment;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the SahodayaAdminController::regionScopedSchoolIds() fix for phase_admin: this
 * method is the ONLY containment on non-{event}-route pages (Membership payments, food
 * billing, etc — see the method's own docblock), since EnsureSahodayaAdmin's per-event
 * scope gate never runs for routes with no {event} route parameter at all. Before this
 * fix, a phase_admin (who has no region_id — their scope is region-less by design) hit
 * this method's `empty($scopes)` check and fell through to "unrestricted," meaning they
 * could see and verify every school's membership payments Sahodaya-wide, not just those
 * in their assigned phase's enabled regions.
 */
class PaymentVerificationPhaseAdminScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_admin_only_sees_payments_for_schools_in_their_phases_enabled_regions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Payment Scope Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'PS',
            'student_data_mode' => 'counts_only',
            'active_academic_year' => '2025-26',
        ]);

        $regionA = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region A', 'code' => 'PSA', 'is_active' => true]);
        $regionB = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region B', 'code' => 'PSB', 'is_active' => true]);

        $schoolA = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'School In Region A',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);
        $schoolB = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'School In Region B',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        SchoolRegionAssignment::create(['tenant_id' => $sahodaya->id, 'region_id' => $regionA->id, 'school_id' => $schoolA->id, 'academic_year' => '2025-26', 'source' => 'sahodaya']);
        SchoolRegionAssignment::create(['tenant_id' => $sahodaya->id, 'region_id' => $regionB->id, 'school_id' => $schoolB->id, 'academic_year' => '2025-26', 'source' => 'sahodaya']);

        MembershipPayment::create(['school_id' => $schoolA->id, 'academic_year' => '2025-26', 'amount' => 500, 'status' => 'submitted', 'payment_proof_path' => 'proofs/a.pdf']);
        MembershipPayment::create(['school_id' => $schoolB->id, 'academic_year' => '2025-26', 'amount' => 500, 'status' => 'submitted', 'payment_proof_path' => 'proofs/b.pdf']);

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Payment Scope Kalotsav', 'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned', 'workflow_mode' => 'phased_regional_billing',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);
        $phase = FestEventPhase::create(['event_id' => $hub->id, 'name' => 'Level 1', 'code' => 'L1', 'sort_order' => 1, 'is_regional' => true]);

        // Only Region A is enabled for this phase — Region B participates in a later phase.
        FestPhaseRegion::create(['phase_id' => $phase->id, 'region_id' => $regionA->id, 'enabled' => true]);
        FestPhaseRegion::create(['phase_id' => $phase->id, 'region_id' => $regionB->id, 'enabled' => false]);

        $phaseAdmin = User::factory()->create(['tenant_id' => $sahodaya->id]);
        $phaseAdmin->assignRole('phase_admin');
        FestEventStaff::create([
            'event_id' => $hub->id, 'user_id' => $phaseAdmin->id, 'duty' => 'phase_admin',
            'region_id' => null, 'source_phase_id' => $phase->id,
        ]);

        $response = $this->actingAs($phaseAdmin)->get(
            route('sahodaya.membership.payments.index', ['tenantId' => $sahodaya->id]),
        );

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('statusCounts.all', 1)
            ->has('payments.data', 1)
            ->where('payments.data.0.school_id', $schoolA->id)
        );
    }
}
