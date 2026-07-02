<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\SahodayaAdmin\Concerns\ForwardsSahodayaProgramDashboard;
use App\Models\FestEvent;

class KalotsavProgramController extends SahodayaAdminController
{
    use ForwardsSahodayaProgramDashboard;

    protected function sahodayaProgramSlug(): string
    {
        return 'kalotsav';
    }

    public function schoolRounds(string $tenantId)
    {
        $events = FestEvent::forTenant($this->sahodaya->id)
            ->ofType('kalolsavam')
            ->where('level_round', 'school')
            ->with(['conductingSchool:id,name'])
            ->withCount(['items', 'registrations'])
            ->orderByDesc('event_start')
            ->get()
            ->map(fn (FestEvent $e) => [
                'id'                  => $e->id,
                'title'               => $e->title,
                'school'              => $e->conductingSchool?->name,
                'school_id'           => $e->conducting_school_id,
                'status'              => $e->status,
                'items_count'         => $e->items_count,
                'registrations_count' => $e->registrations_count,
                'results_published'   => $e->results_published,
                'parent_event_id'     => $e->parent_event_id,
                'linked'              => filled($e->parent_event_id),
            ]);

        $parentEvents = FestEvent::forTenant($this->sahodaya->id)
            ->ofType('kalolsavam')
            ->whereIn('level_round', ['sahodaya', 'state'])
            ->whereIn('status', ['published', 'registration_open', 'ongoing', 'completed'])
            ->orderByDesc('event_start')
            ->get(['id', 'title', 'level_round']);

        return $this->inertia('Sahodaya/Kalotsav/SchoolRounds', [
            'schoolEvents' => $events,
            'parentEvents' => $parentEvents,
        ]);
    }
}
