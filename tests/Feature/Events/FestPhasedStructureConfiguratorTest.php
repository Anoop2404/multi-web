<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestRegistrationBatch;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Services\Events\FestPhasedStructureConfigurator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class FestPhasedStructureConfiguratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_plan_without_writing_anything(): void
    {
        [$root, $regions] = $this->fixture();
        $config = $this->config($root, $regions);

        $preview = app(FestPhasedStructureConfigurator::class)->configure($root, $config, commit: false);

        $this->assertSame(['LEVEL_1', 'LEVEL_2'], $preview['batches']->pluck('code')->all());
        $this->assertSame(['create', 'create'], $preview['batches']->pluck('action')->all());
        $this->assertSame(['DIGI', 'OFF_STAGE', 'SARGADHARA', 'DISTRICT'], $preview['phases']->pluck('code')->all());
        $this->assertSame(['create', 'create', 'create', 'create'], $preview['phases']->pluck('action')->all());
        $this->assertSame([1, 1, 1, 1], $preview['phases']->pluck('item_count')->all());
        $this->assertTrue($preview['unmapped_items']->isEmpty());

        $this->assertSame(0, FestRegistrationBatch::where('event_id', $root->id)->count());
        $this->assertSame(0, FestEventPhase::where('event_id', $root->id)->count());
        $this->assertSame('standard', $root->fresh()->workflow_mode);
    }

    public function test_commit_creates_batches_phases_regions_and_item_assignment(): void
    {
        [$root, $regions] = $this->fixture();
        $config = $this->config($root, $regions);

        app(FestPhasedStructureConfigurator::class)->configure($root, $config, commit: true);

        $batches = FestRegistrationBatch::where('event_id', $root->id)->orderBy('sort_order')->get();
        $this->assertSame(['LEVEL_1', 'LEVEL_2'], $batches->pluck('code')->all());
        $this->assertSame('4000.00', (string) $batches->firstWhere('code', 'LEVEL_1')->school_base_fee);

        $phases = FestEventPhase::where('event_id', $root->id)->orderBy('sort_order')->get();
        $this->assertSame(['DIGI', 'OFF_STAGE', 'SARGADHARA', 'DISTRICT'], $phases->pluck('code')->all());

        $offStage = $phases->firstWhere('code', 'OFF_STAGE');
        $sargadhara = $phases->firstWhere('code', 'SARGADHARA');
        $this->assertTrue($offStage->is_regional);
        $this->assertTrue($sargadhara->is_regional);
        $this->assertFalse($phases->firstWhere('code', 'DIGI')->is_regional);
        $this->assertFalse($phases->firstWhere('code', 'DISTRICT')->is_regional);

        // Off Stage and Sargadhara have independent, non-identical region lists.
        $this->assertEqualsCanonicalizing(
            [$regions[0]->id, $regions[1]->id],
            $offStage->allowedRegions()->pluck('region_id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$regions[0]->id, $regions[2]->id],
            $sargadhara->allowedRegions()->pluck('region_id')->all(),
        );

        // Item assignment landed on the correct phase.
        $itemsByCode = FestEventItem::where('event_id', $root->id)->get()->keyBy('item_code');
        $this->assertSame($phases->firstWhere('code', 'DIGI')->id, $itemsByCode['DIGI1']->phase_id);
        $this->assertSame($offStage->id, $itemsByCode['OFF1']->phase_id);
        $this->assertSame($sargadhara->id, $itemsByCode['SAR1']->phase_id);
        $this->assertSame($phases->firstWhere('code', 'DISTRICT')->id, $itemsByCode['DIS1']->phase_id);
    }

    public function test_commit_is_idempotent_on_rerun(): void
    {
        [$root, $regions] = $this->fixture();
        $config = $this->config($root, $regions);
        $configurator = app(FestPhasedStructureConfigurator::class);

        $configurator->configure($root, $config, commit: true);
        $second = $configurator->configure($root, $config, commit: true);

        $this->assertSame(2, FestRegistrationBatch::where('event_id', $root->id)->count());
        $this->assertSame(4, FestEventPhase::where('event_id', $root->id)->count());
        $this->assertSame(['unchanged', 'unchanged'], $second['batches']->pluck('action')->all());
        $this->assertSame(['unchanged', 'unchanged', 'unchanged', 'unchanged'], $second['phases']->pluck('action')->all());
    }

    public function test_reports_unmapped_enabled_items_as_blocking(): void
    {
        [$root, $regions] = $this->fixture();
        FestEventItem::create(['event_id' => $root->id, 'title' => 'Unmapped Item', 'item_code' => 'UNMAPPED1', 'is_enabled' => true]);
        $config = $this->config($root, $regions);

        $preview = app(FestPhasedStructureConfigurator::class)->configure($root, $config, commit: false);

        $this->assertSame(['UNMAPPED1'], $preview['unmapped_items']->pluck('item_code')->all());
    }

    public function test_rejects_region_code_unknown_to_this_tenant(): void
    {
        [$root, $regions] = $this->fixture();
        $config = $this->config($root, $regions);
        $config['phases'][1]['region_codes'] = ['NOT_A_REAL_REGION'];

        $this->expectException(HttpException::class);
        app(FestPhasedStructureConfigurator::class)->configure($root, $config, commit: false);
    }

    public function test_rejects_item_code_not_present_on_event(): void
    {
        [$root, $regions] = $this->fixture();
        $config = $this->config($root, $regions);
        $config['item_phase_map']['NOT_A_REAL_ITEM'] = 'DIGI';

        $this->expectException(HttpException::class);
        app(FestPhasedStructureConfigurator::class)->configure($root, $config, commit: false);
    }

    /** @return array{0: FestEvent, 1: \Illuminate\Support\Collection<int, Region>} */
    private function fixture(): array
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Configurator Test Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'CFG',
            'student_data_mode' => 'counts_only',
        ]);
        $root = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Configurator Test Kalotsav',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'draft',
            'fee_type' => 'none',
        ]);
        $regions = collect(['Tirur', 'Nilambur', 'Manjeri'])->map(fn (string $name, int $i) => Region::create([
            'tenant_id' => $sahodaya->id,
            'name' => $name,
            'code' => strtoupper(substr($name, 0, 3)).$i,
            'is_active' => true,
        ]));

        FestEventItem::create(['event_id' => $root->id, 'title' => 'Digi Item', 'item_code' => 'DIGI1', 'is_enabled' => true]);
        FestEventItem::create(['event_id' => $root->id, 'title' => 'Off Stage Item', 'item_code' => 'OFF1', 'is_enabled' => true]);
        FestEventItem::create(['event_id' => $root->id, 'title' => 'Sargadhara Item', 'item_code' => 'SAR1', 'is_enabled' => true]);
        FestEventItem::create(['event_id' => $root->id, 'title' => 'District Item', 'item_code' => 'DIS1', 'is_enabled' => true]);

        return [$root, $regions];
    }

    private function config(FestEvent $root, \Illuminate\Support\Collection $regions): array
    {
        $regionCode = fn (int $i) => $regions[$i]->code;

        return [
            'tenant_id' => $root->tenant_id,
            'batches' => [
                ['code' => 'LEVEL_1', 'name' => 'Level 1', 'school_base_fee' => 4000, 'sort_order' => 1],
                ['code' => 'LEVEL_2', 'name' => 'Level 2', 'school_base_fee' => 0, 'sort_order' => 2],
            ],
            'phases' => [
                ['code' => 'DIGI', 'name' => 'Digi Fest', 'batch_code' => 'LEVEL_1', 'is_regional' => false, 'region_codes' => [], 'sort_order' => 1],
                ['code' => 'OFF_STAGE', 'name' => 'Off Stage', 'batch_code' => 'LEVEL_1', 'is_regional' => true, 'region_codes' => [$regionCode(0), $regionCode(1)], 'sort_order' => 2],
                ['code' => 'SARGADHARA', 'name' => 'Sargadhara', 'batch_code' => 'LEVEL_2', 'is_regional' => true, 'region_codes' => [$regionCode(0), $regionCode(2)], 'sort_order' => 3],
                ['code' => 'DISTRICT', 'name' => 'District Kalotsav', 'batch_code' => 'LEVEL_2', 'is_regional' => false, 'region_codes' => [], 'sort_order' => 4],
            ],
            'item_phase_map' => [
                'DIGI1' => 'DIGI',
                'OFF1' => 'OFF_STAGE',
                'SAR1' => 'SARGADHARA',
                'DIS1' => 'DISTRICT',
            ],
        ];
    }
}
