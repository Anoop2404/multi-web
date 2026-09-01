<?php

namespace Tests\Feature\Events;

use App\Models\AuditLog;
use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\FestRegionChangeRequest;
use App\Models\FestSchoolPhaseRegionSelection;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\FestEventPhaseService;
use App\Services\Events\FestPhasedWorkflowService;
use App\Services\Events\FestSchoolPhaseRegionService;
use App\Support\FestPageActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestRegionChangeRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_can_submit_a_request_and_admin_can_approve_it(): void
    {
        [$sahodaya, $school, $root, $phase, $regions] = $this->fixture();

        $selector = app(FestSchoolPhaseRegionService::class);
        $selector->select($root, $phase, $school->id, $regions[0]->id);

        $selection = FestSchoolPhaseRegionSelection::where('event_id', $root->id)
            ->where('phase_id', $phase->id)
            ->where('school_id', $school->id)
            ->firstOrFail();
        $this->assertTrue($selection->isLocked());
        $this->assertSame($regions[0]->id, $selection->region_id);

        // School submits a region-change request (mirrors FestRegionChangeRequestController::store()).
        $changeRequest = FestRegionChangeRequest::create([
            'event_id' => $root->id,
            'phase_id' => $phase->id,
            'school_id' => $school->id,
            'current_region_id' => $selection->region_id,
            'requested_region_id' => $regions[1]->id,
            'reason' => 'Closer to our campus.',
            'status' => 'pending',
        ]);

        $this->assertSame('pending', $changeRequest->status);

        // Admin approves (mirrors FestPhaseRegionMatrixController::approve()).
        $approvedSelection = $selector->select(
            $root,
            $phase,
            $school->id,
            (int) $changeRequest->requested_region_id,
            null,
            true,
            $changeRequest->reason,
        );

        $changeRequest->update([
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);

        app(PlatformAuditLogger::class)->festEvent($root, FestPageActivity::REGISTRATIONS, 'fest.phase_region.request_approved', 'Approved school region change request', [
            'phase_id' => $phase->id,
            'school_id' => $school->id,
            'region_id' => $approvedSelection->region_id,
            'request_id' => $changeRequest->id,
        ]);

        $this->assertSame($regions[1]->id, $approvedSelection->region_id);
        $this->assertSame('approved', $changeRequest->fresh()->status);
        $this->assertDatabaseHas((new AuditLog)->getTable(), [
            'action' => 'fest.phase_region.request_approved',
        ], 'central');
    }

    public function test_rejecting_a_request_leaves_the_selection_unchanged(): void
    {
        [$sahodaya, $school, $root, $phase, $regions] = $this->fixture();

        $selector = app(FestSchoolPhaseRegionService::class);
        $selector->select($root, $phase, $school->id, $regions[0]->id);

        $changeRequest = FestRegionChangeRequest::create([
            'event_id' => $root->id,
            'phase_id' => $phase->id,
            'school_id' => $school->id,
            'current_region_id' => $regions[0]->id,
            'requested_region_id' => $regions[1]->id,
            'reason' => 'Testing rejection.',
            'status' => 'pending',
        ]);

        // Admin rejects (mirrors FestPhaseRegionMatrixController::reject()) — no service call.
        $changeRequest->update([
            'status' => 'rejected',
            'resolution_note' => 'Capacity unavailable.',
            'reviewed_at' => now(),
        ]);

        $selection = FestSchoolPhaseRegionSelection::where('event_id', $root->id)
            ->where('phase_id', $phase->id)
            ->where('school_id', $school->id)
            ->firstOrFail();

        $this->assertSame($regions[0]->id, $selection->region_id);
        $this->assertSame('rejected', $changeRequest->fresh()->status);
    }

    /** @return array{0: Tenant, 1: Tenant, 2: FestEvent, 3: FestEventPhase, 4: Collection<int, Region>} */
    private function fixture(): array
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Region Change Test Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'RCT',
            'student_data_mode' => 'counts_only',
        ]);
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'parent_id' => $sahodaya->id,
            'name' => 'Region Change Test School',
            'domain' => Str::uuid().'.test',
            'membership_status' => 'approved',
            'is_active' => true,
        ]);
        $root = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Region Change Test Kalotsav',
            'event_type' => 'kalotsavam',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
            'fee_type' => 'none',
            'fee_settings' => ['fee_model' => 'item_catalog'],
            'workflow_mode' => FestPhasedWorkflowService::MODE,
            'phase_mode_enabled' => true,
            'conduct_mode' => 'partitioned',
        ]);
        $regions = collect(['Tirur', 'Manjeri'])->map(fn (string $name, int $index) => Region::create([
            'tenant_id' => $sahodaya->id,
            'name' => $name,
            'code' => strtoupper(substr($name, 0, 3)).$index,
            'is_active' => true,
        ]));

        $phase = app(FestEventPhaseService::class)->createPhase($root, [
            'name' => 'Off Stage',
            'code' => 'OFF_STAGE',
            'sort_order' => 1,
            'is_regional' => true,
        ]);
        app(FestPhasedWorkflowService::class)->syncAllowedRegions($phase, $regions->pluck('id')->all());

        return [$sahodaya, $school, $root, $phase->fresh(), $regions];
    }
}
