<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestAppeal;
use App\Models\FestEvent;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Http\Request;

class FestAppealsHubController extends SahodayaAdminController
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'pending');
        $search = $request->input('search', '');

        $eventIds = FestEvent::where('tenant_id', $this->sahodaya->id)->pluck('id');

        $appeals = FestAppeal::query()
            ->whereIn('event_id', $eventIds)
            ->where('status', $status)
            ->when(filled($search), function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('reason', 'like', "%{$search}%")
                        ->orWhereHas('participant.student', fn ($s) => $s
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('reg_no', 'like', "%{$search}%"))
                        ->orWhereHas('participant.registration.school', fn ($s) => $s->where('name', 'like', "%{$search}%"));
                });
            })
            ->with([
                'event:id,title,status',
                'participant.student:id,name,reg_no',
                'participant.registration.item:id,title',
                'participant.registration.school:id,name',
            ])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $statusCounts = [
            'pending'  => FestAppeal::whereIn('event_id', $eventIds)->where('status', 'pending')->count(),
            'approved' => FestAppeal::whereIn('event_id', $eventIds)->where('status', 'approved')->count(),
            'rejected' => FestAppeal::whereIn('event_id', $eventIds)->where('status', 'rejected')->count(),
        ];

        return $this->inertia('Sahodaya/Fest/AppealsQueue', [
            'appeals'      => $appeals,
            'filters'      => [
                'status' => $status,
                'search' => $search,
            ],
            'statusCounts' => $statusCounts,
        ]);
    }

    public function resolve(Request $request, string $tenantId, FestAppeal $appeal, PlatformAuditLogger $audit)
    {
        $event = FestEvent::findOrFail($appeal->event_id);
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'status'          => 'required|in:approved,rejected',
            'resolution_note' => 'nullable|string|max:1000',
        ]);

        $appeal->update([
            'status'              => $data['status'],
            'resolution_note'     => $data['resolution_note'] ?? null,
            'resolved_by_user_id' => $request->user()->id,
            'resolved_at'         => now(),
        ]);

        $audit->festAppealResolved($appeal, $data['status']);

        return back()->with('success', 'Appeal '.$data['status'].'.');
    }

    public function markFeePaid(string $tenantId, FestAppeal $appeal, PlatformAuditLogger $audit)
    {
        $event = FestEvent::findOrFail($appeal->event_id);
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $appeal->update(['fee_paid_at' => now()]);
        $audit->festEvent($event, 'appeals', 'fest.appeal.fee_paid', 'Appeal fee marked paid', ['appeal_id' => $appeal->id]);

        return back()->with('success', 'Appeal fee marked paid.');
    }
}
