<?php

namespace App\Http\Controllers\Admin;

use App\Support\CsvSafety;
use App\Http\Controllers\Controller;
use App\Models\FestEvent;
use App\Models\FestMark;
use App\Models\FestQualification;
use App\Models\FestStateProgram;
use App\Models\FestStateProgramPropagation;
use App\Support\StateScope;
use App\Support\TenantDomainSync;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KalotsavStateController extends Controller
{
    public function index()
    {
        $programs = StateScope::apply(FestStateProgram::query())
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
        StateScope::assertOwns($stateProgram->state_id);
        abort_unless($stateProgram->event_type === 'kalolsavam', 404);

        $stateProgram->load(['propagations.sahodaya:id,name', 'items']);

        return inertia('State/Kalotsav/ProgramDetail', [
            'program' => $stateProgram,
        ]);
    }

    public function results(FestStateProgram $stateProgram)
    {
        StateScope::assertOwns($stateProgram->state_id);
        abort_unless($stateProgram->event_type === 'kalolsavam', 404);

        $propagations = FestStateProgramPropagation::where('state_program_id', $stateProgram->id)
            ->with('sahodaya')
            ->get();

        $clusterResults = $propagations->map(function (FestStateProgramPropagation $prop) {
            if (! $prop->tenant_event_id || ! $prop->sahodaya) {
                return [
                    'sahodaya' => $prop->sahodaya?->name,
                    'level'    => $prop->level_round,
                    'status'   => 'not_propagated',
                    'results'  => [],
                ];
            }

            $eventData = \App\Support\TenancyDatabase::whenDatabaseReady($prop->sahodaya, function () use ($prop) {
                $event = FestEvent::find($prop->tenant_event_id);
                if (! $event) {
                    return null;
                }

                return [
                    'id'                  => $event->id,
                    'title'               => $event->title,
                    'results_published'   => (bool) $event->results_published,
                    'registrations_count' => FestMark::where('event_id', $event->id)->count(),
                ];
            });

            return [
                'sahodaya'           => $prop->sahodaya?->name,
                'level'              => $prop->level_round,
                'event_id'           => $eventData['id'] ?? null,
                'event_title'        => $eventData['title'] ?? null,
                'results_published'  => $eventData['results_published'] ?? false,
                'registrations_count'=> $eventData['registrations_count'] ?? 0,
            ];
        });

        return inertia('State/Kalotsav/Results', [
            'program'        => $stateProgram->only('id', 'title', 'academic_year', 'status'),
            'clusterResults' => $clusterResults,
        ]);
    }

    public function winners(FestStateProgram $stateProgram)
    {
        StateScope::assertOwns($stateProgram->state_id);
        abort_unless($stateProgram->event_type === 'kalolsavam', 404);

        return inertia('State/Kalotsav/Winners', [
            'program' => $stateProgram->only('id', 'title', 'academic_year'),
            'winners' => $this->collectWinnerRows($stateProgram),
        ]);
    }

    public function exportWinners(FestStateProgram $stateProgram): StreamedResponse
    {
        StateScope::assertOwns($stateProgram->state_id);
        abort_unless($stateProgram->event_type === 'kalolsavam', 404);

        $rows = $this->collectWinnerRows($stateProgram);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            CsvSafety::fputcsv($out, ['Participant', 'Reg No', 'School', 'Item', 'Category', 'Grade', 'From Event', 'Next Level', 'Promoted At']);
            foreach ($rows as $w) {
                CsvSafety::fputcsv($out, [
                    $w['participant'], $w['reg_no'], $w['school'], $w['item'], $w['category'],
                    $w['grade'], $w['from_event'], $w['next_level'], $w['promoted_at'],
                ]);
            }
            fclose($out);
        }, "kalotsav-winners-{$stateProgram->id}.csv");
    }

    /**
     * Qualifications/marks live in each Sahodaya's own tenant database, so — like
     * results() above — this has to loop per-Sahodaya via TenancyDatabase rather than
     * running one whereIn('event_id', ...) across ids that span multiple databases.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function collectWinnerRows(FestStateProgram $stateProgram): \Illuminate\Support\Collection
    {
        $propagations = FestStateProgramPropagation::where('state_program_id', $stateProgram->id)
            ->whereNotNull('tenant_event_id')
            ->with('sahodaya')
            ->get();

        $winners = collect();

        foreach ($propagations as $propagation) {
            if (! $propagation->sahodaya) {
                continue;
            }

            $rows = \App\Support\TenancyDatabase::whenDatabaseReady($propagation->sahodaya, function () use ($propagation) {
                $qualifications = FestQualification::where('event_id', $propagation->tenant_event_id)
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

                $marks = FestMark::where('event_id', $propagation->tenant_event_id)
                    ->whereIn('participant_id', $qualifications->pluck('participant_id'))
                    ->get()
                    ->keyBy(fn (FestMark $m) => "{$m->event_id}:{$m->item_id}:{$m->participant_id}");

                $base = TenantDomainSync::publicUrl($propagation->sahodaya);

                return $qualifications->map(function (FestQualification $q) use ($marks, $base) {
                    $mark = $marks->get("{$q->event_id}:{$q->item_id}:{$q->participant_id}");
                    $posterUrl = null;

                    if ($mark && in_array((int) $mark->position, [1, 2, 3], true) && $base && $q->event && $q->item) {
                        $posterUrl = rtrim($base, '/')."/fest/{$q->event_id}/items/{$q->item_id}/winners/{$mark->id}/poster.svg";
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
            }, collect());

            $winners = $winners->concat($rows);
        }

        return $winners->sortByDesc('promoted_at')->values();
    }
}
