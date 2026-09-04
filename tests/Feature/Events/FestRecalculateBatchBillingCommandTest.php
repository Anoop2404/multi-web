<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestRegistration;
use App\Models\FestRegistrationBatch;
use App\Models\FestSchoolEventFee;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Services\Events\FestPhasedWorkflowService;
use App\Services\Events\FestRegistrationBatchFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * --sahodaya/--event became optional filters (previously required) so the command can
 * scan every Sahodaya/event in one run, matching fest:audit-event-topology's convention.
 * Covers: the new no-filter scan reaches multiple tenants, dry-run never writes, and
 * --commit actually persists a corrected total_due without touching amount_paid.
 */
class FestRecalculateBatchBillingCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_scanning_without_a_sahodaya_filter_reaches_every_tenant(): void
    {
        $fixtureA = $this->fixtureWithStaleInvoice('a');
        $fixtureB = $this->fixtureWithStaleInvoice('b');

        $this->artisan('fest:recalculate-batch-billing')
            ->expectsOutputToContain($fixtureA['sahodaya']->name)
            ->expectsOutputToContain($fixtureB['sahodaya']->name)
            ->assertExitCode(0);

        // Dry run — neither tenant's stored total_due should have moved yet.
        $fixtureA['sahodaya']->run(function () use ($fixtureA) {
            $this->assertSame(50.0, (float) FestSchoolEventFee::where('event_id', $fixtureA['root']->id)
                ->where('school_id', $fixtureA['school']->id)->whereNotNull('registration_batch_id')->value('total_due'));
        });
    }

    public function test_dry_run_never_writes_and_commit_persists_the_corrected_total(): void
    {
        $fixture = $this->fixtureWithStaleInvoice('commit');

        $this->artisan('fest:recalculate-batch-billing', ['--sahodaya' => $fixture['sahodaya']->id, '--event' => $fixture['root']->id])
            ->assertExitCode(0);

        $fixture['sahodaya']->run(function () use ($fixture) {
            $fee = FestSchoolEventFee::where('event_id', $fixture['root']->id)
                ->where('school_id', $fixture['school']->id)->whereNotNull('registration_batch_id')->first();
            // Still frozen at the stale, pre-second-item total after a dry run.
            $this->assertSame(50.0, (float) $fee->total_due);
            $this->assertSame(50.0, (float) $fee->amount_paid);
        });

        $this->artisan('fest:recalculate-batch-billing', [
            '--sahodaya' => $fixture['sahodaya']->id,
            '--event' => $fixture['root']->id,
            '--commit' => true,
        ])->assertExitCode(0);

        $fixture['sahodaya']->run(function () use ($fixture) {
            $fee = FestSchoolEventFee::where('event_id', $fixture['root']->id)
                ->where('school_id', $fixture['school']->id)->whereNotNull('registration_batch_id')->first();
            // Now reflects BOTH items (50 + 50 = 100) -- the second item registered after
            // the invoice was paid is finally billed.
            $this->assertSame(100.0, (float) $fee->total_due);
            // amount_paid is never touched by recalculation, forced or not.
            $this->assertSame(50.0, (float) $fee->amount_paid);
        });
    }

    /**
     * A school pays for ONE registered item, then registers a SECOND item afterward --
     * total_due stays frozen at the paid ("immutable") amount, silently excluding the
     * second item's fee, exactly the live gap this command exists to correct.
     *
     * @return array{sahodaya: Tenant, school: Tenant, root: FestEvent}
     */
    private function fixtureWithStaleInvoice(string $suffix): array
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => "Recalc Test Sahodaya {$suffix}",
            'domain' => "recalc-sahodaya-{$suffix}.test",
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => strtoupper($suffix),
            'student_data_mode' => 'counts_only',
        ]);
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'parent_id' => $sahodaya->id,
            'name' => "Recalc Test School {$suffix}",
            'domain' => "recalc-school-{$suffix}.test",
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        [$root, $item1, $item2, $registration1] = $sahodaya->run(function () use ($sahodaya, $school) {
            $root = FestEvent::create([
                'tenant_id' => $sahodaya->id,
                'title' => 'Recalc Test Kalotsav',
                'event_type' => 'kalotsavam',
                'level_round' => 'sahodaya',
                'status' => 'registration_open',
                'fee_type' => 'none',
                'fee_settings' => ['fee_model' => 'item_catalog'],
                'workflow_mode' => FestPhasedWorkflowService::MODE,
                'phase_mode_enabled' => true,
                'conduct_mode' => 'partitioned',
            ]);
            $batch = FestRegistrationBatch::create([
                'event_id' => $root->id,
                'code' => 'LEVEL_1',
                'name' => 'Level 1',
                'sort_order' => 1,
                'school_base_fee' => 0,
                'invoice_prefix' => 'RCT-L1',
                'status' => 'registration_open',
            ]);
            $phase = FestEventPhase::create([
                'event_id' => $root->id,
                'name' => 'General',
                'code' => 'GEN',
                'registration_batch_id' => $batch->id,
            ]);
            $item1 = FestEventItem::create(['event_id' => $root->id, 'phase_id' => $phase->id, 'title' => 'Item 1', 'fee_amount' => 50, 'is_enabled' => true]);
            $item2 = FestEventItem::create(['event_id' => $root->id, 'phase_id' => $phase->id, 'title' => 'Item 2', 'fee_amount' => 50, 'is_enabled' => true]);

            $registration1 = FestRegistration::create([
                'event_id' => $root->id,
                'item_id' => $item1->id,
                'school_id' => $school->id,
                'status' => 'approved',
                'submitted_at' => now(),
            ]);

            app(FestRegistrationBatchFeeService::class)->recalculateAll($root, $school->id);

            return [$root, $item1, $item2, $registration1];
        });

        $sahodaya->run(function () use ($root, $item2, $school) {
            $fee = FestSchoolEventFee::where('event_id', $root->id)->where('school_id', $school->id)
                ->whereNotNull('registration_batch_id')->firstOrFail();
            \App\Models\FeeReceipt::create([
                'feeable_type' => $fee->getMorphClass(),
                'feeable_id' => $fee->id,
                'file_path' => 'test-receipts/placeholder.pdf',
                'amount' => $fee->total_due,
                'status' => \App\Models\FeeReceipt::STATUS_APPROVED,
                'payment_date' => now(),
            ]);
            $fee->refreshPaidState();

            // Registered AFTER payment -- an ordinary registration action, not the bug
            // itself. This is what recalculateBatch()'s immutability guard then silently
            // never bills for.
            FestRegistration::create([
                'event_id' => $root->id,
                'item_id' => $item2->id,
                'school_id' => $school->id,
                'status' => 'approved',
                'submitted_at' => now(),
            ]);
            app(FestRegistrationBatchFeeService::class)->recalculateAll($root, $school->id);
        });

        return ['sahodaya' => $sahodaya, 'school' => $school, 'root' => $root];
    }
}
