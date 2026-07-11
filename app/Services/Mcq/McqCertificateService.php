<?php

namespace App\Services\Mcq;

use App\Models\McqCertificate;
use App\Models\McqCertificateTemplate;
use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\Tenant;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class McqCertificateService
{
    public function assertEligible(McqRegistration $registration): void
    {
        $registration->loadMissing(['exam', 'mark']);

        if ($registration->blocksScoring()) {
            throw ValidationException::withMessages([
                'attendance' => 'Students marked '.$registration->attendanceStatusLabel().' are not eligible for certificates.',
            ]);
        }

        if (! $registration->exam?->results_published) {
            throw ValidationException::withMessages([
                'results' => 'Results must be published before issuing certificates.',
            ]);
        }

        if ($registration->status !== 'submitted' || ! $registration->mark) {
            throw ValidationException::withMessages([
                'marks' => 'Student must have submitted marks before issuing a certificate.',
            ]);
        }
    }

    public function issue(McqRegistration $registration): McqCertificate
    {
        $this->assertEligible($registration);
        $registration->loadMissing(['exam.series', 'student.schoolClass', 'school', 'mark']);

        $existing = McqCertificate::where('registration_id', $registration->id)->first();
        if ($existing) {
            return $existing;
        }

        $template = $this->resolveTemplate($registration->exam);
        $snapshot = $template?->design_json ?? $this->defaultDesign();

        return McqCertificate::create([
            'registration_id'          => $registration->id,
            'certificate_template_id'  => $template?->id,
            'design_snapshot_json'     => $snapshot,
            'verification_uuid'        => (string) Str::uuid(),
            'generated_at'             => now(),
        ]);
    }

    public function issueBulk(McqExam $exam): int
    {
        abort_unless($exam->results_published, 422, 'Publish results before generating certificates.');

        $count = 0;
        McqRegistration::where('exam_id', $exam->id)
            ->where('status', 'submitted')
            ->whereNotIn('attendance_status', McqRegistration::BLOCKING_ATTENDANCE_STATUSES)
            ->whereHas('mark')
            ->with(['exam', 'mark', 'student', 'school'])
            ->chunkById(100, function ($regs) use (&$count) {
                foreach ($regs as $registration) {
                    try {
                        $this->issue($registration);
                        $count++;
                    } catch (ValidationException) {
                        continue;
                    }
                }
            });

        return $count;
    }

    /** @return array<string, string> */
    public function previewSampleFields(McqExam $exam, ?Tenant $sahodaya = null): array
    {
        return [
            'student_name'     => 'Sample Student',
            'school_name'      => 'Sample Model School',
            'exam_title'       => $exam->title,
            'series_title'     => $exam->series?->title ?? 'Talent Search',
            'level_label'      => 'Level '.((int) ($exam->exam_level ?? 1)),
            'score'            => '42',
            'percentage'       => '84',
            'grade'            => 'A',
            'rank'             => '5',
            'certificate_date' => now()->format('d M Y'),
            'sahodaya_name'    => $sahodaya?->name ?? '',
        ];
    }

    /** @return array<string, mixed> */
    public function previewDesign(McqExam $exam): array
    {
        $template = $this->resolveTemplate($exam);

        return $template?->design_json ?? $this->defaultDesign();
    }

    /** @return array<string, string> */
    public function fieldValues(McqRegistration $registration, ?Tenant $sahodaya = null): array
    {
        $registration->loadMissing(['exam.series', 'student', 'school', 'mark']);
        $exam = $registration->exam;
        $mark = $registration->mark;

        return [
            'student_name'   => $registration->student?->name ?? '',
            'school_name'    => $registration->school?->name ?? '',
            'exam_title'     => $exam?->title ?? '',
            'series_title'   => $exam?->series?->title ?? '',
            'level_label'    => 'Level '.((int) ($exam?->exam_level ?? 1)),
            'score'          => (string) ($mark?->score ?? ''),
            'percentage'     => (string) ($mark?->percentage ?? ''),
            'grade'          => (string) ($mark?->grade ?? ''),
            'rank'           => $mark?->rank !== null ? (string) $mark->rank : '',
            'certificate_date' => now()->format('d M Y'),
            'sahodaya_name'  => $sahodaya?->name ?? '',
        ];
    }

    private function resolveTemplate(McqExam $exam): ?McqCertificateTemplate
    {
        if ($exam->certificate_template_id) {
            return McqCertificateTemplate::find($exam->certificate_template_id);
        }

        return McqCertificateTemplate::where('tenant_id', $exam->tenant_id)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /** @return array<string, mixed> */
    private function defaultDesign(): array
    {
        return [
            'body' => 'This is to certify that {student_name} of {school_name} participated in {exam_title} and achieved grade {grade}.',
        ];
    }
}
