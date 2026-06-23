<?php

namespace App\Services\Events;

use App\Models\Certificate;
use App\Models\FestEvent;
use App\Models\FestMark;
use App\Models\FestParticipant;
use Illuminate\Support\Str;

class FestCertificateService
{
    /** @return list<Certificate> */
    public function generateForEvent(FestEvent $event): array
    {
        $created = [];

        $marks = FestMark::where('event_id', $event->id)
            ->whereNotNull('position')
            ->where('position', '<=', 3)
            ->with(['participant.student', 'participant.registration'])
            ->get();

        foreach ($marks as $mark) {
            $participant = $mark->participant;
            if (! $participant) {
                continue;
            }

            $cert = Certificate::firstOrCreate(
                [
                    'entity_type' => FestParticipant::class,
                    'entity_id'   => $participant->id,
                ],
                [
                    'verification_uuid' => (string) Str::uuid(),
                    'generated_at'      => now(),
                ]
            );

            $created[] = $cert;
        }

        return $created;
    }

    public function payloadFor(Certificate $certificate): array
    {
        $participant = FestParticipant::with(['student', 'registration.item', 'registration.event'])
            ->find($certificate->entity_id);

        return [
            'certificate' => $certificate,
            'participant' => $participant,
            'student'     => $participant?->student,
            'event'       => $participant?->registration?->event,
            'item'        => $participant?->registration?->item,
            'mark'        => $participant
                ? FestMark::where('participant_id', $participant->id)->first()
                : null,
        ];
    }
}
