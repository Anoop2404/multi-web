<?php

namespace App\Services\Events;

use App\Models\Certificate;
use App\Models\FestEvent;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRecordBreak;
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
            if (! $participant || $participant->disqualified_at) {
                continue;
            }

            $cert = Certificate::firstOrCreate(
                [
                    'entity_type' => FestParticipant::class,
                    'entity_id'   => $participant->id,
                    'cert_type'   => 'winner',
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

    /** @return list<Certificate> */
    public function generateParticipationForEvent(FestEvent $event): array
    {
        $created = [];

        $participants = FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('status', 'approved'))
            ->whereNull('disqualified_at')
            ->get();

        foreach ($participants as $participant) {
            $cert = Certificate::firstOrCreate(
                [
                    'entity_type' => FestParticipant::class,
                    'entity_id'   => $participant->id,
                    'cert_type'   => 'participation',
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

    public function issueRecordBreakCertificate(FestRecordBreak $break): Certificate
    {
        return Certificate::firstOrCreate(
            [
                'entity_type' => FestRecordBreak::class,
                'entity_id'   => $break->id,
                'cert_type'   => 'record_break',
            ],
            [
                'verification_uuid' => (string) Str::uuid(),
                'generated_at'      => now(),
            ]
        );
    }

    public function payloadFor(Certificate $certificate): array
    {
        if ($certificate->entity_type === FestRecordBreak::class) {
            return $this->recordBreakPayload($certificate);
        }

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
            'recordBreak' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function recordBreakPayload(Certificate $certificate): array
    {
        $break = FestRecordBreak::with([
            'event',
            'item',
            'participant.student',
            'participant.registration.school',
        ])->find($certificate->entity_id);

        return [
            'certificate' => $certificate,
            'participant' => $break?->participant,
            'student'     => $break?->participant?->student,
            'event'       => $break?->event,
            'item'        => $break?->item,
            'mark'        => null,
            'recordBreak' => $break,
        ];
    }
}
