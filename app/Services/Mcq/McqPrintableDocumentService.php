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
    public function attendanceSheetPdf(
        McqExam $exam,
        ?string $schoolId = null,
        ?string $selectedClass = null,
        bool $inline = false,
        ?Tenant $sahodaya = null
    ): Response {
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '300');
        $rows = $this->registrationRows($exam, schoolId: $schoolId, selectedClass: $selectedClass);
        $sahodaya ??= Tenant::find($exam->tenant_id);
        $school = $schoolId ? Tenant::find($schoolId) : null;

        $pdf = Pdf::loadView('mcq.attendance-sheet', [
            'exam'          => $exam,
            'school'        => $school,
            'rows'          => $rows,
            'selectedClass' => $selectedClass,
            'orgName'       => $sahodaya?->name ?? 'Sahodaya',
            'logoSrc'       => $sahodaya ? TenantBranding::logoEmbedSrc($sahodaya) : null,
            'generatedAt'   => $this->generatedAt(),
        ])->setPaper('a4', 'portrait');

        $suffix = $schoolId ? '-school-'.substr($schoolId, 0, 8) : '';
        $filename = $this->slug($exam).'-attendance-sheet'.$suffix.'.pdf';

        return $inline ? $pdf->stream($filename, ['Attachment' => false]) : $pdf->download($filename);
    }

    public function markSheetPdf(McqExam $exam, ?Tenant $sahodaya = null): Response
    {
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '300');
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
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '300');
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

    public function classWiseCountsPdf(
        McqExam $exam,
        ?string $schoolId = null,
        bool $inline = false,
        ?Tenant $sahodaya = null
    ): Response {
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '300');
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
        $filename = $this->slug($exam).'-class-wise-counts'.$suffix.'.pdf';

        return $inline ? $pdf->stream($filename, ['Attachment' => false]) : $pdf->download($filename);
    }

    public function classWiseRegistrationPdf(
        McqExam $exam,
        ?string $schoolId = null,
        ?string $selectedClass = null,
        bool $inline = false,
        ?Tenant $sahodaya = null
    ): Response {
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '300');
        $query = McqRegistration::where('exam_id', $exam->id)
            ->active()
            ->with(['student.schoolClass', 'student.tenant', 'teacher', 'school', 'feeReceipt']);

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

            if ($selectedClass && filled($selectedClass) && strtolower(trim((string) $selectedClass)) !== 'all') {
                $cleanFilter = strtolower(trim(str_ireplace('class', '', (string) $selectedClass)));
                $cleanClass = strtolower(trim(str_ireplace('class', '', (string) $className)));
                if ($cleanClass !== $cleanFilter) {
                    continue;
                }
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
        $totalCount = 0;
        foreach ($classNames as $cName) {
            $sortedGrouped[$cName] = $grouped[$cName];
            $totalCount += count($grouped[$cName]);
        }

        $sahodaya ??= Tenant::find($exam->tenant_id);
        $school = $schoolId ? Tenant::find($schoolId) : null;

        $pdf = Pdf::loadView('mcq.class-wise-registration-pdf', [
            'exam'          => $exam,
            'school'        => $school,
            'groupedRows'   => $sortedGrouped,
            'totalCount'    => $totalCount,
            'selectedClass' => $selectedClass,
            'orgName'       => $sahodaya?->name ?? 'Sahodaya',
            'logoSrc'       => $sahodaya ? TenantBranding::logoEmbedSrc($sahodaya) : null,
            'generatedAt'   => $this->generatedAt(),
        ])->setPaper('a4', 'portrait');

        $suffix = $schoolId ? '-school-'.substr($schoolId, 0, 8) : '';
        $filename = $this->slug($exam).'-class-wise-registration-report'.$suffix.'.pdf';

        return $inline ? $pdf->stream($filename, ['Attachment' => false]) : $pdf->download($filename);
    }

    public function classWiseFeeDuePdf(
        McqExam $exam,
        ?string $schoolId = null,
        ?string $selectedClass = null,
        bool $inline = false,
        ?Tenant $sahodaya = null
    ): Response {
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '300');
        $query = McqRegistration::where('exam_id', $exam->id)
            ->active()
            ->with(['student.schoolClass', 'teacher', 'school', 'feeReceipt']);

        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        $registrations = $query->get();

        $schoolFees = \App\Models\McqSchoolFee::where('exam_id', $exam->id)
            ->with('feeReceipt')
            ->get()
            ->keyBy('school_id');
        $feeRate = (float) $exam->schoolPayablePerStudent();

        $grouped = [];
        foreach ($registrations as $reg) {
            $className = $reg->student?->schoolClass?->name;
            if (! $className) {
                $className = $reg->isTeacherRegistration() ? 'Teachers' : 'Unassigned Class';
            }

            if ($selectedClass && filled($selectedClass) && strtolower(trim((string) $selectedClass)) !== 'all') {
                $cleanFilter = strtolower(trim(str_ireplace('class', '', (string) $selectedClass)));
                $cleanClass = strtolower(trim(str_ireplace('class', '', (string) $className)));
                if ($cleanClass !== $cleanFilter) {
                    continue;
                }
            }

            if (! isset($grouped[$className])) {
                $grouped[$className] = [];
            }

            $schoolFee = $schoolFees->get($reg->school_id);
            $isPaid = $reg->feeReceipt?->status === 'approved'
                || $schoolFee?->status === 'approved'
                || $schoolFee?->feeReceipt?->status === 'approved';

            $grouped[$className][] = [
                'student_name'     => $reg->participantName(),
                'admission_number' => $reg->student?->admission_number ?: ($reg->student?->reg_no ?: '—'),
                'reg_no'           => $reg->student?->reg_no ?: '—',
                'class_name'       => $className,
                'school_name'      => $reg->school?->name ?: '—',
                'fee_amount'       => $feeRate,
                'payment_status'   => $isPaid ? 'Paid' : 'Unpaid',
                'receipt_no'       => $reg->feeReceipt?->transaction_ref ?: ($schoolFee?->feeReceipt?->transaction_ref ?: '—'),
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
        $totalCount = 0;
        $totalFee = 0;
        $totalPaid = 0;
        $totalUnpaid = 0;

        foreach ($classNames as $cName) {
            $sortedGrouped[$cName] = $grouped[$cName];
            foreach ($grouped[$cName] as $row) {
                $totalCount++;
                $totalFee += $row['fee_amount'];
                if ($row['payment_status'] === 'Paid') {
                    $totalPaid += $row['fee_amount'];
                } else {
                    $totalUnpaid += $row['fee_amount'];
                }
            }
        }

        $sahodaya ??= Tenant::find($exam->tenant_id);
        $school = $schoolId ? Tenant::find($schoolId) : null;

        $pdf = Pdf::loadView('mcq.class-wise-fee-due-pdf', [
            'exam'          => $exam,
            'school'        => $school,
            'groupedRows'   => $sortedGrouped,
            'totalCount'    => $totalCount,
            'totalFee'      => $totalFee,
            'totalPaid'     => $totalPaid,
            'totalUnpaid'   => $totalUnpaid,
            'selectedClass' => $selectedClass,
            'orgName'       => $sahodaya?->name ?? 'Sahodaya',
            'logoSrc'       => $sahodaya ? TenantBranding::logoEmbedSrc($sahodaya) : null,
            'generatedAt'   => $this->generatedAt(),
        ])->setPaper('a4', 'portrait');

        $suffix = $schoolId ? '-school-'.substr($schoolId, 0, 8) : '';
        $filename = $this->slug($exam).'-class-wise-fee-due-report'.$suffix.'.pdf';

        return $inline ? $pdf->stream($filename, ['Attachment' => false]) : $pdf->download($filename);
    }

    /**
     * Embed the student's photo as a cached, downscaled base64 data URI instead of
     * handing DomPDF an authenticated app URL (Student::photoUrl()/sahodayaPhotoUrl()).
     * Those routes require a session DomPDF doesn't have, so it either fails to fetch
     * the image at all or — worse — makes a real outbound HTTP request back into this
     * app per candidate, serially, while the worker rendering the PDF blocks waiting.
     * At Sahodaya-exam scale (thousands of candidates across every school) that's
     * enough to exhaust the PHP-FPM worker pool, the same failure mode already fixed
     * for the Fest attendance-sheet report (see FestReportService::attendanceSheetPdf()
     * and TenantStorage::photoBase64DataUri()'s docblock). Caching per student (keyed
     * on their own updated_at) means only the first report that touches a given
     * student's photo pays the decode/downscale cost — an edited photo busts its own
     * cache key since updated_at changes.
     */
    private function studentPhotoDataUri(?\App\Models\Student $student): ?string
    {
        if (! $student || ! $student->photo) {
            return null;
        }

        if (str_starts_with($student->photo, 'http://') || str_starts_with($student->photo, 'https://')) {
            return $student->photo;
        }

        $cacheKey = 'student-photo-thumb:'.$student->id.':'.($student->updated_at?->timestamp ?? 0);

        return \Illuminate\Support\Facades\Cache::remember(
            $cacheKey,
            now()->addDays(30),
            function () use ($student) {
                $tenant = $student->relationLoaded('tenant') ? $student->tenant : \App\Models\Tenant::find($student->tenant_id);

                return \App\Support\TenantStorage::photoBase64DataUri($tenant, $student->photo);
            },
        );
    }

    private function studentPhotoSrc(?\App\Models\Student $student): string
    {
        if ($student && $student->photo) {
            $dataUri = $this->studentPhotoDataUri($student);
            if ($dataUri) {
                return $dataUri;
            }
        }

        $gender = strtolower($student?->gender ?? '');

        return app(\App\Services\Events\FestIdCardService::class)->defaultAvatarDataUri($gender);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function registrationRows(
        McqExam $exam,
        bool $presentOnly = false,
        bool $withMarks = false,
        ?string $schoolId = null,
        ?string $selectedClass = null
    ): array {
        $query = McqRegistration::where('exam_id', $exam->id)
            ->with(['student.schoolClass', 'student.tenant', 'teacher', 'school', 'mark'])
            ->orderBy('hall_ticket_no')
            ->orderBy('id');

        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        if ($presentOnly) {
            $query->where('attendance_status', 'present');
        }

        $registrations = $query->get();

        if ($selectedClass && filled($selectedClass) && strtolower(trim((string) $selectedClass)) !== 'all') {
            $cleanFilter = strtolower(trim(str_ireplace('class', '', (string) $selectedClass)));
            $registrations = $registrations->filter(function ($reg) use ($cleanFilter) {
                $cName = strtolower(trim(str_ireplace('class', '', (string) ($reg->student?->schoolClass?->name ?? ''))));

                return $cName === $cleanFilter;
            });
        }

        return $registrations
            ->values()
            ->map(function (McqRegistration $reg, int $index) use ($withMarks) {
                $row = [
                    'sl'             => $index + 1,
                    'photo_url'      => $this->studentPhotoDataUri($reg->student),
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
