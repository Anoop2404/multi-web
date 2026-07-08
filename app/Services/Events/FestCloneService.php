<?php

namespace App\Services\Events;

use App\Models\FestCombinationRule;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestGradeConfig;
use App\Models\FestParticipationPolicy;
use App\Models\FestPointRule;
use App\Models\FestRankPoint;
use App\Models\FestVolunteer;
use Illuminate\Support\Facades\DB;

class FestCloneService
{
    /** Clone event structure (items, policies, rules) — not registrations/marks. */
    public function cloneEvent(FestEvent $source, string $title): FestEvent
    {
        return DB::transaction(function () use ($source, $title) {
            $clone = $source->replicate([
                'results_published', 'status', 'registration_open', 'registration_close',
            ]);
            $clone->title = $title;
            $clone->status = 'draft';
            $clone->results_published = false;
            $clone->cloned_from_event_id = $source->id;
            $clone->save();

            foreach ($source->items as $item) {
                $newItem = $item->replicate();
                $newItem->event_id = $clone->id;
                $newItem->save();
            }

            foreach (FestParticipationPolicy::where('event_id', $source->id)->get() as $policy) {
                $p = $policy->replicate();
                $p->event_id = $clone->id;
                $p->save();
            }

            foreach (FestCombinationRule::where('event_id', $source->id)->get() as $rule) {
                $r = $rule->replicate();
                $r->event_id = $clone->id;
                $r->save();
            }

            foreach (FestGradeConfig::where('event_id', $source->id)->get() as $cfg) {
                $c = $cfg->replicate();
                $c->event_id = $clone->id;
                $c->item_id = null;
                $c->save();
            }

            foreach (FestPointRule::where('event_id', $source->id)->get() as $rule) {
                $r = $rule->replicate();
                $r->event_id = $clone->id;
                $r->save();
            }

            foreach (FestRankPoint::where('event_id', $source->id)->get() as $rankPoint) {
                $rp = $rankPoint->replicate();
                $rp->event_id = $clone->id;
                $rp->save();
            }

            return $clone->fresh('items');
        });
    }
}
