<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Models\Tenant;
use App\Services\Events\EventContext;
use Illuminate\Http\Request;

class FestChestNumberController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $participants = FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('status', 'approved'))
            ->with(['registration.item', 'registration.school', 'student', 'teacher', 'group'])
            ->orderBy('chest_no')
            ->get()
            ->map(function (FestParticipant $p) {
                $school = Tenant::find($p->registration->school_id);

                return [
                    'id'         => $p->id,
                    'chest_no'   => $p->chest_no,
                    'name'       => $p->student?->name ?? $p->teacher?->name,
                    'school'     => $school?->name,
                    'item'       => $p->registration->item?->title,
                    'group'      => $p->group?->team_name,
                ];
            });

        return $this->inertia('Sahodaya/Events/ChestNumbers', [
            'event'        => $event,
            'participants' => $participants,
        ]);
    }

    public function generate(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $ctx = EventContext::for($event);
        $count = 0;

        FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('status', 'approved'))
            ->with('registration.item')
            ->whereNull('chest_no')
            ->each(function (FestParticipant $p) use ($ctx, &$count) {
                if (! $p->registration->item_id) {
                    return;
                }
                $p->update(['chest_no' => $ctx->nextChestNumber($p->registration->item)]);
                $count++;
            });

        return back()->with('success', "Assigned {$count} chest number(s).");
    }

    public function print(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $rows = FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('status', 'approved'))
            ->whereNotNull('chest_no')
            ->with(['registration.item', 'student', 'teacher'])
            ->orderBy('chest_no')
            ->get()
            ->map(function (FestParticipant $p) {
                return [
                    'chest_no' => $p->chest_no,
                    'name'     => $p->student?->name ?? $p->teacher?->name,
                    'item'     => $p->registration->item?->title,
                    'school'   => Tenant::find($p->registration->school_id)?->name,
                ];
            });

        return view('fest.chest-numbers-print', [
            'event' => $event,
            'rows'  => $rows,
        ]);
    }
}
