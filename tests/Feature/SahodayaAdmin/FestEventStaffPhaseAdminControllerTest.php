<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\FestEventStaff;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end HTTP coverage for the phase_admin duty branch added to
 * FestEventStaffController@store — the mechanical piece RegionScopedAccessParityTest
 * doesn't reach, since that suite seeds FestEventStaff rows directly rather than going
 * through the real assignment form/validation.
 */
class FestEventStaffPhaseAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, admin: User, hub: FestEvent, regionalPhase: FestEventPhase, nonRegionalPhase: FestEventPhase, region: Region} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Phase Admin Test Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'PA', 'student_data_mode' => 'counts_only']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id]);
        $admin->assignRole('sahodaya_admin');

        $region = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Test Region', 'code' => 'PATR', 'is_active' => true]);

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Phase Admin Kalotsav', 'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned', 'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        $regionalPhase = FestEventPhase::create(['event_id' => $hub->id, 'name' => 'Level 1', 'code' => 'L1', 'sort_order' => 1, 'is_regional' => true]);
        $nonRegionalPhase = FestEventPhase::create(['event_id' => $hub->id, 'name' => 'State Round', 'code' => 'SR', 'sort_order' => 2, 'is_regional' => false]);

        return compact('sahodaya', 'admin', 'hub', 'regionalPhase', 'nonRegionalPhase', 'region');
    }

    private function assign(User $admin, Tenant $sahodaya, FestEvent $event, array $overrides = [])
    {
        $target = User::factory()->create(['tenant_id' => $sahodaya->id]);

        return [
            'response' => $this->actingAs($admin)->post(
                route('sahodaya.events.event-staff.store', ['tenantId' => $sahodaya->id, 'event' => $event->id]),
                array_merge(['user_id' => $target->id, 'duty' => 'phase_admin'], $overrides),
            ),
            'target' => $target,
        ];
    }

    public function test_admin_can_assign_phase_admin_duty_with_a_regional_phase(): void
    {
        $f = $this->fixture();

        $result = $this->assign($f['admin'], $f['sahodaya'], $f['hub'], ['source_phase_id' => $f['regionalPhase']->id]);
        $result['response']->assertSessionHas('success');
        $result['response']->assertSessionDoesntHaveErrors();

        $row = FestEventStaff::where('event_id', $f['hub']->id)->where('user_id', $result['target']->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('phase_admin', $row->duty);
        $this->assertSame($f['regionalPhase']->id, $row->source_phase_id);
        $this->assertNull($row->region_id);

        $result['target']->refresh();
        $this->assertTrue($result['target']->hasRole('phase_admin'));
        $this->assertTrue($result['target']->can('fest.marks'));
        $this->assertFalse($result['target']->hasRole('fest_ops'));
    }

    public function test_phase_admin_duty_requires_a_phase(): void
    {
        $f = $this->fixture();

        $result = $this->assign($f['admin'], $f['sahodaya'], $f['hub']);
        $result['response']->assertSessionHasErrors('source_phase_id');
        $this->assertNull(FestEventStaff::where('user_id', $result['target']->id)->first());
    }

    public function test_phase_admin_duty_rejects_a_non_regional_phase(): void
    {
        $f = $this->fixture();

        $result = $this->assign($f['admin'], $f['sahodaya'], $f['hub'], ['source_phase_id' => $f['nonRegionalPhase']->id]);
        $result['response']->assertSessionHasErrors('source_phase_id');
        $this->assertNull(FestEventStaff::where('user_id', $result['target']->id)->first());
    }

    public function test_phase_admin_duty_ignores_a_submitted_region_id(): void
    {
        $f = $this->fixture();

        $result = $this->assign($f['admin'], $f['sahodaya'], $f['hub'], [
            'source_phase_id' => $f['regionalPhase']->id,
            'region_id' => $f['region']->id,
        ]);
        $result['response']->assertSessionDoesntHaveErrors();

        $row = FestEventStaff::where('event_id', $f['hub']->id)->where('user_id', $result['target']->id)->first();
        $this->assertNotNull($row);
        $this->assertNull($row->region_id);
    }
}
