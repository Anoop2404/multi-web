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
            'header_title'  => 'MCQ Examination — Hall Ticket',
            'footer_note'   => '',
            'show_reg_no'   => true,
            'show_school'   => true,
            'primary_color' => '#1e3a8a',
            'accent_color'  => '#dc2626',
            'layout'        => 'standard',
            'logo_path'     => null,
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

        return [
            'header_title'  => trim((string) ($settings['header_title'] ?? $defaults['header_title'])) ?: $defaults['header_title'],
            'footer_note'   => trim((string) ($settings['footer_note'] ?? '')),
            'show_reg_no'   => (bool) ($settings['show_reg_no'] ?? $defaults['show_reg_no']),
            'show_school'   => (bool) ($settings['show_school'] ?? $defaults['show_school']),
            'primary_color' => $primary,
            'accent_color'  => $accent,
            'layout'        => $layout,
            'logo_path'     => filled($settings['logo_path'] ?? null) ? (string) $settings['logo_path'] : null,
        ];
    }

    public static function fromExam(McqExam $exam): array
    {
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
        return [
            'student_name'       => 'Sample Student',
            'student_reg_no'     => 'ADM-2026-001',
            'school_name'        => 'Sample Model School',
            'hall_ticket_no'     => (string) ($exam->next_hall_ticket_no ?? 100),
            'hall_room'          => 'Hall A',
            'seat_no'            => '12',
            'scheduled_at_label' => $exam->scheduled_at?->format('d M Y, h:i A') ?? 'TBA',
            'exam_title'         => $exam->title,
            'hall_instructions'  => $exam->hall_instructions,
        ];
    }

    private static function normalizeColor(?string $value, string $fallback): string
    {
        $value = trim((string) ($value ?? ''));

        return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) ? $value : $fallback;
    }
}
