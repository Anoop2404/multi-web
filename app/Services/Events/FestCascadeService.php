<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;

class FestCascadeService
{
    public function spawnChildEvent(FestEvent $parent, string $title, array $overrides = []): FestEvent
    {
        $levelRound = $overrides['level_round'] ?? $parent->level_round ?? 'sahodaya';

        if (! isset($overrides['fee_type'])) {
            $fee = $levelRound === 'school'
                ? app(FestEventFeeResolver::class)->resolveSchoolRoundFromParent($parent)
                : app(FestEventFeeResolver::class)->resolveForEvent($parent);

            $overrides['fee_type'] = $fee['fee_type'];
            $overrides['fee_amount'] = $fee['fee_amount'];
        }

        $child = FestEvent::create(array_merge([
            'tenant_id'          => $parent->tenant_id,
            'academic_year_id'   => $parent->academic_year_id,
            'title'              => $title,
            'event_type'         => $parent->event_type,
            'conductor_level'    => $parent->conductor_level,
            'conduct_levels'     => $parent->conduct_levels ?? ['sahodaya'],
            'level_round'        => $parent->level_round ?? 'sahodaya',
            'state_program_id'   => $parent->state_program_id,
            'is_cascaded'        => true,
            'parent_event_id'    => $parent->id,
            'registration_open'  => $parent->registration_open,
            'registration_close' => $parent->registration_close,
            'event_start'        => $parent->event_start,
            'event_end'          => $parent->event_end,
            'venue'              => $parent->venue,
            'fee_type'           => $overrides['fee_type'] ?? $parent->fee_type,
            'fee_amount'         => $overrides['fee_amount'] ?? $parent->fee_amount,
            'status'             => 'draft',
            'description'        => "Cascaded from {$parent->title}",
        ], $overrides));

        $parent->loadMissing('items');
        app(FestItemSyncService::class)->copyAllItemsToChild($parent, $child);

        return $child;
    }
}
