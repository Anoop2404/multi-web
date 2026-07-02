<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\SahodayaAdmin\Concerns\ForwardsSahodayaProgramDashboard;
use App\Models\FestEvent;
use App\Services\Events\ProgramHubDataService;

class SportsProgramController extends SahodayaAdminController
{
    use ForwardsSahodayaProgramDashboard;

    protected function sahodayaProgramSlug(): string
    {
        return 'sports-meet';
    }

    public function championship(string $tenantId, ProgramHubDataService $hubData)
    {
        $events = FestEvent::forTenant($this->sahodaya->id)
            ->ofType('sports')
            ->whereIn('status', ['published', 'registration_open', 'ongoing', 'completed'])
            ->orderByDesc('event_start')
            ->get(['id', 'title', 'status', 'results_published']);

        return $this->inertia('Sahodaya/Sports/Championship', [
            'events'          => $events,
            'houseStandings'  => $hubData->crossEventHouseStandings($this->sahodaya),
        ]);
    }
}
