<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestPhaseScoreSnapshot;
use App\Models\FestRegistration;
use App\Models\FestResult;
use App\Models\FestScoreContribution;
use App\Models\Tenant;
use App\Services\Events\FestCumulativeChampionshipService;
use App\Services\Events\FestPhasedWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestCumulativeChampionshipServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_regional_phase_carries_opening_once_and_retains_each_event_contribution(): void
    {
        [$root, $phaseOne, $phaseTwo, $school, $phaseOneLeaf, $north, $south] = $this->fixture();
        $service = app(FestCumulativeChampionshipService::class);

        $this->createResult($phaseOneLeaf, $school, 10);
        $this->assertSame(1, $service->consolidateAndLock($root, $phaseOne));

        $this->createResult($north, $school, 4);
        $this->createResult($south, $school, 6);
        $this->assertSame(1, $service->consolidateAndLock($root, $phaseTwo));

        $snapshot = FestPhaseScoreSnapshot::where('phase_id', $phaseTwo->id)
            ->where('school_id', $school->id)
            ->where('championship_category_key', 'overall')
            ->firstOrFail();

        $this->assertSame(10.0, (float) $snapshot->opening_points);
        $this->assertSame(10.0, (float) $snapshot->current_points);
        $this->assertSame(20.0, (float) $snapshot->closing_points);
        $this->assertSame(2, FestScoreContribution::where('phase_id', $phaseTwo->id)->count());

        $northStanding = $service->publicStanding($north);
        $this->assertSame(4.0, $northStanding['rows'][0]['event_points']);
        $this->assertSame(10.0, $northStanding['rows'][0]['phase_points']);
        $this->assertSame(20.0, $northStanding['rows'][0]['closing_points']);

        $this->get("http://ledger.test/fest/{$north->id}/scoreboard")
            ->assertOk()
            ->assertSee('Championship standing after Off Stage')
            ->assertSee('Official snapshot v1')
            ->assertSee('Opening')
            ->assertSee('This Event')
            ->assertSee('Phase Total')
            ->assertSee('Closing');

        $this->get("http://ledger.test/fest/{$north->id}/results?tab=school")
            ->assertOk()
            ->assertSee('Championship standing after Off Stage')
            ->assertSee('Snapshot v1')
            ->assertSee('This Event')
            ->assertSee('This Phase');
    }

    public function test_unchanged_relock_is_idempotent_and_correction_creates_a_new_version(): void
    {
        [$root, $phaseOne, , $school, $leaf] = $this->fixture();
        $service = app(FestCumulativeChampionshipService::class);
        $result = $this->createResult($leaf, $school, 8);

        $this->assertSame(1, $service->consolidateAndLock($root, $phaseOne));
        $this->assertSame(1, $service->consolidateAndLock($root, $phaseOne));
        $this->assertSame(1, FestPhaseScoreSnapshot::where('phase_id', $phaseOne->id)->count());

        $result->update(['total_points' => 12]);
        $this->assertSame(2, $service->consolidateAndLock($root, $phaseOne, reason: 'Corrected certified result'));
        $this->assertSame(2, FestPhaseScoreSnapshot::where('phase_id', $phaseOne->id)->max('version'));
        $this->assertSame(12.0, (float) FestPhaseScoreSnapshot::where('phase_id', $phaseOne->id)
            ->where('version', 2)->value('closing_points'));
    }

    public function test_earlier_correction_recalculates_every_published_downstream_phase(): void
    {
        [$root, $phaseOne, $phaseTwo, $school, $phaseOneLeaf, $north, $south] = $this->fixture();
        $service = app(FestCumulativeChampionshipService::class);
        $phaseOneResult = $this->createResult($phaseOneLeaf, $school, 10);
        $this->createResult($north, $school, 4);
        $this->createResult($south, $school, 6);

        $service->lockPublishedThrough($root, $phaseTwo);
        $this->assertSame(20.0, (float) FestPhaseScoreSnapshot::where('phase_id', $phaseTwo->id)
            ->where('version', 1)->value('closing_points'));

        $phaseOneResult->update(['total_points' => 15]);
        $service->lockPublishedThrough($root, $phaseOne);

        $this->assertSame(2, FestPhaseScoreSnapshot::where('phase_id', $phaseTwo->id)->max('version'));
        $this->assertSame(25.0, (float) FestPhaseScoreSnapshot::where('phase_id', $phaseTwo->id)
            ->where('version', 2)->value('closing_points'));

        $service->invalidateFrom($root, $phaseOne);
        $this->assertNull($service->publicStanding($north));
        $this->assertSame(0, FestPhaseScoreSnapshot::whereIn('phase_id', [$phaseOne->id, $phaseTwo->id])
            ->whereNull('invalidated_at')->count());
    }

    public function test_event_category_keys_can_map_to_one_stable_championship_category(): void
    {
        [$root, $phaseOne, $phaseTwo, $school, $phaseOneLeaf, $north] = $this->fixture();
        $root->update(['aggregation_config' => [
            'championship_category_map' => [
                (string) $phaseTwo->id => ['category_i' => 'junior'],
            ],
        ]]);

        $this->mark($phaseOneLeaf, $school, 'junior');
        $this->mark($north, $school, 'category_i');
        app(FestCumulativeChampionshipService::class)->consolidateAndLock($root, $phaseOne);
        app(FestCumulativeChampionshipService::class)->consolidateAndLock($root, $phaseTwo);

        $snapshot = FestPhaseScoreSnapshot::where('phase_id', $phaseTwo->id)
            ->where('championship_category_key', 'junior')->firstOrFail();
        $this->assertSame(8.0, (float) $snapshot->opening_points);
        $this->assertSame(8.0, (float) $snapshot->current_points);
        $this->assertSame(16.0, (float) $snapshot->closing_points);
    }

    private function fixture(): array
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Ledger Sahodaya',
            'domain' => 'ledger.test',
            'is_active' => true,
        ]);
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'parent_id' => $sahodaya->id,
            'name' => 'Continuity School',
            'membership_status' => 'approved',
            'is_active' => true,
        ]);
        $root = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'District Kalotsav',
            'event_type' => 'kalotsav',
            'workflow_mode' => FestPhasedWorkflowService::MODE,
            'phase_mode_enabled' => true,
            'conduct_mode' => 'partitioned',
            'status' => 'ongoing',
        ]);
        $phaseOne = FestEventPhase::create([
            'event_id' => $root->id,
            'name' => 'Digi Fest',
            'code' => 'DIGI',
            'sort_order' => 1,
            'results_published' => true,
        ]);
        $phaseTwo = FestEventPhase::create([
            'event_id' => $root->id,
            'name' => 'Off Stage',
            'code' => 'OFF',
            'sort_order' => 2,
            'is_regional' => true,
            'results_published' => true,
        ]);

        $leaf = $this->leaf($root, $phaseOne, 'Digi Fest');
        $north = $this->leaf($root, $phaseTwo, 'Off Stage — North', 1);
        $south = $this->leaf($root, $phaseTwo, 'Off Stage — South', 2);

        return [$root, $phaseOne, $phaseTwo, $school, $leaf, $north, $south];
    }

    private function leaf(FestEvent $root, FestEventPhase $phase, string $title, ?int $regionId = null): FestEvent
    {
        return FestEvent::create([
            'tenant_id' => $root->tenant_id,
            'parent_event_id' => $root->id,
            'root_event_id' => $root->id,
            'source_phase_id' => $phase->id,
            'region_id' => $regionId,
            'title' => $title,
            'event_type' => 'kalotsav',
            'workflow_mode' => FestPhasedWorkflowService::MODE,
            'phase_mode_enabled' => true,
            'partition_role' => $regionId ? 'region' : 'phase',
            'status' => 'completed',
            'results_published' => true,
        ]);
    }

    private function createResult(FestEvent $event, Tenant $school, int $points): FestResult
    {
        return FestResult::create([
            'event_id' => $event->id,
            'school_id' => $school->id,
            'total_points' => $points,
            'rank' => 1,
            'published_at' => now(),
        ]);
    }

    private function mark(FestEvent $event, Tenant $school, string $category): void
    {
        $item = FestEventItem::create([
            'event_id' => $event->id,
            'title' => $category.' item',
            'class_group' => $category,
            'participant_type' => 'individual',
            'is_enabled' => true,
        ]);
        $registration = FestRegistration::create([
            'event_id' => $event->id,
            'item_id' => $item->id,
            'school_id' => $school->id,
            'status' => 'approved',
        ]);
        $participant = FestParticipant::create([
            'event_id' => $event->id,
            'registration_id' => $registration->id,
            'participant_type' => 'student',
        ]);
        FestMark::create([
            'event_id' => $event->id,
            'item_id' => $item->id,
            'participant_id' => $participant->id,
            'grade' => 'A',
            'position' => 1,
            'score' => 80,
        ]);
    }
}
