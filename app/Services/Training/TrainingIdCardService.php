<?php

namespace App\Services\Training;

use App\Models\Tenant;
use App\Models\TrainingRegistration;
use App\Support\TenantBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/** DomPDF teacher ID card for a training programme registration. */
class TrainingIdCardService
{
    public function download(TrainingRegistration $registration, ?Tenant $sahodaya = null): Response
    {
        $registration->loadMissing(['program', 'teacher', 'school']);
        $program = $registration->program;
        $teacher = $registration->teacher;
        abort_unless($program && $teacher, 404);

        $school = $registration->school;
        $sahodaya ??= $school?->parent_id
            ? Tenant::find($school->parent_id)
            : Tenant::find($program->tenant_id);
        abort_unless($sahodaya, 422, 'Sahodaya not found.');

        $name = $teacher->name ?: 'Teacher';
        $initials = collect(preg_split('/\s+/', trim($name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('') ?: '?';

        $pdf = Pdf::loadView('training.id-card', [
            'registration' => $registration,
            'program'      => $program,
            'teacher'      => $teacher,
            'school'       => $school,
            'sahodaya'     => $sahodaya,
            'orgName'      => $sahodaya->name,
            'logoSrc'      => TenantBranding::logoEmbedSrc($sahodaya),
            'photoSrc'     => $teacher->photoDataUri(),
            'initials'     => $initials,
            'teacherName'  => $name,
            'schoolName'   => $school?->name ?? '—',
            'programTitle' => $program->title,
            'regId'        => (string) $registration->id,
            'status'       => $registration->status,
        ])->setPaper([0, 0, 272.13, 204.09], 'portrait'); // ~96mm × 72mm

        $slug = str($program->title)->slug()->limit(40, '')->toString();
        $who = str($name)->slug()->limit(30, '')->toString();

        return $pdf->download("{$slug}-id-card-{$who}.pdf");
    }
}
