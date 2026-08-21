<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\FestClassGroupScheme;
use App\Support\FestPageActivity;
use App\Support\FestTeamSquadRules;
use App\Models\FestEvent;
use App\Models\FestIndividualChampionshipPoint;
use App\Models\FestMark;
use App\Models\Tenant;
use App\Services\Events\FestGradePointService;

class FestChampionshipController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $rows = FestIndividualChampionshipPoint::where('event_id', $event->id)
            ->with(['student'])
            ->orderByDesc('points')
            ->orderByDesc('group_points')
            ->orderBy('student_id')
            ->get()
            ->map(function (FestIndividualChampionshipPoint $row, int $index) {
                $school = Tenant::find($row->student?->tenant_id);

                return [
                    'rank'     => $index + 1,
                    'points'   => $row->points,
                    'group_points' => $row->group_points,
                    'category' => $row->category,
                    'gender'   => $row->gender,
                    'student'  => [
                        'id'   => $row->student_id,
                        'name' => $row->student?->name,
                        'reg_no' => $row->student?->reg_no,
                    ],
                    'school' => $school?->name,
                ];
            });

        return $this->inertia('Sahodaya/Events/Championship', $this->withEventActivity($event, FestPageActivity::CHAMPIONSHIP, [
            'event'       => $event,
            'leaderboard' => $rows,
        ]));
    }

    public function recalculate(string $tenantId, FestEvent $event, FestGradePointService $gradePointService)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $aggregated = [];

        FestMark::where('event_id', $event->id)
            ->with(['participant.student', 'participant.registration.item'])
            ->each(function (FestMark $mark) use ($event, $gradePointService, &$aggregated) {
                $student = $mark->participant?->student;
                if (! $student) {
                    return;
                }

                $item = $mark->participant->registration?->item;
                $points = $gradePointService->pointsForMark($event, $mark);
                // fest_individual_championship_points.category is constrained to
                // lp/up/hs/hss/open — but English Fest / Kalotsav-style events store
                // class_group in a different scheme (category_1, category_2, ...).
                // canonicalKey() maps every known alias onto the constrained scheme;
                // anything it doesn't recognize falls back to 'open' rather than
                // violating the DB check constraint outright.
                $category = FestClassGroupScheme::canonicalKey($item?->class_group);
                $category = in_array($category, ['lp', 'up', 'hs', 'hss', 'open'], true) ? $category : 'open';
                $gender = match ($student->gender) {
                    'male'   => 'male',
                    'female' => 'female',
                    default  => 'open',
                };

                if (! isset($aggregated[$student->id])) {
                    $aggregated[$student->id] = [
                        'points'       => 0,
                        'group_points' => 0,
                        'category'     => $category,
                        'gender'       => $gender,
                    ];
                }

                // Pair/trio/group/team items save one FestMark row per teammate with the
                // same position/points — crediting the full value to every member's
                // individual total would let an 11-person group's 1st place outweigh a
                // genuine solo achievement. Group results are tracked separately and only
                // used as a tiebreak (see the sort in index() and results()), never added
                // to the primary `points` total.
                if ($item && FestTeamSquadRules::isMultiPerson($item->participant_type)) {
                    $aggregated[$student->id]['group_points'] += $points;
                } else {
                    $aggregated[$student->id]['points'] += $points;
                }
            });

        foreach ($aggregated as $studentId => $data) {
            FestIndividualChampionshipPoint::updateOrCreate(
                ['event_id' => $event->id, 'student_id' => $studentId],
                [
                    'category'     => $data['category'],
                    'gender'       => $data['gender'],
                    'points'       => $data['points'],
                    'group_points' => $data['group_points'],
                ]
            );
        }

        FestIndividualChampionshipPoint::where('event_id', $event->id)
            ->whereNotIn('student_id', array_keys($aggregated))
            ->delete();

        return back()->with('success', count($aggregated).' championship point row(s) updated.');
    }
}
