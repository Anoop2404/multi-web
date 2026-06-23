<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;

class FestCascadeService
{
    public function spawnChildEvent(FestEvent $parent, string $title): FestEvent
    {
        $child = FestEvent::create([
            'tenant_id'          => $parent->tenant_id,
            'academic_year_id'   => $parent->academic_year_id,
            'title'              => $title,
            'event_type'         => $parent->event_type,
            'conductor_level'    => $parent->conductor_level,
            'is_cascaded'        => true,
            'parent_event_id'    => $parent->id,
            'registration_open'  => $parent->registration_open,
            'registration_close' => $parent->registration_close,
            'event_start'        => $parent->event_start,
            'event_end'          => $parent->event_end,
            'venue'              => $parent->venue,
            'fee_type'           => $parent->fee_type,
            'fee_amount'         => $parent->fee_amount,
            'status'             => 'draft',
            'description'        => "Cascaded from {$parent->title}",
        ]);

        foreach ($parent->items as $item) {
            FestEventItem::create([
                'event_id'         => $child->id,
                'title'            => $item->title,
                'category'         => $item->category,
                'participant_type' => $item->participant_type,
                'gender'           => $item->gender,
                'class_group'      => $item->class_group,
                'max_per_school'   => $item->max_per_school,
                'min_group_size'   => $item->min_group_size,
                'max_group_size'   => $item->max_group_size,
                'qualify_count'    => $item->qualify_count,
                'display_order'    => $item->display_order,
            ]);
        }

        return $child;
    }
}
