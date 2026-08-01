<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\Tenant;
use App\Support\TenantBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/** Printable attendance / mark / result sheets for Talent Search exams. */
class McqPrintableDocumentService
{
    public function attendanceSheetPdf(McqExam $exam, ?string $schoolId = null, ?Tenant $sahodaya = null): Response
    {
        $rows = $this->registrationRows($exam, schoolId: $schoolId);
        $sahodaya ??= Tenant::find($exam->tenant_id);

        $pdf = Pdf::loadView('mcq.attendance-sheet', [
            'exam'        => $exam,
            'rows'        => $rows,
            'orgName'     => $sahodaya?->name ?? 'Sahodaya',
            'logoSrc'     => $sahodaya ? TenantBranding::logoEmbedSrc($sahodaya) : null,
            'generatedAt' => $this->generatedAt(),
        ])->setPaper('a4', 'portrait');

        $suffix = $schoolId ? '-school-'.substr($schoolId, 0, 8) : '';

        return $pdf->download($this->slug($exam).'-attendance-sheet'.$suffix.'.pdf');
    }

    public function markSheetPdf(McqExam $exam, ?Tenant $sahodaya = null): Response
    {
        $rows = $this->registrationRows($exam, presentOnly: true);
        $sahodaya ??= Tenant::find($exam->tenant_id);

        $pdf = Pdf::loadView('mcq.mark-sheet', [
            'exam'        => $exam,
            'rows'        => $rows,
            'orgName'     => $sahodaya?->name ?? 'Sahodaya',
            'logoSrc'     => $sahodaya ? TenantBranding::logoEmbedSrc($sahodaya) : null,
            'generatedAt' => $this->generatedAt(),
            'totalQuestions' => (int) ($exam->total_questions ?: 0),
        ])->setPaper('a4', 'landscape');

        return $pdf->download($this->slug($exam).'-mark-sheet.pdf');
    }

