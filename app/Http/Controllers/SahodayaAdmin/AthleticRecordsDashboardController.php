<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestAthleticRecord;
use App\Models\FestEvent;
use App\Models\FestRecordBreak;

class AthleticRecordsDashboardController extends SahodayaAdminController
{
    public function index(string $tenantId)
    {
        $eventIds = FestEvent::forTenant($this->sahodaya->id)
            ->ofType('sports')
            ->pluck('id');

        $records = FestAthleticRecord::whereIn('event_id', $eventIds)
            ->with(['item', 'event'])
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get();

        $recentBreaks = FestRecordBreak::whereIn('event_id', $eventIds)
            ->with(['item', 'event', 'participant.student'])
            ->orderByDesc('broken_at')
            ->limit(50)
            ->get();

        return $this->inertia('Sahodaya/Sports/Records', $this->programNavProps('sports-meet') + [
            'records'      => $records,
            'recentBreaks' => $recentBreaks,
        ]);
    }
}
