<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * FestEvent::regionDropdownOptions() (used by sportEventDropdownOptions(), which backs
 * the "SELECT REGION" switcher on Registrations/Marks/Results/... for a
 * phased_regional_billing event) previously only asked childrenForRoles(['region']) —
 * a non-regional phase (partition_role='phase', e.g. Digi Fest, District Kalotsav) has
 * exactly one operational leaf but was never a selectable option at all. And because the
 * label was the bare region name with no phase context, two different regional phases
 * reusing the same region name (e.g. both "Off Stage" and "Sargadhara" having their own
 * "Tirur Region" leaf) rendered as two identical, indistinguishable "Tirur Region"
 * options. Both are fixed by including 'phase' leaves too and prefixing every option
 * with its own phase's name.
 */
class FestEventRegionDropdownOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dropdown_includes_non_regional_phases_and_disambiguates_duplicate_region_names(): void
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Dropdown Options Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'DO',
            'student_data_mode' => 'counts_only',
            'active_academic_year' => '2025-26',
        ]);

        $tirur = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Tirur Region', 'code' => 'TIR', 'is_active' => true]);
        $nilambur = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Nilambur Region', 'code' => 'NIL', 'is_active' => true]);

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Dropdown Options Kalotsav', 'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned', 'workflow_mode' => 'phased_regional_billing',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        $digiFest = FestEventPhase::create(['event_id' => $hub->id, 'name' => 'Digi Fest', 'code' => 'DIGI', 'sort_order' => 1, 'is_regional' => false]);
        $offStage = FestEventPhase::create(['event_id' => $hub->id, 'name' => 'Off Stage', 'code' => 'OFF', 'sort_order' => 2, 'is_regional' => true]);
        $sargadhara = FestEventPhase::create(['event_id' => $hub->id, 'name' => 'Sargadhara', 'code' => 'SARG', 'sort_order' => 3, 'is_regional' => true]);

        $digiLeaf = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Digi Fest leaf', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
            'parent_event_id' => $hub->id, 'source_phase_id' => $digiFest->id, 'partition_role' => 'phase',
        ]);
        $offTirur = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Off Stage — Tirur leaf', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
            'parent_event_id' => $hub->id, 'source_phase_id' => $offStage->id, 'partition_role' => 'region', 'region_id' => $tirur->id,
        ]);
        $sargTirur = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Sargadhara — Tirur leaf', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
            'parent_event_id' => $hub->id, 'source_phase_id' => $sargadhara->id, 'partition_role' => 'region', 'region_id' => $tirur->id,
        ]);
        $offNilambur = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Off Stage — Nilambur leaf', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
            'parent_event_id' => $hub->id, 'source_phase_id' => $offStage->id, 'partition_role' => 'region', 'region_id' => $nilambur->id,
        ]);

        $options = $digiLeaf->sportEventDropdownOptions();
        $byId = collect($options)->keyBy('id');

        $this->assertSame($hub->id, $options[0]['id'], 'hub "All Regions" option must come first');

        // Non-regional phase leaf is now selectable at all.
        $this->assertSame('Digi Fest', $byId[$digiLeaf->id]['title']);

        // Same region name under two different phases is disambiguated by phase name.
        $this->assertSame('Off Stage — Tirur Region', $byId[$offTirur->id]['title']);
        $this->assertSame('Sargadhara — Tirur Region', $byId[$sargTirur->id]['title']);
        $this->assertNotSame($byId[$offTirur->id]['title'], $byId[$sargTirur->id]['title']);

        $this->assertSame('Off Stage — Nilambur Region', $byId[$offNilambur->id]['title']);
    }
}
