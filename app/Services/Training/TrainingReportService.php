<?php

namespace App\Services\Training;

use App\Models\Tenant;
use App\Models\TrainingAttendance;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Support\ExcelExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrainingReportService
{
    /** @return list<array<string, mixed>> */
    public function attendanceRows(TrainingProgram $program): array
    {
        $program->load(['sessions' => fn ($q) => $q->orderBy('scheduled_at'), 'registrations.teacher', 'registrations.school']);

        $attendance = TrainingAttendance::whereIn(
            'registration_id',
            $program->registrations->pluck('id')
        )->get()->groupBy('registration_id');

        $sessions = $program->sessions;

        return $program->registrations
            ->sortBy(fn (TrainingRegistration $r) => $r->teacher?->name ?? '')
            ->values()
            ->map(function (TrainingRegistration $registration) use ($sessions, $attendance) {
                $bySession = $attendance->get($registration->id, collect())->keyBy('session_id');
                $presentCount = $bySession->where('status', 'present')->count();

                $row = [
                    'teacher_name'   => $registration->teacher?->name ?? '',
                    'designation'    => $registration->teacher?->designation ?? '',
                    'school_name'    => $registration->school instanceof Tenant
                        ? $registration->school->name
                        : (Tenant::find($registration->school_id)?->name ?? ''),
                    'status'         => $registration->status,
                    'days_present'   => $presentCount,
                    'total_sessions' => $sessions->count(),
                ];

                foreach ($sessions as $session) {
                    $row['session_'.$session->id] = $bySession->get($session->id)?->status ?? 'unmarked';
                }

                return $row;
            })
            ->all();
    }

    public function exportAttendance(TrainingProgram $program): StreamedResponse
    {
        $program->load(['sessions' => fn ($q) => $q->orderBy('scheduled_at')]);
        $rows = $this->attendanceRows($program);

        $headers = ['Teacher', 'Designation', 'School', 'Registration status', 'Days present', 'Total days'];
        foreach ($program->sessions as $session) {
            $label = $session->title;
            if ($session->scheduled_at) {
                $label .= ' ('.$session->scheduled_at->format('d M Y').')';
            }
            $headers[] = $label;
        }

        return ExcelExport::download(
            'training-attendance-'.$program->id,
            $headers,
            collect($rows)->map(function ($r) use ($program) {
                $line = [
                    $r['teacher_name'],
                    $r['designation'],
                    $r['school_name'],
                    $r['status'],
                    $r['days_present'],
                    $r['total_sessions'],
                ];
                foreach ($program->sessions as $session) {
                    $line[] = $r['session_'.$session->id] ?? 'unmarked';
                }

                return $line;
            }),
        );
    }
}
