<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestRegistrationBatch;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestConfigurePhasedStructureTest extends TestCase
{
    use RefreshDatabase;

    private ?string $configPath = null;

    protected function tearDown(): void
    {
        if ($this->configPath && is_file($this->configPath)) {
            unlink($this->configPath);
        }
        parent::tearDown();
    }

    public function test_dry_run_reports_plan_and_writes_nothing(): void
    {
        [$sahodaya, $root, $regions] = $this->fixture();
        $configPath = $this->writeConfig($root, $regions);

        $this->artisan('fest:configure-phased-structure', [
            '--sahodaya' => $sahodaya->id,
            '--event' => $root->id,
            '--config' => $configPath,
        ])
            ->expectsOutputToContain('Dry-run only')
            ->assertExitCode(0);

        $this->assertSame(0, FestRegistrationBatch::where('event_id', $root->id)->count());
        $this->assertSame(0, FestEventPhase::where('event_id', $root->id)->count());
        $this->assertSame('standard', $root->fresh()->workflow_mode);
    }

    public function test_commit_enables_phased_mode_and_syncs_topology(): void
    {
        [$sahodaya, $root, $regions] = $this->fixture();
        $configPath = $this->writeConfig($root, $regions);

        $this->artisan('fest:configure-phased-structure', [
            '--sahodaya' => $sahodaya->id,
            '--event' => $root->id,
            '--config' => $configPath,
            '--commit' => true,
        ])
            ->expectsOutputToContain('Committed.')
            ->assertExitCode(0);

        $root->refresh();
        $this->assertSame('phased_regional_billing', $root->workflow_mode);
        $this->assertTrue((bool) $root->phase_mode_enabled);
        $this->assertSame('partitioned', $root->conduct_mode);

        // DIGI (1 common) + OFF_STAGE (2 regions) + SARGADHARA (2 regions) + DISTRICT (1 common) = 6 leaves.
        $this->assertSame(6, FestEvent::where('parent_event_id', $root->id)->count());
    }

    public function test_commit_is_refused_when_an_enabled_item_is_unmapped(): void
    {
        [$sahodaya, $root, $regions] = $this->fixture();
        FestEventItem::create(['event_id' => $root->id, 'title' => 'Unmapped', 'item_code' => 'UNMAPPED1', 'is_enabled' => true]);
        $configPath = $this->writeConfig($root, $regions);

        $this->artisan('fest:configure-phased-structure', [
            '--sahodaya' => $sahodaya->id,
            '--event' => $root->id,
            '--config' => $configPath,
            '--commit' => true,
        ])
            ->expectsOutputToContain('Refusing to --commit')
            ->assertExitCode(1);

        $this->assertSame(0, FestRegistrationBatch::where('event_id', $root->id)->count());
        $this->assertSame('standard', $root->fresh()->workflow_mode);
    }

    /** @return array{0: Tenant, 1: FestEvent, 2: \Illuminate\Support\Collection<int, Region>} */
    private function fixture(): array
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Command Test Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'CMD',
            'student_data_mode' => 'counts_only',
        ]);
        $root = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Command Test Kalotsav',
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

        return [$sahodaya, $root, $regions];
    }

    private function writeConfig(FestEvent $root, \Illuminate\Support\Collection $regions): string
    {
        $config = [
            'tenant_id' => $root->tenant_id,
            'batches' => [
                ['code' => 'LEVEL_1', 'name' => 'Level 1', 'school_base_fee' => 4000, 'sort_order' => 1],
                ['code' => 'LEVEL_2', 'name' => 'Level 2', 'school_base_fee' => 0, 'sort_order' => 2],
            ],
            'phases' => [
                ['code' => 'DIGI', 'name' => 'Digi Fest', 'batch_code' => 'LEVEL_1', 'is_regional' => false, 'region_codes' => [], 'sort_order' => 1],
                ['code' => 'OFF_STAGE', 'name' => 'Off Stage', 'batch_code' => 'LEVEL_1', 'is_regional' => true, 'region_codes' => [$regions[0]->code, $regions[1]->code], 'sort_order' => 2],
                ['code' => 'SARGADHARA', 'name' => 'Sargadhara', 'batch_code' => 'LEVEL_2', 'is_regional' => true, 'region_codes' => [$regions[0]->code, $regions[2]->code], 'sort_order' => 3],
                ['code' => 'DISTRICT', 'name' => 'District Kalotsav', 'batch_code' => 'LEVEL_2', 'is_regional' => false, 'region_codes' => [], 'sort_order' => 4],
            ],
            'item_phase_map' => [
                'DIGI1' => 'DIGI',
                'OFF1' => 'OFF_STAGE',
                'SAR1' => 'SARGADHARA',
                'DIS1' => 'DISTRICT',
            ],
        ];

        $this->configPath = tempnam(sys_get_temp_dir(), 'mcs_phase_plan_').'.php';
        file_put_contents($this->configPath, '<?php return '.var_export($config, true).';');

        return $this->configPath;
    }
}
