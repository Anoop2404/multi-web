<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestRegistration;

class FestRegistrationApprovalService
{
    /**
     * Auto-approve every submitted registration for a school in one event.
     * Used when the school's event fee is fully paid — fest no longer needs a
     * separate Sahodaya registration-approval step.
     *
     * @param  ?int  $headId  When given, only registrations for items under this Event Head are
     *                        auto-approved — used when a school pays one head's fee under
     *                        sports_composite per-head billing, so paying Athletics doesn't also
     *                        auto-approve a still-unpaid Chess registration. Omit for the old
     *                        whole-event behavior (every fee model without heads).
     * @return int Number of registrations approved.
     */
    public function approveSchoolEvent(FestEvent $event, string $schoolId, ?int $headId = null): int
    {
        $count = 0;

        FestRegistration::query()
            ->where('event_id', $event->id)
            ->where('school_id', $schoolId)
            ->whereIn('status', ['draft', 'submitted', 'pending_approval'])
            ->when($headId !== null, fn ($q) => $q->whereHas('item', fn ($qq) => $qq->where('head_id', $headId)))
            ->orderBy('id')
            ->get()
            ->each(function (FestRegistration $registration) use (&$count) {
                $this->approve($registration);
                $count++;
            });

        return $count;
    }

    public function approve(FestRegistration $registration): void
    {
        $registration->update(['status' => 'approved']);
        $registration->load(['participants', 'item', 'event']);

        $event = $registration->event;
        if (! $event) {
            return;
        }

        $levelService = app(FestLevelRegistrationService::class);
        $numbering = app(FestNumberingService::class);
        $settings = $numbering->settings($event);
        $autoAssign = (bool) ($settings['auto_assign_on_approve'] ?? true);

        foreach ($registration->participants as $participant) {
            if ($participant->participant_role === 'standby') {
                continue;
            }

            $updates = ['event_id' => $event->id];

            if ($autoAssign) {
                if (! $numbering->persistedChestNumber($participant) && $registration->item_id && $registration->item) {
                    ['chest' => $chest, 'persist' => $persist, 'chest_head_id' => $chestHeadId] = $numbering->resolveChestAssignment(
                        $event,
                        $registration->item,
                        $participant
                    );
                    if ($persist) {
                        $updates['chest_no'] = $chest;
                        $updates['chest_head_id'] = $chestHeadId;
                    }
                }

                if (! $participant->item_registration_number && $registration->item) {
                    $updates['item_registration_number'] = $numbering->nextItemRegistrationNumber($event, $registration->item);
                }
            }

            $participant->update($updates);

            if ($participant->student_id) {
                $levelService->syncParticipant($participant->fresh());
            } elseif ($participant->teacher_id) {
                $levelService->syncTeacherParticipant($participant->fresh());
            }
        }
    }
}
