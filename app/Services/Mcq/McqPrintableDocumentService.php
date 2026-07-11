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
    public function attendanceSheetPdf(McqExam $exam, ?Tenant $sahodaya = null): Response
    {
        $rows = $this->registrationRows($exam);
        $sahodaya ??= Tenant::find($exam->tenant_id);

        $pdf = Pdf::loadView('mcq.attendance-sheet', [
            'exam'        => $exam,
            'rows'        => $rows,
            'orgName'     => $sahodaya?->name ?? 'Sahodaya',
            'logoSrc'     => $sahodaya ? TenantBranding::logoEmbedSrc($sahodaya) : null,
            'generatedAt' => $this->generatedAt(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download($this->slug($exam).'-attendance-sheet.pdf');
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

    /**
     * @return list<array<string, mixed>>
     */
    private function registrationRows(McqExam $exam, bool $presentOnly = false, bool $withMarks = false): array
    {
        $query = McqRegistration::where('exam_id', $exam->id)
            ->with(['student.schoolClass', 'teacher', 'school', 'mark'])
            ->orderBy('hall_ticket_no')
            ->orderBy('id');

        if ($presentOnly) {
            $query->where('attendance_status', 'present');
        }

        return $query->get()
            ->values()
            ->map(function (McqRegistration $reg, int $index) use ($withMarks) {
                $row = [
                    'sl'             => $index + 1,
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
