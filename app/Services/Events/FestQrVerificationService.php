<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventStaff;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestVolunteer;
use App\Models\FestAttendance;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

class FestQrVerificationService
{
    /** @return array{valid: bool, kind: ?string, duplicate: bool, payload: array<string, mixed>, attendance_marked?: bool} */
    public function verify(FestEvent $event, string $rawPayload, bool $markAttendance = false, ?int $markedBy = null): array
    {
        $parsed = $this->parsePayload($rawPayload);
        if (! $parsed) {
            return ['valid' => false, 'kind' => null, 'duplicate' => false, 'payload' => ['error' => 'Invalid QR code format.']];
        }

        if ((int) $parsed['event_id'] !== (int) $event->id) {
            return ['valid' => false, 'kind' => $parsed['kind'], 'duplicate' => false, 'payload' => ['error' => 'QR code is for a different event.']];
        }

        $details = match ($parsed['kind']) {
            'participant' => $this->participantDetails($event, (int) $parsed['entity_id']),
            'registration' => $this->registrationDetails($event, (int) $parsed['entity_id']),
            'volunteer' => $this->volunteerDetails($event, (int) $parsed['entity_id']),
            'staff' => $this->staffDetails($event, (int) $parsed['entity_id']),
            default => null,
        };

        if (! $details) {
            return ['valid' => false, 'kind' => $parsed['kind'], 'duplicate' => false, 'payload' => ['error' => 'No matching record found.']];
        }

        $scanKey = "fest_qr_scan:{$event->id}:{$parsed['kind']}:{$parsed['entity_id']}";
        $duplicate = Cache::has($scanKey);
        Cache::put($scanKey, now()->toIso8601String(), now()->addHours(12));

        $attendanceMarked = false;
        if ($markAttendance && $parsed['kind'] === 'participant' && $markedBy) {
            $attendanceMarked = $this->markParticipantPresent($event, (int) $parsed['entity_id'], $markedBy);
        }

        return [
            'valid'     => true,
            'kind'      => $parsed['kind'],
            'duplicate' => $duplicate,
            'payload'   => $details,
            'attendance_marked' => $attendanceMarked,
        ];
    }

    public function markParticipantPresent(FestEvent $event, int $participantId, int $markedBy): bool
    {
        $participant = FestParticipant::with('registration')
            ->whereHas('registration', fn ($q) => $q->where('event_id', $event->id)->where('status', 'approved'))
            ->find($participantId);

        if (! $participant?->registration?->item_id) {
            return false;
        }

        FestAttendance::updateOrCreate(
            [
                'item_id'        => $participant->registration->item_id,
                'participant_id' => $participant->id,
            ],
            [
                'event_id'  => $event->id,
                'status'    => 'present',
                'marked_by' => $markedBy,
                'marked_at' => now(),
            ]
        );

        return true;
    }

    /** @return array{kind: string, event_id: int, entity_id: int, fest_id: string}|null */
    private function parsePayload(string $raw): ?array
    {
        $raw = trim($raw);
        if (! str_starts_with($raw, 'FEST|')) {
            return null;
        }

        $parts = explode('|', $raw);
        if (count($parts) < 5) {
            return null;
        }

        return [
            'event_id'  => (int) $parts[1],
            'kind'      => (string) $parts[2],
            'entity_id' => (int) $parts[3],
            'fest_id'   => (string) $parts[4],
        ];
    }

    /** @return array<string, mixed>|null */
    private function participantDetails(FestEvent $event, int $participantId): ?array
    {
        $participant = FestParticipant::with(['student', 'teacher', 'registration.item', 'registration.school', 'group'])
            ->whereHas('registration', fn ($q) => $q->where('event_id', $event->id)->where('status', 'approved'))
            ->find($participantId);

        if (! $participant) {
            return null;
        }

        return [
            'type'        => 'participant',
            'name'        => $participant->student?->name ?? $participant->teacher?->name,
            'fest_id'     => $participant->level_registration_number ?? $participant->chest_no,
            'chest_no'    => $participant->chest_no,
            'school'      => $participant->registration?->school?->name,
            'item'        => $participant->registration?->item?->title,
            'team'        => $participant->group?->team_name,
            'disqualified'=> (bool) $participant->disqualified_at,
        ];
    }

    /** @return array<string, mixed>|null */
    private function registrationDetails(FestEvent $event, int $registrationId): ?array
    {
        $registration = FestRegistration::with(['item', 'school', 'groups', 'participants.student'])
            ->where('event_id', $event->id)
            ->where('status', 'approved')
            ->find($registrationId);

        if (! $registration) {
            return null;
        }

        return [
            'type'     => 'team',
            'team'     => $registration->groups->first()?->team_name,
            'school'   => $registration->school?->name ?? Tenant::find($registration->school_id)?->name,
            'item'     => $registration->item?->title,
            'members'  => $registration->participants
                ->where('participant_role', '!=', 'standby')
                ->map(fn (FestParticipant $p) => [
                    'name'     => $p->student?->name ?? $p->teacher?->name,
                    'fest_id'  => $p->level_registration_number ?? $p->chest_no,
                    'chest_no' => $p->chest_no,
                ])->values()->all(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function volunteerDetails(FestEvent $event, int $volunteerId): ?array
    {
        $volunteer = FestVolunteer::where('event_id', $event->id)->find($volunteerId);
        if (! $volunteer) {
            return null;
        }

        return [
            'type'  => 'volunteer',
            'name'  => $volunteer->name,
            'role'  => $volunteer->role,
            'phone' => $volunteer->phone,
        ];
    }

    /** @return array<string, mixed>|null */
    private function staffDetails(FestEvent $event, int $staffId): ?array
    {
        $staff = FestEventStaff::with('user')->where('event_id', $event->id)->find($staffId);
        if (! $staff) {
            return null;
        }

        return [
            'type' => 'staff',
            'name' => $staff->user?->name,
            'role' => $staff->duty,
        ];
    }
}
