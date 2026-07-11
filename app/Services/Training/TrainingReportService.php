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
        $program->load([
            'sessions' => fn ($q) => $q->orderBy('scheduled_at'),
            'registrations.teacher.teachingType',
            'registrations.school',
        ]);

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
                    'category'       => $registration->teacher?->teachingType?->label ?? '',
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

        $headers = ['Teacher', 'Category', 'School', 'Registration status', 'Days present', 'Total days'];
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
                    $r['category'],
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

    /** Blank printable sheet for physical venue marking. */
    public function exportAttendanceSheetPdf(TrainingProgram $program, ?Tenant $sahodaya = null): \Illuminate\Http\Response
    {
        $program->load([
            'sessions' => fn ($q) => $q->orderBy('scheduled_at'),
            'registrations' => fn ($q) => $q->orderBy('id'),
            'registrations.teacher.teachingType',
            'registrations.school',
            'registrations.pendingSchool',
        ]);

        $lifecycle = app(TrainingRegistrationLifecycle::class);
        $attendees = $program->registrations
            ->filter(fn (TrainingRegistration $r) => $lifecycle->canMarkAttendance($r, $program))
            ->sortBy(fn (TrainingRegistration $r) => mb_strtolower($r->teacher?->name ?? ''))
            ->values()
            ->map(function (TrainingRegistration $r, int $index) {
                $school = $r->school instanceof Tenant
                    ? $r->school->name
                    : ($r->pendingSchool?->school_name ?? '');

                return [
                    'sl' => $index + 1,
                    'teacher' => $r->teacher?->name ?? '',
                    'category' => $r->teacher?->teachingType?->label ?? '',
                    'school' => $school,
                ];
            });

        $sessions = $program->sessions->map(fn ($session) => [
            'id' => $session->id,
            'title' => $session->title,
            'date' => $session->scheduled_at?->format('d M Y'),
        ])->values();

        if ($sessions->isEmpty()) {
            $sessions = collect([[
                'id' => 0,
                'title' => 'Attendance',
                'date' => null,
            ]]);
        }

        $sahodaya ??= Tenant::find($program->tenant_id);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('training.attendance-sheet', [
            'program' => $program,
            'orgName' => $sahodaya?->name ?? 'Sahodaya',
            'logoSrc' => $sahodaya ? \App\Support\TenantBranding::logoEmbedSrc($sahodaya) : null,
            'attendees' => $attendees,
            'sessions' => $sessions,
            'generatedAt' => now()->timezone(config('app.timezone'))->format('d M Y · h:i A'),
        ])->setPaper('a4', $sessions->count() > 3 ? 'landscape' : 'portrait');

        return $pdf->download(str($program->title)->slug().'-attendance-sheet.pdf');
    }

    /** Filled attendance report (present / absent) with Sahodaya branding. */
    public function exportAttendanceReportPdf(TrainingProgram $program, ?Tenant $sahodaya = null): \Illuminate\Http\Response
    {
        $program->load(['sessions' => fn ($q) => $q->orderBy('scheduled_at')]);
        $rows = $this->attendanceRows($program);
        $sahodaya ??= Tenant::find($program->tenant_id);
        $sessions = $program->sessions;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('training.attendance-report', [
            'program' => $program,
            'orgName' => $sahodaya?->name ?? 'Sahodaya',
            'logoSrc' => $sahodaya ? \App\Support\TenantBranding::logoEmbedSrc($sahodaya) : null,
            'rows' => $rows,
            'sessions' => $sessions,
            'generatedAt' => now()->timezone(config('app.timezone'))->format('d M Y · h:i A'),
        ])->setPaper('a4', $sessions->count() > 3 ? 'landscape' : 'portrait');

        return $pdf->download(str($program->title)->slug().'-attendance-report.pdf');
    }

    /** @deprecated use exportAttendanceSheetPdf */
    public function exportAttendancePdf(TrainingProgram $program, ?Tenant $sahodaya = null): \Illuminate\Http\Response
    {
        return $this->exportAttendanceSheetPdf($program, $sahodaya);
    }

    public function exportRegistrations(TrainingProgram $program): StreamedResponse
    {
        $rows = $this->registrationReportRows($program);

        return ExcelExport::download(
            'training-registrations-'.$program->id.'.xlsx',
            ['Sl No', 'Teacher name', 'Category', 'School'],
            $rows,
        );
    }

    public function exportRegistrationsPdf(TrainingProgram $program, ?Tenant $sahodaya = null): \Illuminate\Http\Response
    {
        $rows = $this->registrationReportRows($program);
        $sahodaya ??= Tenant::find($program->tenant_id);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('training.registrations-report', [
            'program' => $program,
            'orgName' => $sahodaya?->name ?? 'Sahodaya',
            'logoSrc' => $sahodaya ? \App\Support\TenantBranding::logoEmbedSrc($sahodaya) : null,
            'rows' => $rows,
            'generatedAt' => now()->timezone(config('app.timezone'))->format('d M Y · h:i A'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download(str($program->title)->slug().'-registrations.pdf');
    }

    /** @return \Illuminate\Support\Collection<int, list<int|string>> */
    private function registrationReportRows(TrainingProgram $program)
    {
        $program->load([
            'registrations' => fn ($q) => $q->latest('id'),
            'registrations.teacher.teachingType',
            'registrations.school',
            'registrations.pendingSchool',
        ]);

        return $program->registrations->values()->map(function (TrainingRegistration $r, int $index) {
            $school = $r->school instanceof Tenant
                ? $r->school->name
                : ($r->pendingSchool?->school_name ?? '');

            return [
                $index + 1,
                $r->teacher?->name ?? '',
                $r->teacher?->teachingType?->label ?? '',
                $school,
            ];
        });
    }
}
