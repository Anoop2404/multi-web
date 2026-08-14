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
            'header_title'  => 'Talent Search Examination — Hall Ticket',
            'footer_note'   => '',
            'show_reg_no'   => true,
            'show_school'   => true,
            'primary_color' => '#1e3a8a',
            'accent_color'  => '#dc2626',
            'layout'        => 'standard',
            'logo_path'     => null,
            'show_photo'               => true,
            'show_qr'                  => true,
            'show_signature'           => true,
            'report_before_minutes'    => 30,
            'gate_closure_after_minutes' => 0,
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

        return [
            'header_title'  => trim((string) ($settings['header_title'] ?? $defaults['header_title'])) ?: $defaults['header_title'],
            'footer_note'   => trim((string) ($settings['footer_note'] ?? '')),
            'show_reg_no'   => (bool) ($settings['show_reg_no'] ?? $defaults['show_reg_no']),
            'show_school'   => (bool) ($settings['show_school'] ?? $defaults['show_school']),
            'primary_color' => $primary,
            'accent_color'  => $accent,
            'layout'        => $layout,
            'logo_path'     => filled($settings['logo_path'] ?? null) ? (string) $settings['logo_path'] : null,
            'show_photo'            => (bool) ($settings['show_photo'] ?? $defaults['show_photo']),
            'show_qr'               => (bool) ($settings['show_qr'] ?? $defaults['show_qr']),
            'show_signature'        => (bool) ($settings['show_signature'] ?? $defaults['show_signature']),
            'report_before_minutes' => $reportBefore >= 0 && $reportBefore <= 240 ? $reportBefore : $defaults['report_before_minutes'],
            'gate_closure_after_minutes' => $gateClosure >= 0 && $gateClosure <= 240 ? $gateClosure : $defaults['gate_closure_after_minutes'],
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
     * @return array{type: string, name: string, secondary_label: string, secondary_value: ?string, photo: ?string}
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

        return [
            'participant_type'   => 'student',
            'participant_name'   => 'Sample Student',
            'student_name'       => 'Sample Student',
            'secondary_label'    => 'School admission no.',
            'secondary_value'    => 'ADM-2026-001',
            'student_reg_no'     => 'ADM-2026-001',
            'tertiary_label'     => 'Class',
            'tertiary_value'     => 'Class 10 A',
            'school_name'        => 'Sample Model School',
            'hall_ticket_no'     => (string) ($exam->next_hall_ticket_no ?? 100),
            'hall_room'          => 'Hall A',
            'seat_no'            => '24',
            'venue'              => $exam->venue,
            'scheduled_at_label' => self::examTimingLabel($exam->scheduled_at, $exam->duration_minutes) ?? 'TBA',
            'report_time_label'  => self::reportTimeLabel($exam->scheduled_at, $design),
            'gate_closure_label' => self::gateClosureLabel($exam->scheduled_at, $design),
            'exam_title'         => $exam->title,
            'hall_instructions'  => $exam->hall_instructions,
            'photo_src'          => null,
            'qr_src'             => app(\App\Services\Events\FestIdCardQrService::class)->dataUri('SAMPLE-HALL-TICKET'),
        ];
    }

    private static function normalizeColor(?string $value, string $fallback): string
    {
        $value = trim((string) ($value ?? ''));

        return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) ? $value : $fallback;
    }
}
