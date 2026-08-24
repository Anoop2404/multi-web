<?php

namespace App\Support;

use App\Models\Certificate;
use App\Models\FestParticipant;
use Illuminate\Support\Facades\DB;

/**
 * Explicit call sites over an implicit Eloquent-observer hook, matching this codebase's
 * existing style (PlatformAuditLogger/NotificationService are invoked directly from
 * controllers/services, not via model events) — an explicit call is easy to audit,
 * "any save anywhere might silently flip this flag" is not.
 *
 * These are cheap, best-effort markers only: one indexed bulk UPDATE each, no re-render,
 * no content_hash recomputation (that only happens in RenderCertificateChunkJob and
 * VerifyCertificateStalenessCommand, the two places that actually call
 * FestCertificateService::renderContext()+contentHash()). The scheduled
 * certificates:verify-staleness sweep is the authoritative backstop for anything a call
 * site here misses.
 */
class CertificateStalenessMarker
{
    /** A participant's own certificate(s) — winner and/or participation. */
    public static function markStaleForParticipant(int $participantId): void
    {
        Certificate::where('entity_type', FestParticipant::class)
            ->where('entity_id', $participantId)
            ->where('is_stale', false)
            ->update(['is_stale' => true]);
    }

    /**
     * Every certificate rendered from this template — a template body/layout edit
     * changes what every certificate using it would now render as.
     */
    public static function markStaleForTemplate(int $templateId): void
    {
        Certificate::where('template_id', $templateId)
            ->where('is_stale', false)
            ->update(['is_stale' => true]);
    }

    /**
     * A participation certificate is an aggregate over every item a person entered (see
     * FestCertificateService::generateParticipationForEvent()) — a change to one
     * FestParticipant's registration (add/drop an item, disqualify, school transfer)
     * can change another person's already-generated aggregate certificate too, not just
     * their own. Marks every participation certificate for the same student/teacher
     * across the given event.
     */
    public static function markStaleForParticipationAggregate(int $eventId, ?int $studentId, ?int $teacherId): void
    {
        if (! $studentId && ! $teacherId) {
            return;
        }

        $participantIds = FestParticipant::where(function ($q) use ($eventId) {
            $q->where('event_id', $eventId)
              ->orWhereHas('registration', fn ($rq) => $rq->where('event_id', $eventId));
        })
            ->where(function ($q) use ($studentId, $teacherId) {
                if ($studentId) {
                    $q->orWhere('student_id', $studentId);
                }
                if ($teacherId) {
                    $q->orWhere('teacher_id', $teacherId);
                }
            })
            ->pluck('id');

        if ($participantIds->isEmpty()) {
            return;
        }

        Certificate::where('entity_type', FestParticipant::class)
            ->whereIn('entity_id', $participantIds)
            ->where('cert_type', 'participation')
            ->where('is_stale', false)
            ->update(['is_stale' => true]);
    }
}
