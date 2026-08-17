<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestRegistration;
use App\Models\FestRegistrationBatch;
use App\Models\FestSchoolEventFee;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\FestEventPhaseService;
use App\Services\Events\FestInvoiceService;
use App\Services\Events\FestPhasePublicationService;
use App\Services\Events\FestPhaseTopologyService;
use App\Services\Events\FestPhasedWorkflowService;
use App\Services\Events\FestRegistrationBatchFeeService;
use App\Services\Events\FestRegistrationRouterService;
use App\Services\Events\FestSchoolPhaseRegionService;
use App\Services\Events\Reports\FestReportScopeResolver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestPhasedRegionalBillingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_four_phases_route_independently_and_generate_two_invoices(): void
    {
        [$sahodaya, $school, $root, $regions] = $this->fixture();

        $configured = $this->configureMcsFixture($root, [$regions[0]->id, $regions[1]->id], [$regions[1]->id, $regions[2]->id]);

        $this->assertSame(['LEVEL_1', 'LEVEL_2'], $configured['batches']->pluck('code')->all());
        $this->assertSame(['DIGI', 'OFF_STAGE', 'SARGADHARA', 'DISTRICT'], $configured['phases']->pluck('code')->all());
        $this->assertSame(4000.0, (float) $configured['batches']->firstWhere('code', 'LEVEL_1')->school_base_fee);

        $fees = ['DIGI' => 100, 'OFF_STAGE' => 200, 'SARGADHARA' => 300, 'DISTRICT' => 400];
        $sourceItems = collect($configured['phases'])->mapWithKeys(function ($phase) use ($root, $fees) {
            $item = FestEventItem::create([
                'event_id' => $root->id,
                'phase_id' => $phase->id,
                'title' => $phase->name.' Item',
                'item_code' => $phase->code.'-01',
                'fee_amount' => $fees[$phase->code],
                'is_enabled' => true,
            ]);

            return [$phase->code => $item];
        });

        $leaves = app(FestPhaseTopologyService::class)->sync($root->fresh());
        $this->assertCount(6, $leaves);

        $offStage = $configured['phases']->firstWhere('code', 'OFF_STAGE');
        $sargadhara = $configured['phases']->firstWhere('code', 'SARGADHARA');
        $selector = app(FestSchoolPhaseRegionService::class);
        $selector->select($root, $offStage, $school->id, $regions[0]->id);
        $selector->select($root, $sargadhara, $school->id, $regions[2]->id);

        $router = app(FestRegistrationRouterService::class);
        $offLeaf = $router->resolveTargetEvent($root, $sourceItems['OFF_STAGE'], $school->id);
        $sargadharaLeaf = $router->resolveTargetEvent($root, $sourceItems['SARGADHARA'], $school->id);
        $this->assertSame($regions[0]->id, $offLeaf->region_id);
        $this->assertSame($regions[2]->id, $sargadharaLeaf->region_id);
        $this->assertNotSame($offLeaf->id, $sargadharaLeaf->id);

        foreach ($sourceItems as $code => $sourceItem) {
            $leaf = $router->resolveTargetEvent($root, $sourceItem, $school->id);
            $leafItem = FestEventItem::where('event_id', $leaf->id)
                ->where('inherited_from_item_id', $sourceItem->id)
                ->firstOrFail();
            FestRegistration::create([
                'event_id' => $leaf->id,
                'item_id' => $leafItem->id,
                'school_id' => $school->id,
                'status' => 'approved',
                'submitted_at' => now(),
            ]);
        }

        $batchFees = app(FestRegistrationBatchFeeService::class)->recalculateAll($root, $school->id)->keyBy('registrationBatch.code');
        $this->assertSame(4300.0, (float) $batchFees['LEVEL_1']->total_due, $batchFees['LEVEL_1']->lines->map(fn ($line) => "{$line->label}:{$line->amount}")->implode(', '));
        $this->assertSame(700.0, (float) $batchFees['LEVEL_2']->total_due);
        $this->assertSame(5000.0, (float) FestSchoolEventFee::where('event_id', $root->id)
            ->where('school_id', $school->id)
            ->whereNull('registration_batch_id')
            ->value('total_due'));

        $invoices = $configured['batches']->map(fn ($batch) => app(FestInvoiceService::class)
            ->issueForSchool($root, $school, registrationBatchId: $batch->id));
        $this->assertCount(2, $invoices->unique('id'));
        $this->assertSame(['LEVEL_1', 'LEVEL_2'], $invoices->pluck('registrationBatch.code')->all());
        $this->assertEqualsCanonicalizing([4300.0, 700.0], $invoices->pluck('total_amount')->map(fn ($amount) => (float) $amount)->all());
    }

    public function test_phase_publication_promotes_only_after_all_of_its_regions_publish(): void
    {
        [, , $root, $regions] = $this->fixture();
        $configured = $this->configureMcsFixture($root, [$regions[0]->id, $regions[1]->id], [$regions[1]->id, $regions[2]->id]);
        $leaves = app(FestPhaseTopologyService::class)->sync($root->fresh());
        $offStage = $configured['phases']->firstWhere('code', 'OFF_STAGE');
        $offLeaves = $leaves->where('source_phase_id', $offStage->id)->values();

        app(FestPhasePublicationService::class)->publishSchedule($offLeaves[0]);
        $this->assertFalse((bool) $offStage->fresh()->schedule_published);

        app(FestPhasePublicationService::class)->publishSchedule($offLeaves[1]);
        $this->assertTrue((bool) $offStage->fresh()->schedule_published);
        $this->assertFalse((bool) $root->fresh()->schedule_published);
    }

    public function test_reports_can_scope_by_phase_region_and_payment_level(): void
    {
        [$sahodaya, $school, $root, $regions] = $this->fixture();
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id]);
        $admin->assignRole('sahodaya_admin');

        $configured = $this->configureMcsFixture($root, [$regions[0]->id, $regions[1]->id], [$regions[1]->id, $regions[2]->id]);
        app(FestPhaseTopologyService::class)->sync($root->fresh());
        $offStage = $configured['phases']->firstWhere('code', 'OFF_STAGE');
        app(FestSchoolPhaseRegionService::class)->select($root, $offStage, $school->id, $regions[0]->id);

        $resolver = app(FestReportScopeResolver::class);
        $regional = $resolver->resolve($root, $admin, [
            'mode' => 'region',
            'region_id' => $regions[0]->id,
            'competition_phase_id' => $offStage->id,
        ]);
        $this->assertCount(1, $regional->eventIds);
        $this->assertSame([$school->id], $regional->schoolIds);
        $this->assertSame($offStage->id, FestEvent::findOrFail($regional->eventIds[0])->source_phase_id);

        $level1 = $configured['batches']->firstWhere('code', 'LEVEL_1');
        $levelScope = $resolver->resolve($root, $admin, [
            'mode' => 'combined',
            'registration_batch_id' => $level1->id,
        ]);
        $this->assertCount(3, $levelScope->eventIds);
        $this->assertSame($level1->id, $levelScope->registrationBatchId);
        $this->assertEqualsCanonicalizing(
            $configured['phases']->whereIn('code', ['DIGI', 'OFF_STAGE'])->pluck('id')->all(),
            FestEvent::whereIn('id', $levelScope->eventIds)->pluck('source_phase_id')->unique()->all(),
        );
    }

    /**
     * Builds the "MCS 2026" batch+phase shape (2 payment batches, 4 conduct phases) using
     * the generic, Sahodaya-configurable primitives (FestRegistrationBatch::create() — the
     * same write FestRegistrationBatchController::store() does — and the existing generic
     * FestEventPhaseService::createPhase()) instead of the removed
     * FestPhasedWorkflowService::configureMcs2026() one-click bootstrap that used to be the
     * only way to create this shape. Proves the generic mechanism reproduces the exact
     * configuration a Sahodaya admin now builds by hand via the Phases page, not just this
     * one hardcoded example.
     *
     * @param  list<int>  $offStageRegionIds
     * @param  list<int>  $sargadharaRegionIds
     * @return array{batches: \Illuminate\Support\Collection, phases: \Illuminate\Support\Collection}
     */
    private function configureMcsFixture(FestEvent $root, array $offStageRegionIds, array $sargadharaRegionIds): array
    {
        $root->update([
            'workflow_mode' => FestPhasedWorkflowService::MODE,
            'phase_mode_enabled' => true,
            'conduct_mode' => 'partitioned',
        ]);

        $level1 = FestRegistrationBatch::create([
            'event_id' => $root->id,
            'code' => 'LEVEL_1',
            'name' => 'Level 1',
            'sort_order' => 1,
            'school_base_fee' => 4000,
            'invoice_prefix' => 'MCS-L1',
            'status' => 'registration_open',
            'registration_close' => '2026-08-31 23:59:59',
        ]);
        $level2 = FestRegistrationBatch::create([
            'event_id' => $root->id,
            'code' => 'LEVEL_2',
            'name' => 'Level 2',
            'sort_order' => 2,
            'school_base_fee' => 0,
            'invoice_prefix' => 'MCS-L2',
            'status' => 'registration_open',
            'registration_close' => '2026-09-08 23:59:59',
        ]);

        $phaseService = app(FestEventPhaseService::class);
        $workflow = app(FestPhasedWorkflowService::class);

        $definitions = [
            ['code' => 'DIGI', 'name' => 'Digi Fest', 'sort_order' => 1, 'batch' => $level1, 'regional' => false, 'regions' => []],
            ['code' => 'OFF_STAGE', 'name' => 'Off Stage', 'sort_order' => 2, 'batch' => $level1, 'regional' => true, 'regions' => $offStageRegionIds],
            ['code' => 'SARGADHARA', 'name' => 'Sargadhara', 'sort_order' => 3, 'batch' => $level2, 'regional' => true, 'regions' => $sargadharaRegionIds],
            ['code' => 'DISTRICT', 'name' => 'District Kalotsav', 'sort_order' => 4, 'batch' => $level2, 'regional' => false, 'regions' => []],
        ];

        $phases = collect();
        foreach ($definitions as $definition) {
            $phase = $phaseService->createPhase($root, [
                'name' => $definition['name'],
                'code' => $definition['code'],
                'sort_order' => $definition['sort_order'],
                'registration_batch_id' => $definition['batch']->id,
                'is_regional' => $definition['regional'],
            ]);
            $phase->update([
                'registration_open' => $definition['batch']->registration_open,
                'registration_close' => $definition['batch']->registration_close,
                'status' => $definition['batch']->status,
            ]);
            if ($definition['regions'] !== []) {
                $workflow->syncAllowedRegions($phase, $definition['regions']);
            }
            $phases->push($phase->fresh(['registrationBatch', 'allowedRegions.region']));
        }

        return [
            'batches' => collect([$level1->fresh(), $level2->fresh()]),
            'phases' => $phases,
        ];
    }

    private function fixture(): array
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'MCS Test Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'MCS',
            'student_data_mode' => 'counts_only',
        ]);
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'parent_id' => $sahodaya->id,
            'name' => 'Workflow School',
            'domain' => Str::uuid().'.test',
            'membership_status' => 'approved',
            'is_active' => true,
        ]);
        $root = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'MCS District Kalotsav 2026',
            'event_type' => 'kalotsavam',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
            'fee_type' => 'none',
            'fee_settings' => ['fee_model' => 'item_catalog'],
        ]);
        $regions = collect(['Tirur', 'Manjeri', 'Nilambur'])->map(fn (string $name, int $index) => Region::create([
            'tenant_id' => $sahodaya->id,
            'name' => $name,
            'code' => strtoupper(substr($name, 0, 3)).$index,
            'is_active' => true,
        ]));

        return [$sahodaya, $school, $root, $regions];
    }
}
