<?php

namespace App\Services\Training;

use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\TrainingAttendance;
use App\Support\AcademicYear;
use App\Support\TenancyDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TrainingCpdService
{
    /**
     * CPD hours for one teacher in an academic year (sum of present session durations).
     * Only confirmed/completed registrations count.
     */
    public function hoursForTeacher(Teacher $teacher, ?int $yearId = null): float
    {
        $minutes = $this->presentMinutesQuery($yearId)
            ->where('training_registrations.teacher_id', $teacher->id)
            ->sum('training_sessions.duration_minutes');

        return $this->minutesToHours((float) $minutes);
    }

    /**
     * School-level CPD aggregates for a Sahodaya (hours this academic year).
     *
     * @return Collection<int, array{school_id: string, school: string, teachers: int, hours: float, sessions_present: int}>
     */
    public function summaryForSahodaya(string $sahodayaId, ?int $yearId = null): Collection
    {
        $schoolIds = TenancyDatabase::schoolIdsFor($sahodayaId);
        if ($schoolIds === []) {
            return collect();
        }

        $rows = $this->presentMinutesQuery($yearId)
            ->whereIn('training_registrations.school_id', $schoolIds)
            ->select([
                'training_registrations.school_id',
                DB::raw('COUNT(DISTINCT training_registrations.teacher_id) as teachers'),
                DB::raw('COALESCE(SUM(training_sessions.duration_minutes), 0) as minutes'),
                DB::raw('COUNT(*) as sessions_present'),
            ])
            ->groupBy('training_registrations.school_id')
            ->havingRaw('COALESCE(SUM(training_sessions.duration_minutes), 0) > 0')
            ->get()
            ->keyBy('school_id');

        if ($rows->isEmpty()) {
            return collect();
        }

        $schools = Tenant::whereIn('id', $rows->keys())->orderBy('name')->get(['id', 'name']);

        return $schools->map(function (Tenant $school) use ($rows) {
            $row = $rows->get($school->id);

            return [
                'school_id' => $school->id,
                'school' => $school->name,
                'teachers' => (int) ($row->teachers ?? 0),
                'hours' => $this->minutesToHours((float) ($row->minutes ?? 0)),
                'sessions_present' => (int) ($row->sessions_present ?? 0),
            ];
        })->values();
    }

    /**
     * Teacher-level CPD rows for a school (hours this academic year).
     *
     * @return Collection<int, array{teacher_id: int, teacher: string, hours: float, sessions_present: int}>
     */
    public function summaryForSchool(string $schoolId, ?int $yearId = null): Collection
    {
        $rows = $this->presentMinutesQuery($yearId)
            ->where('training_registrations.school_id', $schoolId)
            ->select([
                'training_registrations.teacher_id',
                DB::raw('COALESCE(SUM(training_sessions.duration_minutes), 0) as minutes'),
                DB::raw('COUNT(*) as sessions_present'),
            ])
            ->groupBy('training_registrations.teacher_id')
            ->get()
            ->keyBy('teacher_id');

        if ($rows->isEmpty()) {
            return collect();
        }

        $teachers = Teacher::whereIn('id', $rows->keys())->orderBy('name')->get(['id', 'name']);

        return $teachers->map(function (Teacher $teacher) use ($rows) {
            $row = $rows->get($teacher->id);

            return [
                'teacher_id' => $teacher->id,
                'teacher' => $teacher->name,
                'hours' => $this->minutesToHours((float) ($row->minutes ?? 0)),
                'sessions_present' => (int) ($row->sessions_present ?? 0),
            ];
        })->values();
    }

    /**
     * Total CPD hours across a Sahodaya for the dashboard widget.
     */
    public function totalHoursForSahodaya(string $sahodayaId, ?int $yearId = null): float
    {
        $schoolIds = TenancyDatabase::schoolIdsFor($sahodayaId);
        if ($schoolIds === []) {
            return 0.0;
        }

        $minutes = $this->presentMinutesQuery($yearId)
            ->whereIn('training_registrations.school_id', $schoolIds)
            ->sum('training_sessions.duration_minutes');

        return $this->minutesToHours((float) $minutes);
    }

    /**
     * Total CPD hours across a school for the dashboard widget.
     */
    public function totalHoursForSchool(string $schoolId, ?int $yearId = null): float
    {
        $minutes = $this->presentMinutesQuery($yearId)
            ->where('training_registrations.school_id', $schoolId)
            ->sum('training_sessions.duration_minutes');

        return $this->minutesToHours((float) $minutes);
    }

    /**
     * CPD hours attributable to one registration (present sessions only).
     */
    public function hoursForRegistration(int $registrationId): float
    {
        $minutes = TrainingAttendance::query()
            ->where('training_attendance.registration_id', $registrationId)
            ->whereIn('training_attendance.status', \App\Models\TrainingAttendance::PRESENT_LIKE)
            ->join('training_sessions', 'training_sessions.id', '=', 'training_attendance.session_id')
            ->sum('training_sessions.duration_minutes');

        return $this->minutesToHours((float) $minutes);
    }

    /**
     * Map of registration_id => CPD hours for a set of registrations.
     *
     * @param  list<int>|Collection<int, int>  $registrationIds
     * @return Collection<int, float>
     */
    public function hoursForRegistrations(iterable $registrationIds): Collection
    {
        $ids = collect($registrationIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return TrainingAttendance::query()
            ->whereIn('training_attendance.registration_id', $ids)
            ->whereIn('training_attendance.status', \App\Models\TrainingAttendance::PRESENT_LIKE)
            ->join('training_sessions', 'training_sessions.id', '=', 'training_attendance.session_id')
            ->select([
                'training_attendance.registration_id',
                DB::raw('COALESCE(SUM(training_sessions.duration_minutes), 0) as minutes'),
            ])
            ->groupBy('training_attendance.registration_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                (int) $row->registration_id => $this->minutesToHours((float) $row->minutes),
            ]);
    }

    /**
     * Base query: present attendance on confirmed/completed registrations, optionally scoped by program year.
     */
    protected function presentMinutesQuery(?int $yearId = null)
    {
        $resolvedYearId = $this->resolveYearId($yearId);

        $query = TrainingAttendance::query()
            ->whereIn('training_attendance.status', \App\Models\TrainingAttendance::PRESENT_LIKE)
            ->join('training_registrations', 'training_registrations.id', '=', 'training_attendance.registration_id')
            ->join('training_sessions', 'training_sessions.id', '=', 'training_attendance.session_id')
            ->join('training_programs', 'training_programs.id', '=', 'training_registrations.program_id')
            ->whereIn('training_registrations.status', ['confirmed', 'completed']);

        if ($resolvedYearId !== null) {
            $query->where('training_programs.academic_year_id', $resolvedYearId);
        }

        return $query;
    }

    protected function resolveYearId(?int $yearId): ?int
    {
        if ($yearId !== null) {
            return $yearId;
        }

        return AcademicYear::activeId();
    }

    protected function minutesToHours(float $minutes): float
    {
        return round($minutes / 60, 2);
    }
}
