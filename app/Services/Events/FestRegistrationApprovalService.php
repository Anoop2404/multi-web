<?php

namespace App\Services\Events;

use App\Models\FestRegistration;

class FestRegistrationApprovalService
{
    public function approve(FestRegistration $registration): void
    {
        $registration->update(['status' => 'approved']);
        $registration->load(['participants', 'item', 'event']);

        $event = $registration->event;
        if (! $event) {
            return;
        }

        $ctx = EventContext::for($event);
        $levelService = app(FestLevelRegistrationService::class);

        foreach ($registration->participants as $participant) {
            if ($participant->participant_role === 'standby') {
                continue;
            }

            if (! $participant->chest_no && $registration->item_id) {
                $participant->update([
                    'chest_no' => $ctx->nextChestNumber($registration->item),
                ]);
            }

            if ($participant->student_id) {
                $levelService->syncParticipant($participant->fresh());
            }
        }
    }
}
