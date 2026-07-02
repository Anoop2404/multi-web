<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\FestPageActivity;
use App\Models\FestAthleticRecord;
use App\Models\FestEvent;
use App\Models\FestRecordBreak;
use App\Services\Events\FestAthleticRecordService;
use App\Services\Events\FestCertificateService;
use App\Services\Audit\PlatformAuditLogger;
use App\Support\FestClassGroupScheme;
use Illuminate\Http\Request;

class FestAthleticRecordController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $event->load('items');

        $records = FestAthleticRecord::where('event_id', $event->id)
            ->with(['item', 'breaks.participant.student'])
            ->orderBy('item_id')
            ->get();

        $breaks = FestRecordBreak::where('event_id', $event->id)
            ->with(['item', 'participant.student', 'participant.registration.school'])
            ->orderByDesc('broken_at')
            ->get()
            ->map(function (FestRecordBreak $break) {
                $cert = app(FestCertificateService::class)->issueRecordBreakCertificate($break);

                return array_merge($break->toArray(), [
                    'certificate_uuid' => $cert->verification_uuid,
                ]);
            });

        return $this->inertia('Sahodaya/Events/AthleticRecords', $this->withEventActivity($event, FestPageActivity::ATHLETIC_RECORDS, [
            'event'   => $event,
            'records' => $records,
            'breaks'  => $breaks,
            'classGroups' => FestClassGroupScheme::labels(null, $event),
        ]));
    }

    public function store(Request $request, string $tenantId, FestEvent $event, FestAthleticRecordService $service, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'item_id'          => 'required|exists:fest_event_items,id',
            'class_group'      => 'required|in:lp,up,hs,hss,open',
            'gender'           => 'required|in:male,female,open',
            'record_direction' => 'required|in:lower_better,higher_better',
            'record_value'     => 'required|string|max:50',
            'record_unit'      => 'nullable|string|max:20',
            'holder_name'      => 'nullable|string|max:255',
            'notes'            => 'nullable|string|max:500',
        ]);

        FestAthleticRecord::updateOrCreate(
            [
                'event_id'    => $event->id,
                'item_id'     => $data['item_id'],
                'class_group' => $data['class_group'],
                'gender'      => $data['gender'],
            ],
            [
                'record_direction' => $data['record_direction'],
                'record_value'     => $service->parseMeasurement($data['record_value']),
                'record_unit'      => $data['record_unit'],
                'holder_name'      => $data['holder_name'],
                'notes'            => $data['notes'] ?? null,
                'record_date'      => now()->toDateString(),
            ]
        );

        $audit->festEvent($event, FestPageActivity::ATHLETIC_RECORDS, 'fest.athletic_record.saved', 'Athletic record saved', [
            'item_id' => $data['item_id'],
        ]);

        return back()->with('success', 'Record saved.');
    }

    public function destroy(string $tenantId, FestEvent $event, FestAthleticRecord $record, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($record->event_id !== $event->id, 404);

        $record->delete();

        $audit->festEvent($event, FestPageActivity::ATHLETIC_RECORDS, 'fest.athletic_record.deleted', 'Athletic record removed');

        return back()->with('success', 'Record removed.');
    }

    public function togglePrize(string $tenantId, FestEvent $event, FestRecordBreak $break, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($break->event_id !== $event->id, 404);

        $break->update(['prize_awarded' => ! $break->prize_awarded]);

        $audit->festEvent($event, FestPageActivity::ATHLETIC_RECORDS, 'fest.record_break.prize_toggled', 'Record break prize status updated', [
            'break_id' => $break->id,
            'prize_awarded' => $break->prize_awarded,
        ]);

        return back()->with('success', 'Prize status updated.');
    }

    public function recordBreakCertificate(string $tenantId, FestEvent $event, FestRecordBreak $break, FestCertificateService $certs)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($break->event_id !== $event->id, 404);

        $certificate = $certs->issueRecordBreakCertificate($break);

        return redirect()->route('certificates.print', $certificate->verification_uuid);
    }
}
