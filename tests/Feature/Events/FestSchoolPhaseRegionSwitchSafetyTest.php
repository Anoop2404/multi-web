<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestRegistrationBatch;
use App\Models\FestSchoolEventFee;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Services\Events\FestEventPhaseService;
use App\Services\Events\FestPhaseTopologyService;
use App\Services\Events\FestPhasedWorkflowService;
use App\Services\Events\FestRegistrationBatchFeeService;
use App\Services\Events\FestSchoolPhaseRegionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Covers the safety gaps found live on a school whose region was switched after they'd
 * already registered and paid: a silent 403-free lockout, a stale invoice, a participant
 * denormalization drift, and an unguarded item-matching fallback that could silently
 * reassign a paid registration to the wrong item.
 */
class FestSchoolPhaseRegionSwitchSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_switching_a_school_with_a_paid_invoice_requires_acknowledgement(): void
    {
        [$root, $school, $phase, $regions, $leafItemA] = $this->fixtureWithRegisteredSchool();

        // recalculateAll() inside the fixture already created this school's fee row;
        // mark it paid rather than creating a second one for the same (event, school,
        // batch) key.
        FestSchoolEventFee::where('event_id', $root->id)->where('school_id', $school->id)
            ->update(['amount_paid' => 100]);

        $selector = app(FestSchoolPhaseRegionService::class);

        $this->expectException(ValidationException::class);
        $selector->select($root, $phase, $school->id, $regions[1]->id, null, true, 'Testing');
    }

    public function test_acknowledging_the_paid_invoice_lets_the_switch_proceed(): void
    {
        [$root, $school, $phase, $regions] = $this->fixtureWithRegisteredSchool();

        FestSchoolEventFee::where('event_id', $root->id)->where('school_id', $school->id)
            ->update(['amount_paid' => 100]);

        $selector = app(FestSchoolPhaseRegionService::class);
        $selection = $selector->select($root, $phase, $school->id, $regions[1]->id, null, true, 'Testing', acknowledgePaidInvoice: true);

        $this->assertSame($regions[1]->id, $selection->region_id);
    }

    public function test_switching_moves_participants_to_the_new_leaf_too(): void
    {
        [$root, $school, $phase, $regions, $leafItemA, $registration] = $this->fixtureWithRegisteredSchool();

        $selector = app(FestSchoolPhaseRegionService::class);
        $selector->select($root, $phase, $school->id, $regions[1]->id, null, true, 'Testing');

        $newLeaf = FestEvent::where('parent_event_id', $root->id)
            ->where('source_phase_id', $phase->id)
            ->where('region_id', $regions[1]->id)
            ->firstOrFail();

        $registration->refresh();
        $this->assertSame($newLeaf->id, $registration->event_id);

        $participant = FestParticipant::where('registration_id', $registration->id)->firstOrFail();
        $this->assertSame($newLeaf->id, $participant->event_id);
    }

    public function test_switching_refuses_when_the_target_region_already_has_an_active_registration_for_the_same_item(): void
    {
        [$root, $school, $phase, $regions, $leafItemA] = $this->fixtureWithRegisteredSchool();

        $newLeaf = FestEvent::where('parent_event_id', $root->id)
            ->where('source_phase_id', $phase->id)
            ->where('region_id', $regions[1]->id)
            ->firstOrFail();
        $leafItemB = FestEventItem::where('event_id', $newLeaf->id)
            ->where('inherited_from_item_id', $leafItemA->inherited_from_item_id)
            ->firstOrFail();

        // A registration already exists on the target region for the same item -- e.g.
        // the school registered there directly before the switch was requested.
        FestRegistration::create([
            'event_id' => $newLeaf->id,
            'item_id' => $leafItemB->id,
            'school_id' => $school->id,
            'status' => 'approved',
            'submitted_at' => now(),
        ]);

        $selector = app(FestSchoolPhaseRegionService::class);

        try {
            $selector->select($root, $phase, $school->id, $regions[1]->id, null, true, 'Testing');
            $this->fail('Expected the collision to be refused.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString('already has an active registration', $e->getMessage());
        }
    }

    public function test_switching_refuses_an_item_not_linked_to_the_hub_catalog(): void
    {
        [$root, $school, $phase, $regions, , $registration] = $this->fixtureWithRegisteredSchool();

        // Simulate an item authored directly on the leaf (never copied from a hub item) --
        // inherited_from_item_id is null, so there's no reliable way to match it on the
        // new leaf.
        $registration->item->update(['inherited_from_item_id' => null]);

        $selector = app(FestSchoolPhaseRegionService::class);

        try {
            $selector->select($root, $phase, $school->id, $regions[1]->id, null, true, 'Testing');
            $this->fail('Expected the unlinked item to be refused.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString("isn't linked to a shared catalog item", $e->getMessage());
        }
    }

    /**
     * @return array{0: FestEvent, 1: Tenant, 2: FestEventPhase, 3: \Illuminate\Support\Collection<int, Region>, 4: FestEventItem, 5: FestRegistration}
     */
    private function fixtureWithRegisteredSchool(): array
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Region Switch Safety Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'RSS',
            'student_data_mode' => 'counts_only',
        ]);
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'parent_id' => $sahodaya->id,
            'name' => 'Region Switch Safety School',
            'domain' => Str::uuid().'.test',
            'membership_status' => 'approved',
            'is_active' => true,
        ]);
        $root = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Region Switch Safety Kalotsav',
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
            'invoice_prefix' => 'RSS-L1',
            'status' => 'registration_open',
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
            'registration_batch_id' => $batch->id,
            'is_regional' => true,
        ]);
        app(FestPhasedWorkflowService::class)->syncAllowedRegions($phase, $regions->pluck('id')->all());

        $hubItem = FestEventItem::create([
            'event_id' => $root->id,
            'phase_id' => $phase->id,
            'title' => 'Off Stage Item',
            'item_code' => 'OFF-01',
            'fee_amount' => 50,
            'is_enabled' => true,
        ]);

        app(FestPhaseTopologyService::class)->sync($root->fresh());

        $oldLeaf = FestEvent::where('parent_event_id', $root->id)
            ->where('source_phase_id', $phase->id)
            ->where('region_id', $regions[0]->id)
            ->firstOrFail();
        $leafItemA = FestEventItem::where('event_id', $oldLeaf->id)
            ->where('inherited_from_item_id', $hubItem->id)
            ->firstOrFail();

        app(FestSchoolPhaseRegionService::class)->select($root, $phase, $school->id, $regions[0]->id);

        $registration = FestRegistration::create([
            'event_id' => $oldLeaf->id,
            'item_id' => $leafItemA->id,
            'school_id' => $school->id,
            'status' => 'approved',
            'submitted_at' => now(),
        ]);
        FestParticipant::create([
            'registration_id' => $registration->id,
            'event_id' => $oldLeaf->id,
            'participant_type' => 'student',
        ]);

        app(FestRegistrationBatchFeeService::class)->recalculateAll($root, $school->id);

        return [$root, $school, $phase->fresh(), $regions, $leafItemA->fresh(), $registration->fresh(['item'])];
    }
}
