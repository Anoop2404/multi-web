<?php

namespace App\Services\Training;

use App\Models\TrainingPendingSchool;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Support\ExcelExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrainingQrReportService
{
    /** @return array<string, mixed> */
    public function summary(TrainingProgram $program): array
    {
        $regs = TrainingRegistration::where('program_id', $program->id)->with(['teacher', 'school', 'pendingSchool'])->get();
        $qr = $regs->where('registration_source', 'qr');

        $byDesignation = $qr->groupBy(fn ($r) => $r->teacher?->designation ?: 'Unspecified')
            ->map->count()
            ->sortDesc()
            ->all();

        return [
            'qr_registrations'   => $qr->count(),
            'teachers_created'   => $qr->where('teacher_created', true)->count(),
            'pending_schools'    => TrainingPendingSchool::where('program_id', $program->id)->where('status', 'pending')->count(),
            'by_designation'     => $byDesignation,
            'total_registrations'=> $regs->count(),
            'registrations'      => $qr->values()->map(fn (TrainingRegistration $r) => [
                'id'              => $r->id,
                'teacher_name'    => $r->teacher?->name,
                'email'           => $r->teacher?->email,
                'mobile'          => $r->teacher?->mobile,
                'designation'     => $r->teacher?->designation,
                'department'      => $r->department,
                'school'          => $r->displaySchoolName() === '—' ? null : $r->displaySchoolName(),
                'school_code'     => $r->pendingSchool?->school_code ?? $r->school?->school_prefix,
                'membership'      => $r->pending_school_id ? null : $r->school?->membership_status,
                'teacher_created' => $r->teacher_created,
                'pending_school'  => (bool) $r->pending_school_id,
                'status'          => $r->status,
                'created_at'      => $r->created_at?->toDateTimeString(),
            ])->all(),
            'pending_school_rows'=> TrainingPendingSchool::where('program_id', $program->id)
                ->orderByDesc('id')
                ->get()
                ->map(fn (TrainingPendingSchool $p) => [
                    'id'            => $p->id,
                    'school_name'   => $p->school_name,
                    'school_code'   => $p->school_code,
                    'contact_name'  => $p->contact_name,
                    'contact_email' => $p->contact_email,
                    'contact_phone' => $p->contact_phone,
                    'status'        => $p->status,
                    'linked_school_id' => $p->linked_school_id,
                    'created_at'    => $p->created_at?->toDateTimeString(),
                ])->all(),
        ];
    }

    public function exportQrRegistrations(TrainingProgram $program): StreamedResponse
    {
        $summary = $this->summary($program);
        $rows = array_map(fn ($r) => [
            $r['id'],
            $r['teacher_name'],
            $r['email'],
            $r['mobile'],
            $r['designation'],
            $r['department'],
            $r['school'],
            $r['school_code'],
            $r['membership'],
            $r['teacher_created'] ? 'yes' : 'no',
            $r['pending_school'] ? 'yes' : 'no',
            $r['status'],
            $r['created_at'],
        ], $summary['registrations']);

        return ExcelExport::download(
            'training-qr-registrations-'.$program->id.'.xlsx',
            ['ID', 'Teacher', 'Email', 'Mobile', 'Designation', 'Department', 'School', 'School code', 'Membership', 'Teacher created', 'Pending school', 'Status', 'Registered at'],
            $rows,
        );
    }

    /**
     * QR registrations where a new teacher record was created (teacher_created=true).
     *
     * @return list<array<string, mixed>>
     */
    public function createdTeachers(TrainingProgram $program): array
    {
        return TrainingRegistration::where('program_id', $program->id)
            ->where('registration_source', 'qr')
            ->where('teacher_created', true)
            ->with(['teacher', 'school', 'pendingSchool'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (TrainingRegistration $r) => [
                'id'              => $r->id,
                'teacher_name'    => $r->teacher?->name,
                'email'           => $r->teacher?->email,
                'mobile'          => $r->teacher?->mobile,
                'designation'     => $r->teacher?->designation,
                'department'      => $r->department,
                'school'          => $r->displaySchoolName() === '—' ? null : $r->displaySchoolName(),
                'school_code'     => $r->pendingSchool?->school_code ?? $r->school?->school_prefix,
                'status'          => $r->status,
                'is_verified'     => $r->teacher?->isVerified() ?? false,
                'verified_at'     => $r->teacher?->verified_at?->toDateTimeString(),
                'created_at'      => $r->created_at?->toDateTimeString(),
            ])
            ->all();
    }
}
