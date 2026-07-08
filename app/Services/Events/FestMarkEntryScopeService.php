<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestJudgeAssignment;
use App\Models\FestMark;
use App\Models\FestRegistration;
use App\Models\User;
use Illuminate\Support\Collection;

class FestMarkEntryScopeService
{
    public function __construct(
        private FestItemHeadService $headService,
    ) {}

    /** @return Collection<int, FestRegistration> */
    public function scopedRegistrations(FestEvent $event, User $user, ?array $judgeItemIds = null): Collection
    {
        $query = FestRegistration::query()
            ->where('event_id', $event->id)
            ->where('status', 'approved')
            ->with(['item.head', 'participants.student', 'participants.teacher', 'school']);

        if ($judgeItemIds !== null) {
            if ($judgeItemIds === []) {
                return collect();
            }
            $query->whereIn('item_id', $judgeItemIds);
        }

        return $query->get()
            ->filter(fn (FestRegistration $reg) => $this->userCanAccessRegistration($user, $event, $reg))
            ->values();
    }

    /** @return list<int> */
    public function judgeItemIds(User $user, FestEvent $event): array
    {
        return FestJudgeAssignment::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->pluck('item_id')
            ->unique()
            ->values()
            ->all();
    }

    public function userCanAccessRegistration(User $user, FestEvent $event, FestRegistration $reg): bool
    {
        if ($user->isSuperAdmin() || $user->hasAnyRole(['sahodaya_admin', 'mark_entry_admin'])) {
            return true;
        }

        $headId = $reg->item?->head_id;

        return $this->headService->userCanAccessHead($user->id, $event, $headId);
    }

    public function assertCanEnterMark(User $user, FestEvent $event, int $itemId): void
    {
        if ($user->isSuperAdmin() || $user->hasAnyRole(['sahodaya_admin', 'mark_entry_admin'])) {
            return;
        }

        $item = FestEventItem::query()
            ->where('event_id', $event->id)
            ->findOrFail($itemId);

        abort_unless(
            $this->headService->userCanAccessHead($user->id, $event, $item->head_id),
            403,
            'You are not assigned to this item head.',
        );
    }

    /** @return Collection<int|string, FestMark> keyed by participant_id */
    public function officialMarks(FestEvent $event, ?array $itemIds = null): Collection
    {
        return FestMark::query()
            ->where('event_id', $event->id)
            ->when($itemIds !== null, fn ($q) => $q->whereIn('item_id', $itemIds ?: [0]))
            ->get()
            ->keyBy('participant_id');
    }
}
