<?php

namespace App\Services\Mcq;

use App\Models\McqCertificate;
use App\Models\McqCertificateTemplate;
use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\Tenant;
use App\Support\Mcq\McqResultReleaseSettings;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class McqCertificateService
{
    public function assertEligible(McqRegistration $registration): void
    {
        $registration->loadMissing(['exam', 'mark']);
        $exam = $registration->exam;

        if ($registration->blocksScoring()) {
            throw ValidationException::withMessages([
                'attendance' => 'Students marked '.$registration->attendanceStatusLabel().' are not eligible for certificates.',
            ]);
        }

        if (! $exam?->results_published) {
            throw ValidationException::withMessages([
                'results' => 'Results must be published before issuing certificates.',
            ]);
        }

        if (! McqResultReleaseSettings::certificatesEnabled($exam)) {
            throw ValidationException::withMessages([
                'certificates' => 'Certificates are not enabled for this exam.',
            ]);
        }

        if ($registration->status !== 'submitted' || ! $registration->mark) {
            throw ValidationException::withMessages([
                'marks' => 'Student must have submitted marks before issuing a certificate.',
            ]);
        }

        if (! $this->meetsEligibilityRules($registration)) {
            throw ValidationException::withMessages([
                'eligibility' => 'This student does not meet the certificate eligibility rules for this exam.',
            ]);
        }
    }

    /** Whether a student may see / download a certificate (no exception). */
    public function isAvailableForStudent(McqRegistration $registration): bool
    {
        try {
            $this->assertEligible($registration);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    /** Student-facing reason when a certificate is not available. */
    public function unavailableReason(McqRegistration $registration): ?string
    {
        $registration->loadMissing(['exam', 'mark']);
        $exam = $registration->exam;

        if (! $exam) {
            return 'Certificate not available.';
        }

        if (! McqResultReleaseSettings::certificatesEnabled($exam)) {
            return 'Certificates are not issued for this exam.';
        }

        if (! $exam->results_published) {
            return 'Certificate not available yet — results have not been released.';
        }

        if ($registration->status !== 'submitted' || ! $registration->mark) {
            return 'Certificate not available — exam not submitted.';
        }

        if ($registration->blocksScoring()) {
            return 'Certificate not available for '.$registration->attendanceStatusLabel().' attendance.';
        }

        if (! $this->meetsEligibilityRules($registration)) {
            $settings = McqResultReleaseSettings::certificates($exam);

            return match ($settings['eligibility']) {
                McqResultReleaseSettings::CERT_ELIGIBILITY_PASS => 'Certificate requires a passing score.',
                McqResultReleaseSettings::CERT_ELIGIBILITY_GRADE_BANDS => 'Certificate requires an eligible grade band.',
                default => 'Not eligible for a certificate.',
            };
        }

        return null;
    }

    public function issue(McqRegistration $registration): McqCertificate
    {
        $this->assertEligible($registration);
        $registration->loadMissing(['exam.series', 'student.schoolClass', 'teacher', 'school', 'mark']);

        $existing = McqCertificate::where('registration_id', $registration->id)->first();
        if ($existing) {
            return $existing;
        }

        $template = $this->resolveTemplate($registration->exam);
        $snapshot = $template?->design_json ?? $this->defaultDesign();

        $certificate = McqCertificate::create([
            'registration_id'          => $registration->id,
            'certificate_template_id'  => $template?->id,
            'design_snapshot_json'     => $snapshot,
            'verification_uuid'        => (string) Str::uuid(),
            'generated_at'             => now(),
        ]);

        app(McqExamNotifier::class)->certificateReady($registration);

        return $certificate;
    }

    public function issueBulk(McqExam $exam): int
    {
        abort_unless($exam->results_published, 422, 'Publish results before generating certificates.');
        abort_unless(McqResultReleaseSettings::certificatesEnabled($exam), 422, 'Enable certificates on the exam before generating.');

        $count = 0;

        // Pre-fetch which registrations already have a certificate in one query,
        // instead of a McqCertificate::where(...)->exists() call per registration in
        // the loop below — issue() itself already runs the equivalent check via
        // first() right before creating, so the per-registration exists() here was a
        // second, redundant query on top of that. See
        // docs/N1_AND_REPORT_MEMORY_AUDIT_2026_08_03.md §8.
        $existingRegistrationIds = McqCertificate::whereHas(
            'registration',
            fn ($q) => $q->where('exam_id', $exam->id),
        )->pluck('registration_id')->flip();

        McqRegistration::where('exam_id', $exam->id)
            ->where('status', 'submitted')
            ->whereNotIn('attendance_status', McqRegistration::BLOCKING_ATTENDANCE_STATUSES)
            ->whereHas('mark')
            ->with(['exam', 'mark', 'student', 'teacher', 'school'])
            ->chunkById(100, function ($regs) use (&$count, $existingRegistrationIds) {
                foreach ($regs as $registration) {
                    try {
                        $before = $existingRegistrationIds->has($registration->id);
                        $this->issue($registration);
                        if (! $before) {
                            $count++;
                        }
                    } catch (ValidationException) {
                        continue;
                    }
                }
            });

        return $count;
    }

    private function meetsEligibilityRules(McqRegistration $registration): bool
    {
        $exam = $registration->exam;
        $mark = $registration->mark;
        if (! $exam || ! $mark) {
            return false;
        }

        $settings = McqResultReleaseSettings::certificates($exam);

        return match ($settings['eligibility']) {
            McqResultReleaseSettings::CERT_ELIGIBILITY_PASS => $this->isPassing($exam, $mark),
            McqResultReleaseSettings::CERT_ELIGIBILITY_GRADE_BANDS => in_array(
                (string) ($mark->grade ?? ''),
                $settings['eligible_grades'],
                true,
            ),
            default => true,
        };
    }

    private function isPassing(McqExam $exam, $mark): bool
    {
        $passMark = (float) ($exam->pass_mark ?? 0);
        if ($passMark > 0) {
            return (float) ($mark->percentage ?? 0) >= $passMark;
        }

        $bands = app(McqGradeService::class)->bandsForExam($exam);
        $grade = (string) ($mark->grade ?? '');
        foreach ($bands as $band) {
            if (($band['label'] ?? '') === $grade) {
                return (bool) ($band['is_pass'] ?? true);
            }
        }

        // No pass mark / no matching band — treat as participation pass.
        return $grade !== '' && strtoupper($grade) !== 'F';
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
        $registration->loadMissing(['exam.series', 'student', 'teacher', 'school', 'mark']);
        $exam = $registration->exam;
        $mark = $registration->mark;

        return [
            'student_name'   => $registration->participantName(),
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
