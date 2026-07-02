<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\Certificate;
use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Services\Events\FestCertificateService;
use Illuminate\Http\Request;

class FestCertificateOpsController extends SahodayaAdminController
{
    public function search(Request $request, string $tenantId)
    {
        $query = trim((string) $request->input('q', ''));

        $results = collect();

        if (strlen($query) >= 2) {
            $eventIds = FestEvent::where('tenant_id', $this->sahodaya->id)->pluck('id');

            if ($request->filled('event_id')) {
                $eventIds = $eventIds->intersect([(int) $request->input('event_id')]);
            }

            if ($eventIds->isEmpty()) {
                $eventIds = collect();
            }

            $participantQuery = FestParticipant::whereHas('registration', fn ($q) => $q
                ->whereIn('event_id', $eventIds)
                ->where('status', 'approved'))
                ->with([
                    'student',
                    'registration.event',
                    'registration.item',
                    'registration.school',
                ]);

            $participantQuery->where(function ($q) use ($query) {
                $q->where('chest_no', $query)
                    ->orWhere('level_registration_number', 'like', "%{$query}%")
                    ->orWhereHas('student', fn ($s) => $s
                        ->where('name', 'like', "%{$query}%")
                        ->orWhere('reg_no', 'like', "%{$query}%")
                        ->orWhere('admission_number', 'like', "%{$query}%"));
            });

            $participants = $participantQuery->limit(50)->get();
            $participantIds = $participants->pluck('id');

            $certificates = Certificate::where('entity_type', FestParticipant::class)
                ->whereIn('entity_id', $participantIds)
                ->get()
                ->keyBy('entity_id');

            $service = app(FestCertificateService::class);

            $results = $participants->map(function (FestParticipant $p) use ($certificates, $service) {
                $cert = $certificates->get($p->id);

                return [
                    'participant_id' => $p->id,
                    'name'           => $p->student?->name ?? $p->teacher?->name,
                    'reg_no'         => $p->level_registration_number ?? $p->student?->reg_no,
                    'chest_no'       => $p->chest_no,
                    'school'         => $p->registration?->school?->name,
                    'event'          => $p->registration?->event?->title,
                    'event_id'       => $p->registration?->event_id,
                    'item'           => $p->registration?->item?->title,
                    'certificate'    => $cert ? [
                        'id'           => $cert->id,
                        'uuid'         => $cert->verification_uuid,
                        'cert_type'    => $cert->cert_type,
                        'collected_at' => $cert->collected_at,
                    ] : null,
                ];
            });
        }

        if ($request->wantsJson()) {
            return response()->json(['results' => $results]);
        }

        return $this->inertia('Sahodaya/Events/CertificateSearch', [
            'query'   => $query,
            'results' => $results,
        ]);
    }

    public function generateParticipation(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $created = app(FestCertificateService::class)->generateParticipationForEvent($event);

        return back()->with('success', count($created).' participation certificate(s) generated.');
    }

    public function collect(Request $request, string $tenantId, Certificate $certificate)
    {
        $participant = FestParticipant::find($certificate->entity_id);
        abort_unless($participant, 404);

        $event = $participant->registration?->event;
        abort_if(! $event || $event->tenant_id !== $this->sahodaya->id, 403);

        $certificate->update([
            'collected_at'          => now(),
            'collected_by_user_id'  => $request->user()->id,
        ]);

        return back()->with('success', 'Certificate marked as collected.');
    }

    public function bulkCollect(Request $request, string $tenantId)
    {
        $data = $request->validate([
            'certificate_ids'   => 'required|array|min:1',
            'certificate_ids.*' => 'integer|exists:certificates,id',
        ]);

        $eventIds = FestEvent::where('tenant_id', $this->sahodaya->id)->pluck('id');
        $participantIds = FestParticipant::whereHas('registration', fn ($q) => $q
            ->whereIn('event_id', $eventIds))
            ->pluck('id');

        $updated = Certificate::whereIn('id', $data['certificate_ids'])
            ->where('entity_type', FestParticipant::class)
            ->whereIn('entity_id', $participantIds)
            ->update([
                'collected_at'         => now(),
                'collected_by_user_id' => $request->user()->id,
            ]);

        return back()->with('success', "{$updated} certificate(s) marked as collected.");
    }
}
