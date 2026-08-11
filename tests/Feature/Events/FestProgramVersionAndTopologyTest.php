<?php

namespace Tests\Feature\Events;

use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\State\StateFestEvent;
use App\Services\Events\FestEffectiveSettingsResolverService;
use App\Services\Events\FestStateProgramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class FestProgramVersionAndTopologyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('state:migrate');
    }

    public function test_fest_state_program_supports_level_settings_and_versioning(): void
    {
        $program = FestStateProgram::create([
            'title'               => 'Kerala State Kalotsavam 2026',
            'event_type'          => 'kalotsavam',
            'conduct_levels'      => ['sahodaya', 'state'],
            'academic_year'       => '2026-2027',
            'status'              => 'draft',
            'settings_version'    => 2,
            'level_event_settings' => [
                'sahodaya' => ['max_total_per_student' => 5, 'individual_fee_amount' => 150],
                'state'    => ['max_total_per_student' => 3, 'individual_fee_amount' => 500],
            ],
        ]);

        $this->assertEquals(2, $program->settings_version);
        $this->assertEquals(150, $program->level_event_settings['sahodaya']['individual_fee_amount']);
        $this->assertEquals(500, $program->level_event_settings['state']['individual_fee_amount']);
    }

    public function test_fest_state_program_item_supports_advancement_mode(): void
    {
        $program = FestStateProgram::create([
            'title'          => 'Kerala State Kalotsavam 2026',
            'event_type'     => 'kalotsavam',
            'conduct_levels' => ['sahodaya', 'state'],
            'status'         => 'draft',
        ]);

        $item = FestStateProgramItem::create([
            'state_program_id' => $program->id,
            'title'            => 'Light Music (Boys)',
            'item_code'        => 'LM01',
            'category'         => 'music',
            'advancement_mode' => 'region_direct_finale',
        ]);

        $this->assertEquals('region_direct_finale', $item->advancement_mode);
        $attributes = $item->toTenantAttributes();
        $this->assertEquals('region_direct_finale', $attributes['advancement_mode']);
    }

    public function test_effective_settings_resolver_service_merges_hierarchy(): void
    {
        $program = FestStateProgram::create([
            'title'               => 'Kerala State Kalotsavam 2026',
            'event_type'          => 'kalotsavam',
            'conduct_levels'      => ['sahodaya', 'state'],
            'status'              => 'draft',
            'level_event_settings' => [
                'sahodaya' => ['max_total_per_student' => 4],
            ],
        ]);

        $resolver = new FestEffectiveSettingsResolverService();
        $result = $resolver->resolve('sahodaya', $program);

        $this->assertEquals(4, $result['settings']['max_total_per_student']);
        $this->assertEquals(500, $result['settings']['team_fee_amount']); // Inherited from platform defaults
    }

    public function test_program_service_publishes_state_event_to_state_connection(): void
    {
        $program = FestStateProgram::create([
            'title'               => 'Kerala State Kalotsavam 2026',
            'event_type'          => 'kalotsavam',
            'conduct_levels'      => ['sahodaya', 'state'],
            'status'              => 'draft',
            'level_event_settings' => [
                'state' => ['max_total_per_student' => 3],
            ],
        ]);

        $service = new FestStateProgramService();
        $service->publish($program);

        $stateEvent = StateFestEvent::where('state_program_id', $program->id)->first();
        $this->assertNotNull($stateEvent);
        $this->assertEquals('state', $stateEvent->getConnectionName());
        $this->assertEquals('Kerala State Kalotsavam 2026', $stateEvent->name);
        $this->assertEquals('published', $stateEvent->status);

        $stateEvent->update(['results_published' => true, 'status' => 'completed']);
        $program->update([
            'title' => 'Kerala State Kalotsavam 2026 — Revised',
            'event_start' => '2026-11-01',
            'level_event_settings' => ['state' => ['max_total_per_student' => 2]],
        ]);
        $service->publish($program->fresh());

        $stateEvent->refresh();
        $this->assertSame(1, StateFestEvent::where('state_program_id', $program->id)->count());
        $this->assertSame('Kerala State Kalotsavam 2026 — Revised', $stateEvent->name);
        $this->assertSame('2026-11-01', $stateEvent->starts_on->toDateString());
        $this->assertSame(2, $stateEvent->settings['max_total_per_student']);
        $this->assertSame('completed', $stateEvent->status);
    }
}
