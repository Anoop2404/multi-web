<?php

namespace Tests\Feature\Events;

use App\Models\FeeReceipt;
use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\FestRegistrationBatch;
use App\Models\FestSchoolEventFee;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\FestPhasedWorkflowService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A phased_regional_billing event produces one FestSchoolEventFee row per payment level
 * (Level 1, Level 2, …) per school — shown as separate table rows made this listing twice
 * as long per school with proofs scattered across rows. Confirms FestEventFeesController
 * combines them into one row per school (merged itemized breakdown, merged receipts, a
 * `batches` sub-array retaining each level's own fee id for approve/reject actions).
 */
class FestEventFeesCombinedBatchRowTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_level_fee_rows_are_combined_into_one_row_per_school_with_receipts_merged(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Combined Row Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'CRS',
            'student_data_mode' => 'counts_only',
        ]);
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'parent_id' => $sahodaya->id,
            'name' => 'Combined Row School',
            'domain' => Str::uuid().'.test',
            'membership_status' => 'approved',
            'is_active' => true,
        ]);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $root = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Combined Row Kalotsav',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'conductor_level' => 'sahodaya',
            'status' => 'registration_open',
            'workflow_mode' => FestPhasedWorkflowService::MODE,
            'fee_settings' => ['fee_model' => 'kalolsavam_composite'],
        ]);
        $level1 = FestRegistrationBatch::create([
            'event_id' => $root->id, 'code' => 'LEVEL_1', 'name' => 'Level 1', 'sort_order' => 1,
        ]);
        $level2 = FestRegistrationBatch::create([
            'event_id' => $root->id, 'code' => 'LEVEL_2', 'name' => 'Level 2', 'sort_order' => 2,
        ]);
        FestEventPhase::create(['event_id' => $root->id, 'name' => 'Digi Fest', 'code' => 'DIGI', 'sort_order' => 1, 'registration_batch_id' => $level1->id]);
        FestEventPhase::create(['event_id' => $root->id, 'name' => 'Sargadhara', 'code' => 'SARGADHARA', 'sort_order' => 2, 'registration_batch_id' => $level2->id]);

        $level1Fee = FestSchoolEventFee::create([
            'event_id' => $root->id, 'school_id' => $school->id, 'registration_batch_id' => $level1->id,
            'participation_item_count' => 3, 'total_due' => 4400, 'status' => 'proof_uploaded',
        ]);
        $level2Fee = FestSchoolEventFee::create([
            'event_id' => $root->id, 'school_id' => $school->id, 'registration_batch_id' => $level2->id,
            'participation_item_count' => 1, 'total_due' => 50, 'status' => 'pending',
        ]);
        $rollup = FestSchoolEventFee::create([
            'event_id' => $root->id, 'school_id' => $school->id,
            'total_due' => 4450, 'amount_paid' => 0, 'status' => 'pending',
        ]);

        $receipt = FeeReceipt::create([
            'feeable_type' => FestSchoolEventFee::class,
            'feeable_id' => $level1Fee->id,
            'file_path' => 'fest/receipts/level1.jpg',
            'amount' => 4400,
            'status' => 'uploaded',
        ]);
        $level1Fee->update(['fee_receipt_id' => $receipt->id]);

        $response = $this->actingAs($admin)->get(route('sahodaya.events.fees.index', [
            'tenantId' => $sahodaya->id,
            'event' => $root->id,
        ]));

        $response->assertOk();
        $response->assertInertia(function ($page) use ($school, $rollup, $level1Fee, $level2Fee) {
            $rows = collect($page->toArray()['props']['rows']);
            $this->assertCount(1, $rows, 'Both payment levels for the school must collapse into a single row.');

            $row = $rows->first();
            $this->assertSame($school->id, $row['school_id']);
            $this->assertSame($rollup->id, $row['id'], 'Combined row should key off the rollup fee record.');
            $this->assertSame(4450.0, (float) $row['total_due']);
            $this->assertNull($row['registration_batch'], 'Combined row should not claim a single payment level.');

            // The Level 1 proof must not disappear once rows are combined.
            $receiptIds = collect($row['all_receipts'])->pluck('id')->all();
            $this->assertContains($level1Fee->receipts()->value('id'), $receiptIds);

            // Each level's own fee id survives for the Actions column to act on.
            $this->assertCount(2, $row['batches']);
            $batchIds = collect($row['batches'])->pluck('id')->all();
            $this->assertContains($level1Fee->id, $batchIds);
            $this->assertContains($level2Fee->id, $batchIds);

            return true;
        });
    }
}
