<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\FestPageActivity;
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
            ->orderBy('student_id')
            ->get()
            ->map(function (FestIndividualChampionshipPoint $row, int $index) {
                $school = Tenant::find($row->student?->tenant_id);

                return [
                    'rank'     => $index + 1,
                    'points'   => $row->points,
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

                $points = $gradePointService->pointsForMark($event, $mark);
                $category = $mark->participant->registration?->item?->class_group ?? 'open';
                $gender = match ($student->gender) {
                    'male'   => 'male',
                    'female' => 'female',
                    default  => 'open',
                };

                if (! isset($aggregated[$student->id])) {
                    $aggregated[$student->id] = [
                        'points'   => 0,
                        'category' => $category,
                        'gender'   => $gender,
                    ];
                }

                $aggregated[$student->id]['points'] += $points;
            });

        foreach ($aggregated as $studentId => $data) {
            FestIndividualChampionshipPoint::updateOrCreate(
                ['event_id' => $event->id, 'student_id' => $studentId],
                [
                    'category' => $data['category'],
                    'gender'   => $data['gender'],
                    'points'   => $data['points'],
                ]
            );
        }

        FestIndividualChampionshipPoint::where('event_id', $event->id)
            ->whereNotIn('student_id', array_keys($aggregated))
            ->delete();

        return back()->with('success', count($aggregated).' championship point row(s) updated.');
    }
}
