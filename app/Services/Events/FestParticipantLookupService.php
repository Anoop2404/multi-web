<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;

class FestParticipantLookupService
{
    public function resolveForEvent(FestEvent $event, array $row): ?FestParticipant
    {
        if (! empty($row['participant_id'])) {
            $participant = FestParticipant::find((int) $row['participant_id']);

            return ($participant && $participant->registration?->event_id === $event->id) ? $participant : null;
        }

        $item = null;
        if (! empty($row['item_id'])) {
            $item = FestEventItem::where('event_id', $event->id)->find($row['item_id']);
        } elseif (! empty($row['item_title'])) {
            $item = FestEventItem::where('event_id', $event->id)->where('title', $row['item_title'])->first();
        }

        $regNo = trim((string) ($row['reg_no'] ?? ''));
        if ($regNo === '') {
            return null;
        }

        $query = FestParticipant::query()
            ->whereHas('registration', fn ($q) => $q
                ->where('event_id', $event->id)
                ->when($item, fn ($q2) => $q2->where('item_id', $item->id)));

        if (! empty($row['chest_no'])) {
            $query->where('chest_no', (int) $row['chest_no']);
        }

        if (! empty($row['school_id'])) {
            $query->whereHas('registration', fn ($q) => $q->where('school_id', $row['school_id']));
        }

        $participant = $query->where(function ($q) use ($regNo) {
            $q->whereHas('student', fn ($s) => $s->where('reg_no', $regNo))
                ->orWhereHas('teacher', fn ($t) => $t->where('reg_no', $regNo));
        })->first();

        return $participant;
    }

    /** @return list<array<string, mixed>> */
    public function approvedRowsForTemplate(FestEvent $event): array
    {
        return FestParticipant::query()
            ->whereHas('registration', fn ($q) => $q
                ->where('event_id', $event->id)
                ->where('status', 'approved'))
            ->with(['student:id,reg_no,name', 'teacher:id,reg_no,name', 'registration.item:id,title', 'registration.school:id,name,school_prefix'])
            ->orderBy('chest_no')
            ->get()
            ->map(fn (FestParticipant $p) => [
                'participant_id' => $p->id,
                'reg_no'         => $p->student?->reg_no ?? $p->teacher?->reg_no ?? '',
                'name'           => $p->student?->name ?? $p->teacher?->name ?? '',
                'chest_no'       => $p->chest_no ?? '',
                'item_id'        => $p->registration?->item_id ?? '',
                'item_title'     => $p->registration?->item?->title ?? '',
                'school_prefix'  => $p->registration?->school?->school_prefix ?? '',
            ])
            ->all();
    }
}
