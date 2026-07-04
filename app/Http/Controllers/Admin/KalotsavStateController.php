<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FestEvent;
use App\Models\FestMark;
use App\Models\FestQualification;
use App\Models\FestStateProgram;
use App\Models\FestStateProgramPropagation;
use App\Models\Tenant;
use App\Support\TenantDomainSync;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KalotsavStateController extends Controller
{
    public function index()
    {
        $programs = FestStateProgram::query()
            ->where('event_type', 'kalolsavam')
            ->withCount(['propagations', 'items'])
            ->orderByDesc('created_at')
            ->get();

        return inertia('State/Kalotsav/Index', [
            'programs' => $programs,
        ]);
    }

    public function show(FestStateProgram $stateProgram)
    {
        abort_unless($stateProgram->event_type === 'kalolsavam', 404);

        $stateProgram->load(['propagations.sahodaya:id,name', 'items']);

        return inertia('State/Kalotsav/ProgramDetail', [
            'program' => $stateProgram,
        ]);
    }

    public function results(FestStateProgram $stateProgram)
    {
        abort_unless($stateProgram->event_type === 'kalolsavam', 404);

        $propagations = FestStateProgramPropagation::where('state_program_id', $stateProgram->id)
            ->with('sahodaya:id,name')
            ->get();

        $clusterResults = $propagations->map(function (FestStateProgramPropagation $prop) {
            if (! $prop->tenant_event_id) {
                return [
                    'sahodaya' => $prop->sahodaya?->name,
                    'level'    => $prop->level_round,
                    'status'   => 'not_propagated',
                    'results'  => [],
                ];
            }

            $event = FestEvent::find($prop->tenant_event_id);

            return [
                'sahodaya'           => $prop->sahodaya?->name,
                'level'              => $prop->level_round,
                'event_id'           => $event?->id,
                'event_title'        => $event?->title,
                'results_published'  => (bool) $event?->results_published,
                'registrations_count'=> $event ? FestMark::where('event_id', $event->id)->count() : 0,
            ];
        });

        return inertia('State/Kalotsav/Results', [
            'program'        => $stateProgram->only('id', 'title', 'academic_year', 'status'),
            'clusterResults' => $clusterResults,
        ]);
    }

    public function winners(FestStateProgram $stateProgram)
    {
        abort_unless($stateProgram->event_type === 'kalolsavam', 404);

        $eventIds = FestStateProgramPropagation::where('state_program_id', $stateProgram->id)
            ->whereNotNull('tenant_event_id')
            ->pluck('tenant_event_id');

        $qualifications = FestQualification::whereIn('event_id', $eventIds)
            ->with([
                'participant.student',
                'participant.teacher',
                'participant.registration.school',
                'item',
                'event',
                'nextLevelEvent',
            ])
            ->orderByDesc('promoted_at')
            ->get();

        $marks = FestMark::whereIn('event_id', $eventIds)
            ->whereIn('participant_id', $qualifications->pluck('participant_id'))
            ->get()
            ->keyBy(fn (FestMark $m) => "{$m->event_id}:{$m->item_id}:{$m->participant_id}");

        $tenants = Tenant::whereIn('id', $qualifications->pluck('event.tenant_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $winners = $qualifications->map(function (FestQualification $q) use ($marks, $tenants) {
            $markKey = "{$q->event_id}:{$q->item_id}:{$q->participant_id}";
            $mark = $marks->get($markKey);
            $posterUrl = null;

            if ($mark && in_array((int) $mark->position, [1, 2, 3], true)) {
                $tenant = $q->event?->tenant_id ? $tenants->get($q->event->tenant_id) : null;
                $base = $tenant ? TenantDomainSync::publicUrl($tenant) : null;
                if ($base && $q->event && $q->item) {
                    $posterUrl = rtrim($base, '/')."/fest/{$q->event_id}/items/{$q->item_id}/winners/{$mark->id}/poster.svg";
                }
            }

            return [
                'participant' => $q->participant?->student?->name ?? $q->participant?->teacher?->name,
                'reg_no'      => $q->participant?->student?->reg_no,
                'school'      => $q->participant?->registration?->school?->name,
                'item'        => $q->item?->title,
                'category'    => $q->item?->category,
                'grade'       => $mark?->grade,
                'from_event'  => $q->event?->title,
                'next_level'  => $q->nextLevelEvent?->level_round,
                'promoted_at' => $q->promoted_at?->toDateString(),
                'poster_url'  => $posterUrl,
            ];
        });

        return inertia('State/Kalotsav/Winners', [
            'program'    => $stateProgram->only('id', 'title', 'academic_year'),
            'winners'    => $winners,
        ]);
    }

    public function exportWinners(FestStateProgram $stateProgram): StreamedResponse
    {
        abort_unless($stateProgram->event_type === 'kalolsavam', 404);

        $eventIds = FestStateProgramPropagation::where('state_program_id', $stateProgram->id)
            ->whereNotNull('tenant_event_id')
            ->pluck('tenant_event_id');

        $rows = FestQualification::whereIn('event_id', $eventIds)
            ->with([
                'participant.student',
                'participant.teacher',
                'participant.registration.school',
                'item',
                'event',
                'nextLevelEvent',
            ])
            ->get();

        $marks = FestMark::whereIn('event_id', $eventIds)
            ->whereIn('participant_id', $rows->pluck('participant_id'))
            ->get()
            ->keyBy(fn (FestMark $m) => "{$m->event_id}:{$m->item_id}:{$m->participant_id}");

        return response()->streamDownload(function () use ($rows, $marks) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Participant', 'Reg No', 'School', 'Item', 'Category', 'Grade', 'From Event', 'Next Level', 'Promoted At']);
            foreach ($rows as $q) {
                $mark = $marks->get("{$q->event_id}:{$q->item_id}:{$q->participant_id}");
                fputcsv($out, [
                    $q->participant?->student?->name ?? $q->participant?->teacher?->name,
                    $q->participant?->student?->reg_no,
                    $q->participant?->registration?->school?->name,
                    $q->item?->title,
                    $q->item?->category,
                    $mark?->grade,
                    $q->event?->title,
                    $q->nextLevelEvent?->level_round,
                    $q->promoted_at?->toDateString(),
                ]);
            }
            fclose($out);
        }, "kalotsav-winners-{$stateProgram->id}.csv");
    }
}
