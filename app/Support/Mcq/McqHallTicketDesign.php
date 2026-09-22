<?php

namespace App\Support\Mcq;

use App\Models\McqExam;
use App\Models\Tenant;
use App\Support\TenantStorage;

class McqHallTicketDesign
{
    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'organization_name' => 'SAHODAYA SCHOOLS COMPLEX',
            'sub_heading'       => 'CBSE Affiliated Schools Academic Council',
            'sub_title'         => 'Annual Inter-School MCQ Aptitude & Talent Search Examination',
            'header_title'      => 'Hall Ticket / Admit Card',
            'title_left'        => 'Hall Ticket — Theory',
            'footer_note'       => '',
            'disclaimer'        => 'This Hall Ticket is system-generated and validates entry for the specified class MCQ exam session. Any tampering, impersonation, or possession of unauthorized materials will result in immediate disqualification and disciplinary action.',
            'primary_color'     => '#1e3a8a',
            'accent_color'      => '#b91c1c',
            'layout'            => 'standard',
            'logo_path'         => null,
            'board_logo_path'   => null,
            'show_school'       => true,
            'show_reg_no'       => true,
            'show_roll_no'      => true,
            'show_photo'        => true,
            'show_qr'           => true,
            'show_signature'    => true,
            'show_candidate_sig'=> true,
            'show_invigilator_sig' => true,
            'show_controller_sig'  => true,
            'signatory_title'   => 'Controller of Examinations',
            'show_audit_bar'    => true,
            'show_center_box'   => true,
            'show_exam_schedule'=> true,
            'exam_mode_label'   => 'OMR Answer Sheet (Pen & Paper)',
            'report_before_minutes'    => 30,
            'gate_closure_after_minutes' => 0,
            'instructions_title'=> 'Important Instructions for MCQ / OMR Examination',
            'instructions'      => [
                'Candidates must report at the examination centre strictly at or before the Reporting Time. No candidate will be admitted into the examination hall after Gate Closure.',
                'Candidate must produce this printed Hall Ticket along with their official School Identity Card or valid Photo ID (Aadhaar / Passport).',
                'Use ONLY Blue or Black Ballpoint Pen to darken bubbles on the OMR Answer Sheet. Gel pens, ink pens, whitener, and pencils are strictly prohibited.',
                'Each question has four alternatives with only one correct response. Darken the circle completely (⚫). Partial filling, ticks, or multiple markings will receive zero marks.',
                'Verify that the Question Booklet Code and OMR Sheet Code match before beginning. Report any defect, misprint, or missing pages to the invigilator immediately.',
                'Mobile phones, smartwatches, digital calculators, bluetooth devices, electronic gadgets, rough paper, and bags are strictly prohibited in the exam hall.',
                'All rough calculations must be performed strictly in the designated space provided at the end of the Question Booklet and NOT on the OMR sheet.',
                'Candidates must sign the attendance roster in the presence of the room invigilator. Candidates are not permitted to leave before the exam concludes.'
            ],
        ];
    }

    /** @param  array<string, mixed>|null  $settings */
    public static function normalize(?array $settings): array
    {
        $settings = $settings ?? [];
        $defaults = self::defaults();

        $primary = self::normalizeColor($settings['primary_color'] ?? null, $defaults['primary_color']);
        $accent = self::normalizeColor($settings['accent_color'] ?? null, $defaults['accent_color']);
        $layout = in_array($settings['layout'] ?? 'standard', ['standard', 'compact'], true)
            ? ($settings['layout'] ?? 'standard')
            : 'standard';

        $reportBefore = (int) ($settings['report_before_minutes'] ?? $defaults['report_before_minutes']);
        $gateClosure = (int) ($settings['gate_closure_after_minutes'] ?? $defaults['gate_closure_after_minutes']);

        $instructions = $settings['instructions'] ?? $defaults['instructions'];
        if (! is_array($instructions) || empty($instructions)) {
            $instructions = $defaults['instructions'];
        }

        return [
            'organization_name' => trim((string) ($settings['organization_name'] ?? $defaults['organization_name'])) ?: $defaults['organization_name'],
            'sub_heading'       => trim((string) ($settings['sub_heading'] ?? $defaults['sub_heading'])),
            'sub_title'         => trim((string) ($settings['sub_title'] ?? $defaults['sub_title'])),
            'header_title'      => trim((string) ($settings['header_title'] ?? $defaults['header_title'])) ?: $defaults['header_title'],
            'title_left'        => trim((string) ($settings['title_left'] ?? $defaults['title_left'])) ?: $defaults['title_left'],
            'footer_note'       => trim((string) ($settings['footer_note'] ?? '')),
            'disclaimer'        => trim((string) ($settings['disclaimer'] ?? $defaults['disclaimer'])) ?: $defaults['disclaimer'],
            'show_reg_no'       => (bool) ($settings['show_reg_no'] ?? $defaults['show_reg_no']),
            'show_roll_no'      => (bool) ($settings['show_roll_no'] ?? $defaults['show_roll_no']),
            'show_school'       => (bool) ($settings['show_school'] ?? $defaults['show_school']),
            'primary_color'     => $primary,
            'accent_color'      => $accent,
            'layout'            => $layout,
            'logo_path'         => filled($settings['logo_path'] ?? null) ? (string) $settings['logo_path'] : null,
            'board_logo_path'   => filled($settings['board_logo_path'] ?? null) ? (string) $settings['board_logo_path'] : null,
            'show_photo'        => (bool) ($settings['show_photo'] ?? $defaults['show_photo']),
            'show_qr'           => (bool) ($settings['show_qr'] ?? $defaults['show_qr']),
            'show_signature'    => (bool) ($settings['show_signature'] ?? $defaults['show_signature']),
            'show_candidate_sig'=> (bool) ($settings['show_candidate_sig'] ?? $defaults['show_candidate_sig']),
            'show_invigilator_sig' => (bool) ($settings['show_invigilator_sig'] ?? $defaults['show_invigilator_sig']),
            'show_controller_sig'  => (bool) ($settings['show_controller_sig'] ?? $defaults['show_controller_sig']),
            'signatory_title'   => trim((string) ($settings['signatory_title'] ?? $defaults['signatory_title'])) ?: $defaults['signatory_title'],
            'show_audit_bar'    => (bool) ($settings['show_audit_bar'] ?? $defaults['show_audit_bar']),
            'show_center_box'   => (bool) ($settings['show_center_box'] ?? $defaults['show_center_box']),
            'show_exam_schedule'=> (bool) ($settings['show_exam_schedule'] ?? $defaults['show_exam_schedule']),
            'exam_mode_label'   => trim((string) ($settings['exam_mode_label'] ?? $defaults['exam_mode_label'])) ?: $defaults['exam_mode_label'],
            'report_before_minutes' => $reportBefore >= 0 && $reportBefore <= 240 ? $reportBefore : $defaults['report_before_minutes'],
            'gate_closure_after_minutes' => $gateClosure >= 0 && $gateClosure <= 240 ? $gateClosure : $defaults['gate_closure_after_minutes'],
            'instructions_title'=> trim((string) ($settings['instructions_title'] ?? $defaults['instructions_title'])) ?: $defaults['instructions_title'],
            'instructions'      => array_values(array_filter(array_map('trim', $instructions))),
        ];
    }

    /**
     * "Report by" time label — exam start time minus the configured lead time — so candidates
     * know when to arrive, distinct from when the exam itself begins.
     */
    public static function reportTimeLabel(?\Carbon\CarbonInterface $scheduledAt, array $design): ?string
    {
        if (! $scheduledAt) {
            return null;
        }

        $minutes = (int) ($design['report_before_minutes'] ?? 30);

        return $scheduledAt->copy()->subMinutes($minutes)->format('d M Y, h:i A');
    }

    /** Hard cutoff after which latecomers are turned away — exam start time plus a grace period. */
    public static function gateClosureLabel(?\Carbon\CarbonInterface $scheduledAt, array $design): ?string
    {
        if (! $scheduledAt) {
            return null;
        }

        $minutes = (int) ($design['gate_closure_after_minutes'] ?? 0);

        return $scheduledAt->copy()->addMinutes($minutes)->format('h:i A');
    }

    /** "Start – End" range using the exam's configured duration, falling back to a start-only label. */
    public static function examTimingLabel(?\Carbon\CarbonInterface $scheduledAt, ?int $durationMinutes): ?string
    {
        if (! $scheduledAt) {
            return null;
        }

        if (! $durationMinutes) {
            return $scheduledAt->format('d M Y, h:i A');
        }

        $end = $scheduledAt->copy()->addMinutes($durationMinutes);
        $sameDay = $scheduledAt->isSameDay($end);

        return $sameDay
            ? $scheduledAt->format('d M Y, h:i A').' – '.$end->format('h:i A')
            : $scheduledAt->format('d M Y, h:i A').' – '.$end->format('d M Y, h:i A');
    }

    /**
     * Participant-agnostic identity fields for a hall ticket, so the template renders
     * correctly for both student and teacher registrations instead of assuming a student.
     *
     * @return array<string, mixed>
     */
    public static function participantFields(\App\Models\McqRegistration $registration): array
    {
        if ($registration->isTeacherRegistration()) {
            $teacher = $registration->teacher;

            return [
                'type'             => 'teacher',
                'name'             => $teacher?->name ?? $registration->participantName(),
                'secondary_label'  => 'Employee code',
                'secondary_value'  => $teacher?->employee_code ?: $teacher?->reg_no,
                'tertiary_label'   => 'Designation',
                'tertiary_value'   => $teacher?->designation,
                'class_name'       => 'Teacher / Faculty',
                'section_name'     => null,
                'father_name'      => null,
                'mother_name'      => null,
                'academic_year'    => null,
                'photo'            => $teacher?->photoDataUri(),
            ];
        }

        $student = $registration->student;

        return [
            'type'             => 'student',
            'name'             => $student?->name ?? $registration->participantName(),
            'secondary_label'  => 'School admission no.',
            'secondary_value'  => $student?->admission_number ?: $student?->reg_no,
            'tertiary_label'   => 'Class',
            'tertiary_value'   => $student?->schoolClass?->name,
            'class_name'       => $student?->schoolClass?->name,
            'section_name'     => null,
            'father_name'      => $student?->parent_name,
            'mother_name'      => null,
            'academic_year'    => $student?->academicYear?->name,
            'photo'            => $student?->photoDataUri(),
        ];
    }

    public static function fromExam(McqExam $exam): array
    {
        if ($exam->hall_ticket_template_id) {
            $template = \App\Models\McqHallTicketTemplate::find($exam->hall_ticket_template_id);
            if ($template?->design_json) {
                return self::normalize($template->design_json);
            }
        }

        $default = \App\Models\McqHallTicketTemplate::where('tenant_id', $exam->tenant_id)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
        if ($default?->design_json) {
            return self::normalize($default->design_json);
        }

        return self::normalize($exam->settings_json['hall_ticket'] ?? null);
    }

    public static function logoUrl(?Tenant $tenant, array $design): ?string
    {
        return TenantStorage::logoUrl($tenant, $design['logo_path'] ?? null);
    }

    public static function boardLogoUrl(?Tenant $tenant, array $design): ?string
    {
        return TenantStorage::logoUrl($tenant, $design['board_logo_path'] ?? null);
    }

    /** @param  array<string, mixed>  $settingsJson */
    public static function mergeIntoSettings(array $settingsJson, array $design): array
    {
        $settingsJson['hall_ticket'] = array_merge(
            $settingsJson['hall_ticket'] ?? [],
            self::normalize($design),
        );

        return $settingsJson;
    }

    /** @return array<string, mixed> */
    public static function previewSample(McqExam $exam): array
    {
        $design = self::fromExam($exam);
        $scheduledAt = $exam->scheduled_at;
        $qrService = app(\App\Services\Events\FestIdCardQrService::class);

        return [
            'participant_type'   => 'student',
            'participant_name'   => 'Sample Student',
            'student_name'       => 'Sample Student',
            'secondary_label'    => 'School admission no.',
            'secondary_value'    => 'ADM-2026-0842',
            'student_reg_no'     => 'ADM-2026-0842',
            'admission_no'       => 'ADM-2026-0842',
            'tertiary_label'     => 'Class',
            'tertiary_value'     => 'Class 10 A',
            'class_name'         => 'Class 10',
            'section_name'       => 'A',
            'father_name'        => 'Robert Fernandez',
            'mother_name'        => 'Mary Fernandez',
            'academic_year'      => '2025 - 2026',
            'school_name'        => 'St. Thomas Model Higher Secondary School',
            'hall_ticket_no'     => (string) ($exam->next_hall_ticket_no ?? 10001),
            'hall_room'          => 'Hall A - Room 102',
            'seat_no'            => '24',
            'venue'              => $exam->venue ?: 'Main Examination Center Campus',
            'center_code'        => 'CEN-' . str_pad((string) ($exam->id * 17 + 101), 4, '0', STR_PAD_LEFT),
            'center_name'        => $exam->venue ?: 'St. Thomas Central School Examination Centre',
            'center_address'     => 'Bypass Road, Alappuzha, Kerala - 688001',
            'scheduled_at_label' => self::examTimingLabel($scheduledAt, $exam->duration_minutes) ?? 'Saturday, 28 Oct 2026, 10:00 AM – 11:30 AM',
            'exam_date'          => $scheduledAt ? $scheduledAt->format('l, d F Y') : 'Saturday, 28 October 2026',
            'exam_time'          => self::examTimingLabel($scheduledAt, $exam->duration_minutes) ?? '10:00 AM – 11:30 AM',
            'report_time_label'  => self::reportTimeLabel($scheduledAt, $design) ?? '09:15 AM',
            'gate_closure_label' => self::gateClosureLabel($scheduledAt, $design) ?? '09:45 AM',
            'exam_title'         => $exam->title ?: 'Sahodaya Talent Search Examination',
            'paper_name'         => $exam->title ?: 'Class 10 MCQ Aptitude & Composite Assessment',
            'exam_code'          => $exam->code ?: 'MCQ-X-2026',
            'duration_minutes'   => $exam->duration_minutes ?: 90,
            'total_questions'    => $exam->total_questions ?: 60,
            'max_marks'          => $exam->total_questions ?: 60,
            'hall_instructions'  => $exam->hall_instructions,
            'generated_at'       => now()->format('d-m-Y H:i:s'),
            'security_ref'       => 'SEC-' . strtoupper(substr(md5((string) $exam->id), 0, 8)),
            'photo_src'          => null,
            'qr_src'             => $qrService->dataUri('MCQHT|SAMPLE|10001'),
        ];
    }

    /** @return array<string, mixed> Standalone preview sample when no exam instance is attached. */
    public static function previewSampleData(?array $design = null): array
    {
        $design = self::normalize($design);
        $qrService = app(\App\Services\Events\FestIdCardQrService::class);

        return [
            'participant_type'   => 'student',
            'participant_name'   => 'Sample Student',
            'student_name'       => 'Sample Student',
            'secondary_label'    => 'School admission no.',
            'secondary_value'    => 'ADM-2026-0842',
            'student_reg_no'     => 'ADM-2026-0842',
            'admission_no'       => 'ADM-2026-0842',
            'tertiary_label'     => 'Class',
            'tertiary_value'     => 'Class 10 A',
            'class_name'         => 'Class 10',
            'section_name'       => 'A',
            'father_name'        => 'Robert Fernandez',
            'mother_name'        => 'Mary Fernandez',
            'academic_year'      => '2025 - 2026',
            'school_name'        => 'St. Thomas Model Higher Secondary School',
            'hall_ticket_no'     => '10001',
            'hall_room'          => 'Hall A - Room 102',
            'seat_no'            => '24',
            'venue'              => 'St. Thomas Central School Examination Centre',
            'center_code'        => 'CEN-4201',
            'center_name'        => 'St. Thomas Central School Examination Centre',
            'center_address'     => 'Bypass Road, Alappuzha, Kerala - 688001',
            'scheduled_at_label' => 'Saturday, 28 Oct 2026, 10:00 AM – 11:30 AM',
            'exam_date'          => 'Saturday, 28 October 2026',
            'exam_time'          => '10:00 AM – 11:30 AM',
            'report_time_label'  => '09:15 AM',
            'gate_closure_label' => '09:45 AM',
            'exam_title'         => 'Sahodaya Talent Search MCQ Examination',
            'paper_name'         => 'Class 10 MCQ Aptitude & Composite Assessment',
            'exam_code'          => 'MCQ-X-2026',
            'duration_minutes'   => 90,
            'total_questions'    => 60,
            'max_marks'          => 60,
            'hall_instructions'  => null,
            'generated_at'       => now()->format('d-m-Y H:i:s'),
            'security_ref'       => 'SEC-DEMO789',
            'photo_src'          => null,
            'qr_src'             => $qrService->dataUri('MCQHT|SAMPLE|10001'),
        ];
    }

    private static function normalizeColor(?string $value, string $fallback): string
    {
        $value = trim((string) ($value ?? ''));

        return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) ? $value : $fallback;
    }
}