    public function resultSheetPdf(McqExam $exam, ?Tenant $sahodaya = null): Response
    {
        $rows = $this->registrationRows($exam, withMarks: true);
        $sahodaya ??= Tenant::find($exam->tenant_id);

        $pdf = Pdf::loadView('mcq.result-sheet', [
            'exam'        => $exam,
            'rows'        => $rows,
            'orgName'     => $sahodaya?->name ?? 'Sahodaya',
            'logoSrc'     => $sahodaya ? TenantBranding::logoEmbedSrc($sahodaya) : null,
            'generatedAt' => $this->generatedAt(),
            'published'   => (bool) $exam->results_published,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($this->slug($exam).'-result-sheet.pdf');
    }

    public function classWiseCountsPdf(McqExam $exam, ?string $schoolId = null, ?Tenant $sahodaya = null): Response
    {
        $matrix = app(McqReportService::class)->classWiseCountMatrix($exam, $schoolId);
        $sahodaya ??= Tenant::find($exam->tenant_id);

        $pdf = Pdf::loadView('mcq.class-wise-counts', [
            'exam'        => $exam,
            'matrix'      => $matrix,
            'orgName'     => $sahodaya?->name ?? 'Sahodaya',
            'logoSrc'     => $sahodaya ? TenantBranding::logoEmbedSrc($sahodaya) : null,
            'generatedAt' => $this->generatedAt(),
        ])->setPaper('a4', count($matrix['classes']) > 6 ? 'landscape' : 'portrait');

        $suffix = $schoolId ? '-school-'.substr($schoolId, 0, 8) : '';

        return $pdf->download($this->slug($exam).'-class-wise-counts'.$suffix.'.pdf');
    }

    public function classWiseRegistrationPdf(McqExam $exam, ?string $schoolId = null, ?Tenant $sahodaya = null): Response
    {
        $query = McqRegistration::where('exam_id', $exam->id)
            ->active()
            ->with(['student.schoolClass', 'teacher', 'school', 'feeReceipt']);

        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        $registrations = $query->get();

        $grouped = [];
        foreach ($registrations as $reg) {
            $className = $reg->student?->schoolClass?->name;
            if (! $className) {
                $className = $reg->isTeacherRegistration() ? 'Teachers' : 'Unassigned Class';
            }

            if (! isset($grouped[$className])) {
                $grouped[$className] = [];
            }

            $grouped[$className][] = [
                'photo_src'        => $this->studentPhotoSrc($reg->student),
                'student_name'     => $reg->participantName(),
                'admission_number' => $reg->student?->admission_number ?: ($reg->student?->reg_no ?: '—'),
                'reg_no'           => $reg->student?->reg_no ?: '—',
                'hall_ticket_no'   => $reg->hall_ticket_no ?: '—',
                'class_name'       => $className,
                'school_name'      => $reg->school?->name ?: '—',
                'approval_status'  => ucfirst(str_replace('_', ' ', $reg->approval_status ?? 'pending')),
                'attendance_status'=> ucfirst($reg->attendance_status ?? 'pending'),
            ];
        }

        $classNames = array_keys($grouped);
        usort($classNames, function ($a, $b) {
            $numA = (int) filter_var($a, FILTER_SANITIZE_NUMBER_INT);
            $numB = (int) filter_var($b, FILTER_SANITIZE_NUMBER_INT);
            if ($numA > 0 && $numB > 0 && $numA !== $numB) {
                return $numA <=> $numB;
            }

            return strnatcasecmp($a, $b);
        });

        $sortedGrouped = [];
        foreach ($classNames as $cName) {
            $sortedGrouped[$cName] = $grouped[$cName];
        }

        $sahodaya ??= Tenant::find($exam->tenant_id);
        $school = $schoolId ? Tenant::find($schoolId) : null;

        $pdf = Pdf::loadView('mcq.class-wise-registration-pdf', [
            'exam'        => $exam,
            'school'      => $school,
            'groupedRows' => $sortedGrouped,
            'totalCount'  => $registrations->count(),
            'orgName'     => $sahodaya?->name ?? 'Sahodaya',
            'logoSrc'     => $sahodaya ? TenantBranding::logoEmbedSrc($sahodaya) : null,
            'generatedAt' => $this->generatedAt(),
        ])->setPaper('a4', 'portrait');

        $suffix = $schoolId ? '-school-'.substr($schoolId, 0, 8) : '';

        return $pdf->download($this->slug($exam).'-class-wise-registration-report'.$suffix.'.pdf');
    }

    public function classWiseFeeDuePdf(McqExam $exam, ?string $schoolId = null, ?Tenant $sahodaya = null): Response
    {
        $matrix = app(McqReportService::class)->classWiseFeeDueMatrix($exam, $schoolId);
        $sahodaya ??= Tenant::find($exam->tenant_id);
        $school = $schoolId ? Tenant::find($schoolId) : null;

        $pdf = Pdf::loadView('mcq.class-wise-fee-due', [
            'exam'        => $exam,
            'school'      => $school,
            'matrix'      => $matrix,
            'orgName'     => $sahodaya?->name ?? 'Sahodaya',
            'logoSrc'     => $sahodaya ? TenantBranding::logoEmbedSrc($sahodaya) : null,
            'generatedAt' => $this->generatedAt(),
        ])->setPaper('a4', 'portrait');

        $suffix = $schoolId ? '-school-'.substr($schoolId, 0, 8) : '';

        return $pdf->download($this->slug($exam).'-class-wise-fee-due-report'.$suffix.'.pdf');
    }

    private function studentPhotoSrc(?\App\Models\Student $student): string
    {
        if ($student && $student->photo) {
            try {
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($student->photo)) {
                    $content = \Illuminate\Support\Facades\Storage::disk('public')->get($student->photo);
                    $mime = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($student->photo) ?: 'image/jpeg';

                    return 'data:'.$mime.';base64,'.base64_encode($content);
                }
                if (\Illuminate\Support\Facades\Storage::exists($student->photo)) {
                    $content = \Illuminate\Support\Facades\Storage::get($student->photo);
                    $mime = \Illuminate\Support\Facades\Storage::mimeType($student->photo) ?: 'image/jpeg';

                    return 'data:'.$mime.';base64,'.base64_encode($content);
                }
            } catch (\Throwable $e) {
                // Ignore failure, fall back to avatar
            }
        }

        $gender = strtolower($student?->gender ?? '');

        return app(\App\Services\Events\FestIdCardService::class)->defaultAvatarDataUri($gender);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function registrationRows(McqExam $exam, bool $presentOnly = false, bool $withMarks = false, ?string $schoolId = null): array
    {
        $query = McqRegistration::where('exam_id', $exam->id)
            ->with(['student.schoolClass', 'teacher', 'school', 'mark'])
            ->orderBy('hall_ticket_no')
            ->orderBy('id');

        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        if ($presentOnly) {
            $query->where('attendance_status', 'present');
        }

        return $query->get()
            ->values()
            ->map(function (McqRegistration $reg, int $index) use ($withMarks) {
                $row = [
                    'sl'             => $index + 1,
                    'photo_url'      => $reg->student?->sahodayaPhotoUrl($exam->tenant_id) ?? $reg->student?->photoUrl(),
                    'hall_ticket_no' => $reg->hall_ticket_no ?: '—',
                    'name'           => $reg->participantName() ?: '—',
                    'school'         => $reg->school?->name ?: '—',
                    'class'          => $reg->student?->schoolClass?->name ?: '—',
                    'attendance'     => $reg->attendanceStatusLabel(),
                ];

                if ($withMarks) {
                    $row['score'] = $reg->mark?->score;
                    $row['percentage'] = $reg->mark?->percentage;
                    $row['grade'] = $reg->mark?->grade;
                    $row['rank'] = $reg->mark?->rank;
                }

                return $row;
            })
            ->all();
    }

    private function slug(McqExam $exam): string
    {
        return str($exam->code ?: $exam->title)->slug()->limit(50, '')->toString() ?: 'mcq-exam';
    }

    private function generatedAt(): string
    {
        return now()->timezone(config('app.timezone'))->format('d M Y · h:i A');
    }
}
