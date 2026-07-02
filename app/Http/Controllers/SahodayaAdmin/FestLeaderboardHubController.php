<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\FestPageActivity;
use App\Models\FestAthleticRecord;
use App\Models\FestEvent;
use App\Models\FestIndividualChampionshipPoint;
use App\Models\FestRecordBreak;
use App\Models\Tenant;
use App\Services\Events\EventContext;

class FestLeaderboardHubController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $ctx = EventContext::for($event);

        $schoolBoard = array_slice($ctx->scoreboardBySchool(), 0, 10);
        $houseBoard = array_slice($ctx->scoreboardByHouse(), 0, 10);

        $championship = FestIndividualChampionshipPoint::where('event_id', $event->id)
            ->with('student')
            ->orderByDesc('points')
            ->limit(10)
            ->get()
            ->map(function ($row, $i) {
                $school = Tenant::find($row->student?->tenant_id);

                return [
                    'rank'    => $i + 1,
                    'name'    => $row->student?->name,
                    'school'  => $school?->name,
                    'points'  => $row->points,
                    'category'=> $row->category,
                ];
            });

        $recordBreaks = FestRecordBreak::where('event_id', $event->id)
            ->with(['item', 'participant.student'])
            ->orderByDesc('broken_at')
            ->limit(10)
            ->get();

        $records = FestAthleticRecord::where('event_id', $event->id)
            ->with('item')
            ->orderBy('item_id')
            ->get();

        $base = "/sahodaya-admin/{$this->sahodaya->id}/events/{$event->id}";

        return $this->inertia('Sahodaya/Events/LeaderboardHub', $this->withEventActivity($event, FestPageActivity::LEADERBOARD, [
            'event'        => $event,
            'schoolBoard'  => $schoolBoard,
            'houseBoard'   => $houseBoard,
            'championship' => $championship,
            'recordBreaks' => $recordBreaks,
            'records'      => $records,
            'links'        => [
                ['label' => 'Overall ranking report', 'href' => "{$base}/reports/overall-ranking"],
                ['label' => 'Individual championship', 'href' => "{$base}/championship"],
                ['label' => 'Athletic records', 'href' => "{$base}/athletic-records"],
                ['label' => 'Finance invoices', 'href' => "{$base}/finance"],
                ['label' => 'Live public board', 'href' => '/fest/'.$event->id.'/live', 'external' => true],
                ['label' => 'All reports', 'href' => "{$base}/reports"],
            ],
        ]));
    }
}
