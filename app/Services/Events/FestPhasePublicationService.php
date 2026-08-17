<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\FestResult;
use Illuminate\Support\Facades\DB;

class FestPhasePublicationService
{
    public function publishSchedule(FestEvent $leaf): void
    {
        [$root, $source, $childPhase] = $this->context($leaf);

        DB::transaction(function () use ($leaf, $root, $source, $childPhase) {
            $leaf->update(['schedule_published' => true]);
            $childPhase->update(['schedule_published' => true]);
            $source->update(['schedule_published' => $this->allLeaves($root, $source)->every->schedule_published]);
            $root->update(['schedule_published' => $root->phases()->where('schedule_published', false)->doesntExist()]);
        });
    }

    public function unpublishSchedule(FestEvent $leaf): void
    {
        [$root, $source, $childPhase] = $this->context($leaf);
        DB::transaction(function () use ($leaf, $root, $source, $childPhase) {
            $leaf->update(['schedule_published' => false]);
            $childPhase->update(['schedule_published' => false]);
            $source->update(['schedule_published' => false]);
            $root->update(['schedule_published' => false]);
        });
    }

    public function publishResults(FestEvent $leaf, ?int $actorId = null): void
    {
        [$root, $source, $childPhase] = $this->context($leaf);
        EventLifecycleGate::allowPublishResults($leaf);

        DB::transaction(function () use ($leaf, $root, $source, $childPhase, $actorId) {
            EventContext::for($leaf)->recalculateSchoolPoints();
            $leaf->update(['results_published' => true, 'status' => 'completed']);
            $childPhase->update(['results_published' => true, 'status' => 'completed']);
            FestResult::where('event_id', $leaf->id)->whereNull('item_id')->update([
                'published_at' => now(),
                'published_by' => $actorId,
            ]);

            $phaseComplete = $this->allLeaves($root, $source)->every->results_published;
            $source->update([
                'results_published' => $phaseComplete,
                'status' => $phaseComplete ? 'completed' : $source->status,
            ]);

            $allComplete = $root->phases()->where('results_published', false)->doesntExist();
            $root->update([
                'results_published' => $allComplete,
                'status' => $allComplete ? 'completed' : $root->status,
            ]);
        });
    }

    public function unpublishResults(FestEvent $leaf): void
    {
        [$root, $source, $childPhase] = $this->context($leaf);
        DB::transaction(function () use ($leaf, $root, $source, $childPhase) {
            $leaf->update(['results_published' => false]);
            $childPhase->update(['results_published' => false]);
            $source->update(['results_published' => false]);
            $root->update(['results_published' => false]);
            FestResult::where('event_id', $leaf->id)->whereNull('item_id')->update([
                'published_at' => null,
                'published_by' => null,
            ]);
        });
    }

    /** @return array{FestEvent, FestEventPhase, FestEventPhase} */
    private function context(FestEvent $leaf): array
    {
        abort_unless($leaf->usesPhasedRegionalBilling(), 422, 'Event does not use phased publication.');
        abort_if(! $leaf->parent_event_id || ! $leaf->source_phase_id, 422, 'Publish an operational phase/region event, not the root event.');

        $root = $leaf->rootEvent();
        $source = FestEventPhase::where('event_id', $root->id)->findOrFail($leaf->source_phase_id);
        $childPhase = FestEventPhase::where('event_id', $leaf->id)
            ->where('source_phase_id', $source->id)
            ->firstOrFail();

        return [$root, $source, $childPhase];
    }

    private function allLeaves(FestEvent $root, FestEventPhase $source)
    {
        return FestEvent::where('parent_event_id', $root->id)
            ->where('source_phase_id', $source->id)
            ->when($source->isRegional(), function ($query) use ($source) {
                $query->whereIn('region_id', $source->allowedRegions()
                    ->where('enabled', true)
                    ->select('region_id'));
            })
            ->get();
    }
}
