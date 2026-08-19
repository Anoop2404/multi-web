<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestParticipant;
use App\Models\FestPhaseRegion;
use App\Models\FestRegistration;
use App\Models\FestSchoolPhaseRegionSelection;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Services\Events\FestPartitionService;
use App\Services\Events\FestRegionRoundMigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Shaped after the real production scenario that prompted this service: a hub already
 * running as two live region-partition children (Tirur/Manjeri) with real registrations,
 * participants, and chest numbers already issued — converting to a 4-phase-style structure
 * where the catalog is genuinely mixed across target phases, not a clean 1:1 relabel.
 */
class FestRegionRoundMigrationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrate_adopts_regional_items_in_place_and_merges_common_items(): void
    {
        $fixture = $this->fixture();

        $service = app(FestRegionRoundMigrationService::class);
        $result = $service->migrate($fixture['root'], $this->config($fixture), commit: true);

        $root = $fixture['root']->fresh();
        $this->assertSame('phased_regional_billing', $root->workflow_mode);
        $this->assertSame('partitioned', $root->conduct_mode);
        $this->assertFalse(app(FestPartitionService::class)->hasLegacyPartitions($root));

        $tirur = $fixture['tirurChild']->fresh();
        $manjeri = $fixture['manjeriChild']->fresh();
        $offStage = FestEventPhase::where('event_id', $root->id)->where('code', 'OFF_STAGE')->firstOrFail();

        // Adoption: same FestEvent rows, now tagged with the new-system fields.
        $this->assertSame($offStage->id, $tirur->source_phase_id);
        $this->assertSame($offStage->id, $manjeri->source_phase_id);
        $this->assertSame('phased_regional_billing', $tirur->workflow_mode);

        // Live lifecycle was preserved, not reset to the freshly-created phase's defaults.
        $this->assertTrue((bool) $tirur->schedule_published);
        $this->assertSame('registration_open', $tirur->status);
        $this->assertSame($fixture['tirurRegClose']->toDateTimeString(), $tirur->registration_close->toDateTimeString());

        // Staying item: same row, same id, just phase-tagged now. Its registration never moved.
        $offStageItem = FestEventItem::find($fixture['tirurOffStageItem']->id);
        $this->assertNotNull($offStageItem);
        $this->assertSame($tirur->id, $offStageItem->event_id);
        $this->assertNotNull($offStageItem->phase_id);
        $this->assertSame(1, FestRegistration::where('id', $fixture['tirurOffStageReg']->id)->where('event_id', $tirur->id)->count());

        // Common-phase item: both regions' old copies are gone, exactly one canonical
        // item remains, and both registrations now point at it.
        $this->assertNull(FestEventItem::find($fixture['tirurDigiItem']->id));
        $this->assertNull(FestEventItem::find($fixture['manjeriDigiItem']->id));
        $canonicalDigi = FestEventItem::where('item_code', 'DIGI_A')->where('event_id', '!=', $root->id)->get();
        $this->assertCount(1, $canonicalDigi, 'exactly one canonical Digi Fest item should remain');
        $digiLeafId = $canonicalDigi->first()->event_id;
        $this->assertSame(2, FestRegistration::where('item_id', $canonicalDigi->first()->id)->count());

        // Chest numbers that collided on the source regions (both started at 1) were
        // renumbered to stay unique under the merged item.
        $digiParticipants = FestParticipant::whereIn('registration_id', FestRegistration::where('item_id', $canonicalDigi->first()->id)->pluck('id'))->get();
        $this->assertCount(2, $digiParticipants);
        $this->assertCount(2, $digiParticipants->pluck('chest_no')->unique(), 'chest numbers must not collide after merge');

        // Regional item moving to a DIFFERENT regional phase: Tirur's Sargadhara item moved
        // to its own new "Sargadhara — Tirur" leaf (not merged with Manjeri, no Sargadhara
        // registration existed there in this fixture).
        $this->assertNull(FestEventItem::find($fixture['tirurSargaItem']->id));
        $sargaLeaf = FestEvent::where('parent_event_id', $root->id)->where('region_id', $fixture['tirur']->id)
            ->whereHas('sourcePhase', fn ($q) => $q->where('code', 'SARGADHARA'))->firstOrFail();
        $sargaItem = FestEventItem::where('event_id', $sargaLeaf->id)->where('item_code', 'SARGA_A')->firstOrFail();
        $this->assertSame(1, FestRegistration::where('item_id', $sargaItem->id)->count());

        // Nothing was dropped or duplicated: 4 registrations in, 4 registrations out.
        $allLeafIds = FestEvent::where('root_event_id', $root->id)->pluck('id')->push($root->id);
        $this->assertSame(4, FestRegistration::whereIn('event_id', $allLeafIds)->count());

        // Schools' existing region choice was backfilled and locked so the new UI never re-asks.
        $tSelection = FestSchoolPhaseRegionSelection::where('event_id', $root->id)->where('phase_id', $offStage->id)->where('school_id', $fixture['schoolT']->id)->first();
        $this->assertNotNull($tSelection);
        $this->assertSame($fixture['tirur']->id, $tSelection->region_id);
        $this->assertNotNull($tSelection->locked_at);
        $this->assertTrue(FestPhaseRegion::where('phase_id', $offStage->id)->where('region_id', $fixture['tirur']->id)->exists());
        $this->assertTrue(FestPhaseRegion::where('phase_id', $offStage->id)->where('region_id', $fixture['manjeri']->id)->exists());

        $this->assertNotNull($result['leaves']);
    }

    public function test_dry_run_reports_the_plan_without_writing_anything(): void
    {
        $fixture = $this->fixture();
        $service = app(FestRegionRoundMigrationService::class);

        $result = $service->migrate($fixture['root'], $this->config($fixture), commit: false);

        $this->assertNull($result['leaves']);
        $this->assertSame('standard', $fixture['root']->fresh()->workflow_mode);
        $this->assertNotNull(FestEventItem::find($fixture['tirurDigiItem']->id), 'dry run must not touch the database');

        $plan = collect($result['adoption'])->firstWhere('child.id', $fixture['tirurChild']->id);
        $this->assertNotNull($plan);
        $this->assertCount(1, $plan['stayingItems']);
        $this->assertCount(2, $plan['leavingItems']);
    }

    /** @return array<string, mixed> */
    private function fixture(): array
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Region Migration Test Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'RMT', 'student_data_mode' => 'counts_only']);

        $schoolT = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Tirur School', 'domain' => Str::uuid().'.test', 'membership_status' => 'approved', 'is_active' => true,
        ]);
        $schoolM = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Manjeri School', 'domain' => Str::uuid().'.test', 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $tirur = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Tirur', 'code' => 'TIR', 'is_active' => true]);
        $manjeri = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Manjeri', 'code' => 'MAN', 'is_active' => true]);

        $root = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Region Migration Test Kalotsav', 'event_type' => 'kalotsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open', 'conduct_mode' => 'partitioned',
            'fee_type' => 'none', 'fee_settings' => ['fee_model' => 'item_catalog'],
        ]);

        // Root's own catalog — what an item-phase config maps by item_code.
        $rootOffStage = FestEventItem::create(['event_id' => $root->id, 'title' => 'Off Stage Item', 'item_code' => 'OFFSTAGE_A', 'is_enabled' => true]);
        $rootDigi = FestEventItem::create(['event_id' => $root->id, 'title' => 'Digi Fest Item', 'item_code' => 'DIGI_A', 'is_enabled' => true]);
        $rootSarga = FestEventItem::create(['event_id' => $root->id, 'title' => 'Sargadhara Item', 'item_code' => 'SARGA_A', 'is_enabled' => true]);

        $regClose = now()->addWeek();

        $tirurChild = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Region Migration Test Kalotsav — Tirur', 'event_type' => 'kalotsavam',
            'parent_event_id' => $root->id, 'root_event_id' => $root->id,
            'partition_key' => 'tirur', 'cluster_key' => 'tirur', 'partition_role' => 'region', 'region_id' => $tirur->id,
            'level_round' => 'sahodaya', 'status' => 'registration_open', 'conduct_mode' => 'standard',
            'registration_close' => $regClose, 'schedule_published' => true,
        ]);
        $manjeriChild = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Region Migration Test Kalotsav — Manjeri', 'event_type' => 'kalotsavam',
            'parent_event_id' => $root->id, 'root_event_id' => $root->id,
            'partition_key' => 'manjeri', 'cluster_key' => 'manjeri', 'partition_role' => 'region', 'region_id' => $manjeri->id,
            'level_round' => 'sahodaya', 'status' => 'registration_open', 'conduct_mode' => 'standard',
        ]);

        // Each region got its own independent copy of the full catalog, as the legacy
        // FestPartitionService::spawnPartition()/copyItemsToPartition() flow always does.
        $tirurOffStageItem = FestEventItem::create(['event_id' => $tirurChild->id, 'title' => 'Off Stage Item', 'item_code' => 'OFFSTAGE_A', 'is_enabled' => true, 'inherited_from_item_id' => $rootOffStage->id]);
        $tirurDigiItem = FestEventItem::create(['event_id' => $tirurChild->id, 'title' => 'Digi Fest Item', 'item_code' => 'DIGI_A', 'is_enabled' => true, 'inherited_from_item_id' => $rootDigi->id]);
        $tirurSargaItem = FestEventItem::create(['event_id' => $tirurChild->id, 'title' => 'Sargadhara Item', 'item_code' => 'SARGA_A', 'is_enabled' => true, 'inherited_from_item_id' => $rootSarga->id]);
        $manjeriDigiItem = FestEventItem::create(['event_id' => $manjeriChild->id, 'title' => 'Digi Fest Item', 'item_code' => 'DIGI_A', 'is_enabled' => true, 'inherited_from_item_id' => $rootDigi->id]);

        // event_id/chest_head_id are denormalized onto fest_participants purely to scope the
        // (event_id, chest_head_id, chest_no) unique index (see
        // 2026_09_01_000001_fest_chest_scope_per_event_type.php) — real chest-numbered
        // participants always carry them, so the fixture must too for the merge/renumber
        // path to be exercised realistically.
        $tirurOffStageReg = FestRegistration::create(['event_id' => $tirurChild->id, 'item_id' => $tirurOffStageItem->id, 'school_id' => $schoolT->id, 'status' => 'approved', 'submitted_at' => now()]);
        FestParticipant::create(['registration_id' => $tirurOffStageReg->id, 'event_id' => $tirurChild->id, 'chest_head_id' => $tirurOffStageItem->id, 'student_id' => 1, 'participant_type' => 'student', 'chest_no' => 1]);

        $tirurDigiReg = FestRegistration::create(['event_id' => $tirurChild->id, 'item_id' => $tirurDigiItem->id, 'school_id' => $schoolT->id, 'status' => 'approved', 'submitted_at' => now()]);
        FestParticipant::create(['registration_id' => $tirurDigiReg->id, 'event_id' => $tirurChild->id, 'chest_head_id' => $tirurDigiItem->id, 'student_id' => 2, 'participant_type' => 'student', 'chest_no' => 1]);

        $tirurSargaReg = FestRegistration::create(['event_id' => $tirurChild->id, 'item_id' => $tirurSargaItem->id, 'school_id' => $schoolT->id, 'status' => 'approved', 'submitted_at' => now()]);
        FestParticipant::create(['registration_id' => $tirurSargaReg->id, 'event_id' => $tirurChild->id, 'chest_head_id' => $tirurSargaItem->id, 'student_id' => 3, 'participant_type' => 'student', 'chest_no' => 1]);

        $manjeriDigiReg = FestRegistration::create(['event_id' => $manjeriChild->id, 'item_id' => $manjeriDigiItem->id, 'school_id' => $schoolM->id, 'status' => 'approved', 'submitted_at' => now()]);
        // Same chest_no as Tirur's Digi Fest participant — collision the merge must resolve.
        FestParticipant::create(['registration_id' => $manjeriDigiReg->id, 'event_id' => $manjeriChild->id, 'chest_head_id' => $manjeriDigiItem->id, 'student_id' => 4, 'participant_type' => 'student', 'chest_no' => 1]);

        DB::table('fest_rank_points')->insert([
            'event_id' => $root->id, 'rank' => 1, 'points' => 5, 'is_group' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return compact(
            'sahodaya', 'schoolT', 'schoolM', 'tirur', 'manjeri', 'root',
            'tirurChild', 'manjeriChild', 'regClose',
            'tirurOffStageItem', 'tirurDigiItem', 'tirurSargaItem', 'manjeriDigiItem',
            'tirurOffStageReg', 'tirurDigiReg', 'tirurSargaReg', 'manjeriDigiReg',
        ) + ['tirurRegClose' => $regClose];
    }

    private function config(array $fixture): array
    {
        return [
            'tenant_id' => $fixture['sahodaya']->id,
            'batches' => [
                ['code' => 'LEVEL_1', 'name' => 'Level 1', 'school_base_fee' => 4000, 'sort_order' => 1],
                ['code' => 'LEVEL_2', 'name' => 'Level 2', 'school_base_fee' => 0, 'sort_order' => 2],
            ],
            'phases' => [
                ['code' => 'OFF_STAGE', 'name' => 'Off Stage', 'batch_code' => 'LEVEL_1', 'is_regional' => true, 'region_codes' => ['TIR', 'MAN'], 'sort_order' => 1],
                ['code' => 'DIGI_FEST', 'name' => 'Digi Fest', 'batch_code' => 'LEVEL_1', 'is_regional' => false, 'region_codes' => [], 'sort_order' => 2],
                ['code' => 'SARGADHARA', 'name' => 'Sargadhara', 'batch_code' => 'LEVEL_2', 'is_regional' => true, 'region_codes' => ['TIR', 'MAN'], 'sort_order' => 3],
            ],
            'item_phase_map' => [
                'OFFSTAGE_A' => 'OFF_STAGE',
                'DIGI_A' => 'DIGI_FEST',
                'SARGA_A' => 'SARGADHARA',
            ],
            'legacy_adoption' => [
                $fixture['tirurChild']->id => 'OFF_STAGE',
                $fixture['manjeriChild']->id => 'OFF_STAGE',
            ],
        ];
    }
}
