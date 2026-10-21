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
            'background_path'   => null,
            'card_width_mm' => 90,
            'card_height_mm' => 121.384615,
            'page_orientation' => 'landscape',
            'card_gap_mm' => 3,
            'page_margin_mm' => 10,
            'background_fit' => 'contain',
            'background_aspect_ratio' => 780 / 1052,
            'show_student_photo' => false,
            'custom_fields' => [],
            'remarks_text' => '',
            'remarks_height_mm' => 35,
            'remarks_format' => 'paragraph',
            'remarks_title' => 'Remarks / Instructions',
            'remarks_font_size' => 11,
            'remarks_theme_color' => '#101b6f',
            'remarks_paper_color' => '#ffffff',
            'remarks_text_color' => '#111111',
            'remarks_text_style' => [],
            'field_positions' => [
                'registration' => ['x' => 41, 'y' => 48, 'size' => 11, 'width' => 52],
                'name' => ['x' => 41, 'y' => 54, 'size' => 11, 'width' => 52],
                'class' => ['x' => 24, 'y' => 66, 'size' => 11, 'width' => 68],
                'date' => ['x' => 49, 'y' => 71.5, 'size' => 10, 'width' => 44],
                'centre' => ['x' => 37, 'y' => 76, 'size' => 10, 'width' => 56],
                'principal_signature' => ['x' => 10, 'y' => 81.7, 'size' => 11, 'width' => 30],
                'student_photo' => ['x' => 75, 'y' => 31, 'size' => 11, 'width' => 16, 'height' => 15],
            ],
            'logo_path'         => null,
            'board_logo_path'   => null,
            'show_school'       => true,
            'show_exam_passcode'=> true,
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
        $layout = in_array($settings['layout'] ?? 'standard', ['standard', 'compact', 'stse_three_up', 'custom_background'], true)
            ? ($settings['layout'] ?? 'standard')
            : 'standard';

        $reportBefore = (int) ($settings['report_before_minutes'] ?? $defaults['report_before_minutes']);
        $gateClosure = (int) ($settings['gate_closure_after_minutes'] ?? $defaults['gate_closure_after_minutes']);

        $instructions = $settings['instructions'] ?? $defaults['instructions'];
        if (! is_array($instructions) || empty($instructions)) {
            $instructions = $defaults['instructions'];
        }

        $positions = $defaults['field_positions'];
        foreach ($positions as $field => &$position) {
            foreach ($position as $axis => &$value) {
                $candidate = $settings['field_positions'][$field][$axis] ?? $value;
                $value = is_numeric($candidate)
                    ? max($axis === 'size' ? 6 : 0, min($axis === 'size' ? 72 : 95, (float) $candidate))
                    : $value;
            }
            unset($value);
            $position['width'] = min(max(1, $position['width']), 100 - $position['x']);
            if (isset($position['height'])) $position['height'] = min(max(1, $position['height']), 100 - $position['y']);
            $position = array_merge($position, self::textStyle($settings['field_positions'][$field] ?? []));
        }
        unset($position);

        $customFields = [];
        $sources = ['text', 'school_name', 'hall_room', 'seat_no', 'exam_title', 'admission_no', 'academic_year'];
        foreach (array_slice(is_array($settings['custom_fields'] ?? null) ? $settings['custom_fields'] : [], 0, 20) as $field) {
            if (!is_array($field) || !is_string($field['id'] ?? null) || !preg_match('/^custom_[a-zA-Z0-9_-]{1,64}$/', $field['id'] ?? '')) {
                continue;
            }
            $number = fn (string $key, float $fallback): float => is_numeric($field[$key] ?? null) ? (float) $field[$key] : $fallback;
            $x = max(0, min(95, $number('x', 10)));
            $customFields[$field['id']] = [
                'id' => $field['id'],
                'label' => mb_substr(trim((is_scalar($field['label'] ?? null) ? (string) $field['label'] : 'Custom field')), 0, 80),
                'source' => in_array($field['source'] ?? '', $sources, true) ? $field['source'] : 'text',
                'text' => mb_substr((is_scalar($field['text'] ?? null) ? (string) $field['text'] : ''), 0, 500),
                'x' => $x, 'y' => max(0, min(95, $number('y', 35))),
                'width' => max(1, min(100 - $x, $number('width', 60))),
                'size' => max(6, min(72, $number('size', 10))),
                ...self::textStyle($field),
            ];
        }

        return [
            'field_positions' => $positions,
            'show_student_photo' => (bool) ($settings['show_student_photo'] ?? false),
            'custom_fields' => array_values($customFields),
            'remarks_text' => mb_substr(trim((string) ($settings['remarks_text'] ?? '')), 0, 3000),
            'remarks_format' => in_array($settings['remarks_format'] ?? '', ['paragraph','numbered','bullets'], true) ? $settings['remarks_format'] : 'paragraph',
            'remarks_title' => mb_substr((string) ($settings['remarks_title'] ?? 'Remarks / Instructions'), 0, 100),
            'remarks_font_size' => max(8, min(18, (float) ($settings['remarks_font_size'] ?? 11))),
            'remarks_theme_color' => self::normalizeColor($settings['remarks_theme_color'] ?? null, '#101b6f'),
            'remarks_paper_color' => self::normalizeColor($settings['remarks_paper_color'] ?? null, '#ffffff'),
            'remarks_text_color' => self::normalizeColor($settings['remarks_text_color'] ?? null, '#111111'),
            'remarks_text_style' => self::textStyle(array_merge(['line_height'=>1.4], is_array($settings['remarks_text_style'] ?? null) ? $settings['remarks_text_style'] : [])),
            'remarks_height_mm' => max(15, min(257, (float) ($settings['remarks_height_mm'] ?? 35))),
            'card_width_mm' => max(20, min(277, (float) ($settings['card_width_mm'] ?? 90))),
            'card_height_mm' => max(20, min(277, (float) ($settings['card_height_mm'] ?? 121.384615))),
            'page_margin_mm' => max(0, min(20, (float) ($settings['page_margin_mm'] ?? 10))),
            'page_orientation' => ($settings['page_orientation'] ?? 'landscape') === 'portrait' ? 'portrait' : 'landscape',
            'card_gap_mm' => max(0, min(15, (float) ($settings['card_gap_mm'] ?? 3))),
            'background_fit' => ($settings['background_fit'] ?? 'contain') === 'stretch' ? 'stretch' : 'contain',
            'background_aspect_ratio' => max(0.1, min(10, (float) ($settings['background_aspect_ratio'] ?? (780 / 1052)))),
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
            'show_exam_passcode'=> (bool) ($settings['show_exam_passcode'] ?? $defaults['show_exam_passcode']),
            'primary_color'     => $primary,
            'accent_color'      => $accent,
            'layout'            => $layout,
            'background_path'   => filled($settings['background_path'] ?? null) ? (string) $settings['background_path'] : null,
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

    public static function textStyle(array $field): array
    {
        $families = ['Arial', 'Times New Roman', 'Georgia', 'Verdana', 'Tahoma', 'Courier New'];
        return [
            'font_family' => in_array($field['font_family'] ?? '', $families, true) ? $field['font_family'] : 'Arial',
            'font_weight' => ($field['font_weight'] ?? '') === 'bold' ? 'bold' : 'normal',
            'font_style' => ($field['font_style'] ?? '') === 'italic' ? 'italic' : 'normal',
            'text_decoration' => ($field['text_decoration'] ?? '') === 'underline' ? 'underline' : 'none',
            'text_color' => self::normalizeColor(is_string($field['text_color'] ?? null) ? $field['text_color'] : null, '#111111'),
            'line_height' => max(1, min(2.5, is_numeric($field['line_height'] ?? null) ? (float) $field['line_height'] : 1.25)),
            'letter_spacing' => max(-1, min(5, is_numeric($field['letter_spacing'] ?? null) ? (float) $field['letter_spacing'] : 0)),
            'layer' => max(1, min(50, (int) ($field['layer'] ?? 2))),
            'text_align' => in_array($field['text_align'] ?? '', ['left','center','right','justify'], true) ? $field['text_align'] : 'left',
        ];
    }

    public static function customFieldValue(array $field, array $sample): string
    {
        if (($field['source'] ?? 'text') === 'text') {
            return (string) ($field['text'] ?? '');
        }
        $value = $sample[$field['source']] ?? '';
        return trim(($field['label'] ?? '') . (filled($field['label'] ?? '') ? ': ' : '') . $value);
    }

    /** Derive A4 capacity from the selected die and page margins. */
    public static function printGeometry(array $design): array
    {
        $landscape = ($design['page_orientation'] ?? 'landscape') === 'landscape';
        $margin = max(0, min(20, (float) ($design['page_margin_mm'] ?? 10)));
        $availableWidth = ($landscape ? 297 : 210) - 2 * $margin;
        $availableHeight = ($landscape ? 210 : 297) - 2 * $margin;
        $width = min($availableWidth, max(20, (float) ($design['card_width_mm'] ?? 90)));
        $height = min($availableHeight, max(20, (float) ($design['card_height_mm'] ?? 121.384615)));
        $gap = max(0, min(15, (float) ($design['card_gap_mm'] ?? 3)));
        $columns = max(1, (int) floor(($availableWidth + $gap + 0.00001) / ($width + $gap)));
        $rows = max(1, (int) floor(($availableHeight + $gap + 0.00001) / ($height + $gap)));

        return ['margin' => $margin, 'width' => $width, 'height' => $height, 'gap' => $gap, 'columns' => $columns, 'rows' => $rows, 'per_page' => $columns * $rows];
    }

    /** One non-empty line is one rule; list numbering is supplied by the renderer. */
    public static function instructionPoints(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: []), fn ($line) => $line !== ''));
    }

    public static function remarksHeight(array $design): float
    {
        if (trim($design['remarks_text'] ?? '') === '') {
            return 0;
        }
        return min(max(15, (float) ($design['remarks_height_mm'] ?? 35)), self::printGeometry($design)['height'] - 20);
    }

    /** Extend the artwork's own paper edges, keeping instructions inside the same frame. */
    public static function instructionBox(array $design): array
    {
        $die = self::printGeometry($design);
        $stse = ($design['layout'] ?? '') === 'stse_three_up';
        $inset = $stse ? 30 / 780 : 0;
        $bottom = $stse ? $die['height'] * 28 / 1052 : 3;
        $top = $stse ? $die['height'] * 20 / 1052 : 0;
        return [
            'frame_x' => 0, 'frame_y' => 0, 'frame_width' => $die['width'], 'frame_height' => $die['height'],
            'paper_x' => $die['width'] * $inset, 'paper_y' => $top,
            'paper_width' => $die['width'] * ($stse ? 712 / 780 : 1), 'paper_height' => max(0, $die['height'] - $top - $bottom),
            'x' => $die['width'] * 0.10, 'width' => $die['width'] * 0.80,
            'bottom' => $bottom, 'height' => max(0, self::remarksHeight($design) - $bottom),
        ];
    }

    /** Artwork bounds inside a die; text uses the same coordinate space as its labels. */
    public static function artworkBox(array $design): array
    {
        $geometry = self::printGeometry($design);
        $width = $geometry['width'];
        $height = $geometry['height'] - self::remarksHeight($design);
        // The supplied STSE bitmap includes a grey outer frame. In borderless fill
        // mode enlarge the entire artwork coordinate space, including its fields,
        // so only the white card (30,20)-(742,1024) occupies the die.
        if (($design['layout'] ?? '') === 'stse_three_up'
            && ($design['background_fit'] ?? '') === 'stretch'
            && $geometry['margin'] == 0 && self::remarksHeight($design) == 0) {
            return ['x' => -$width * 30 / 712, 'y' => -$height * 20 / 1004,
                'width' => $width * 780 / 712, 'height' => $height * 1052 / 1004];
        }
        if (($design['background_fit'] ?? 'contain') === 'stretch') {
            return ['x' => 0, 'y' => 0, 'width' => $width, 'height' => $height];
        }
        $aspect = ($design['layout'] ?? '') === 'stse_three_up' ? 780 / 1052 : ($design['background_aspect_ratio'] ?? 780 / 1052);
        $artWidth = min($width, $height * $aspect);
        $artHeight = $artWidth / $aspect;

        return ['x' => ($width - $artWidth) / 2, 'y' => ($height - $artHeight) / 2, 'width' => $artWidth, 'height' => $artHeight];
    }

    public static function schoolCentreSettingKey(McqExam $exam): string
    {
        return 'mcq_exam_centre_'.$exam->id;
    }

    /** A school-specific centre overrides the Sahodaya's exam-wide venue. */
    public static function centreForSchool(McqExam $exam, ?Tenant $school): ?string
    {
        $centre = $school?->getSetting(self::schoolCentreSettingKey($exam));
        if (is_string($centre) && trim($centre) !== '') {
            return trim($centre);
        }

        return filled($exam->venue) ? $exam->venue : null;
    }

    public static function fromExam(McqExam $exam): array
    {
        if (($exam->settings_json['hall_ticket_design_scope'] ?? null) === 'exam') {
            return self::normalize($exam->settings_json['hall_ticket'] ?? null);
        }

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
            'is_preview' => true,
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
            'exam_passcode'      => $exam->isOnlineDelivery() ? 'K7M2P-Q9RTX' : null,
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
            'is_preview' => true,
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
