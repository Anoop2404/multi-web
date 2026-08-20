<?php

namespace Tests\Feature\SchoolAdmin;

use App\Models\FestEvent;
use App\Models\FestRegistrationBatch;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression test for a same-day bug: FestRegistrationController::hydrateEventForSchoolRegistration()
 * computed the per-batch fee breakdown correctly but stored it under the Inertia prop key
 * 'phased_billing_batches', while PhasedRegionBillingPanel.vue reads 'school_registration_batch_fees'
 * — so every phased-regional-billing event's fee tab silently rendered no fee cards/line items at
 * all, and the "Download Proforma Invoice" link (which reads its batch id off that same empty data)
 * always 422'd with "Select Level 1 or Level 2." Nothing in the existing test suite hit this route
 * and asserted on the billing prop, so it shipped unnoticed.
 */
class FestRegistrationBatchFeesPropTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_exposes_batch_fees_under_the_prop_name_the_vue_component_reads(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Batch Fee Prop Test Sahodaya',
            'domain' => 'batch-fee-prop-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'BF', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Batch Fee Prop Test School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $schoolAdmin = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $schoolAdmin->assignRole('school_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Batch Fee Prop Test Kalotsav',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
            'workflow_mode' => 'phased_regional_billing',
        ]);
        $this->assertTrue($event->usesPhasedRegionalBilling());

        $batch = FestRegistrationBatch::create([
            'event_id' => $event->id,
            'code' => 'LEVEL_1',
            'name' => 'Level 1',
            'sort_order' => 1,
            'school_base_fee' => 4000,
            'invoice_prefix' => 'BF-L1',
            'status' => 'registration_open',
            'registration_close' => now()->addMonth(),
        ]);

        $response = $this->actingAs($schoolAdmin)->get(route('school.kalotsav.event.registration', [
            'tenantId' => $school->id,
            'event' => $event->id,
        ]));

        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $hydratedEvent = collect($props['events'])->firstWhere('id', $event->id);

        $this->assertTrue($hydratedEvent['uses_registration_batch_billing']);
        $this->assertArrayHasKey('school_registration_batch_fees', $hydratedEvent);
        $this->assertArrayNotHasKey('phased_billing_batches', $hydratedEvent,
            'The old, wrong prop name must not reappear — it would mean the mismatch regressed.');

        $batchFees = collect($hydratedEvent['school_registration_batch_fees']);
        $this->assertNotEmpty($batchFees, 'PhasedRegionBillingPanel.vue reads exactly this prop to render fee cards — empty means the fee tab shows nothing.');

        $level1Fee = $batchFees->firstWhere('registration_batch_id', $batch->id);
        $this->assertNotNull($level1Fee);
        $this->assertSame('LEVEL_1', $level1Fee['batch_code']);
        // The exact fee computation (base fee + item lines) is already covered by
        // FestBatchStudentRegistrationFeeTest / FestPhasedRegionalBillingWorkflowTest —
        // this test's only concern is that the computed record reaches the right prop key
        // with the right shape, which is what actually regressed.
        $this->assertArrayHasKey('total_due', $level1Fee);
        $this->assertArrayHasKey('lines', $level1Fee);
    }
}
