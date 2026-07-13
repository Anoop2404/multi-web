<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateTemplate extends Model
{
    protected $fillable = [
        'tenant_id', 'event_type', 'certificate_type', 'title', 'body',
        'template_file_path', 'background_path', 'logo_path', 'seal_path', 'signatories',
        'dynamic_fields_json', 'layout_json', 'is_active',
    ];

    protected $casts = [
        'dynamic_fields_json' => 'array',
        'layout_json'         => 'array',
        'signatories'         => 'array',
        'is_active'           => 'boolean',
    ];

    /**
     * Default overlay positions (% of page) when a background image is used.
     * Tuned for landscape certificate designs like Malappuram Central Sahodaya.
     *
     * @return array{
     *     show_recipient_name: bool,
     *     show_participation_label: bool,
     *     bold_variables: bool,
     *     participation_label_cover: array{top: float, left: float, width: float, height: float},
     *     recipient_name: array{top: float, left: float, width: float, font_size: int},
     *     body: array{top: float, left: float, width: float, font_size: int},
     *     certificate_date: array{top: float, left: float, width: float, font_size: int, align?: string},
     *     uuid: array{top: float, left: float, width: float, font_size: int}
     * }
     */
    public static function defaultBackgroundLayout(): array
    {
        return [
            'show_recipient_name' => false,
            'show_participation_label' => true,
            'bold_variables' => true,
            'participation_label_cover' => [
                'top' => 28,
                'left' => 18,
                'width' => 64,
                'height' => 7,
            ],
            'recipient_name' => [
                'top' => 38,
                'left' => 10,
                'width' => 80,
                'font_size' => 28,
                'font_family' => 'Georgia',
                'font_weight' => 'bold',
                'font_style' => 'normal',
            ],
            'body' => [
                'top' => 48,
                'left' => 12,
                'width' => 76,
                'font_size' => 13,
                'font_family' => 'Times New Roman',
                'font_weight' => 'normal',
                'font_style' => 'normal',
            ],
            'certificate_date' => [
                'top' => 72,
                'left' => 8,
                'width' => 42,
                'font_size' => 12,
                'font_family' => 'Times New Roman',
                'font_weight' => 'normal',
                'font_style' => 'normal',
                'align' => 'left',
            ],
            'uuid' => [
                'top' => 92,
                'left' => 5,
                'width' => 90,
                'font_size' => 8,
                'font_family' => 'Arial',
                'font_weight' => 'normal',
                'font_style' => 'normal',
            ],
        ];
    }

    /** @return list<string> */
    public static function fontFamilyOptions(): array
    {
        return [
            'Times New Roman',
            'Georgia',
            'Arial',
            'Helvetica',
            'Verdana',
            'Courier New',
            'Palatino Linotype',
            'Garamond',
        ];
    }

    /**
     * Inline CSS for an overlay text block from layout_json field config.
     *
     * @param  array<string, mixed>  $field
     * @param  array{font_size?: int, font_family?: string, font_weight?: string, font_style?: string}  $fallback
     */
    public static function overlayFieldStyle(array $field, array $fallback = []): string
    {
        $size = (int) ($field['font_size'] ?? $fallback['font_size'] ?? 13);
        $size = max(6, min(96, $size));

        $family = (string) ($field['font_family'] ?? $fallback['font_family'] ?? 'Times New Roman');
        if (! in_array($family, self::fontFamilyOptions(), true)) {
            $family = 'Times New Roman';
        }
        $stack = match ($family) {
            'Georgia' => 'Georgia, "Times New Roman", Times, serif',
            'Arial' => 'Arial, Helvetica, sans-serif',
            'Helvetica' => 'Helvetica, Arial, sans-serif',
            'Verdana' => 'Verdana, Geneva, sans-serif',
            'Courier New' => '"Courier New", Courier, monospace',
            'Palatino Linotype' => '"Palatino Linotype", Palatino, "Book Antiqua", serif',
            'Garamond' => 'Garamond, "Times New Roman", Times, serif',
            default => '"Times New Roman", Times, serif',
        };

        $weight = ($field['font_weight'] ?? $fallback['font_weight'] ?? 'normal') === 'bold' ? '700' : '400';
        $style = ($field['font_style'] ?? $fallback['font_style'] ?? 'normal') === 'italic' ? 'italic' : 'normal';

        $parts = [
            'font-size:'.$size.'px',
            'font-family:'.$stack,
            'font-weight:'.$weight,
            'font-style:'.$style,
            'top:'.(float) ($field['top'] ?? $fallback['top'] ?? 0).'%',
            'left:'.(float) ($field['left'] ?? $fallback['left'] ?? 0).'%',
            'width:'.(float) ($field['width'] ?? $fallback['width'] ?? 80).'%',
        ];

        $align = $field['align'] ?? $fallback['align'] ?? null;
        if (in_array($align, ['left', 'right', 'center'], true)) {
            $parts[] = 'text-align:'.$align;
        }

        return implode(';', $parts).';';
    }

    public function usesBackground(): bool
    {
        return filled($this->background_path);
    }

    /** @return array<string, mixed> */
    public function overlayLayout(): array
    {
        $defaults = self::defaultBackgroundLayout();
        $custom = is_array($this->layout_json) ? $this->layout_json : [];

        foreach (['show_recipient_name', 'show_participation_label', 'bold_variables'] as $flag) {
            if (array_key_exists($flag, $custom)) {
                $defaults[$flag] = filter_var($custom[$flag], FILTER_VALIDATE_BOOLEAN);
            }
        }

        $textKeys = ['top', 'left', 'width', 'font_size', 'font_family', 'font_weight', 'font_style'];

        foreach (['recipient_name', 'body', 'certificate_date', 'uuid', 'participation_label_cover'] as $key) {
            if (! isset($custom[$key]) || ! is_array($custom[$key])) {
                continue;
            }
            $allowed = match ($key) {
                'participation_label_cover' => ['top', 'left', 'width', 'height'],
                'certificate_date' => [...$textKeys, 'align'],
                default => $textKeys,
            };
            $defaults[$key] = array_merge(
                $defaults[$key],
                array_intersect_key($custom[$key], array_flip($allowed)),
            );
        }

        return $defaults;
    }

    /** Default body text with placeholders for training certificates. */
    public static function defaultTrainingBody(): string
    {
        return <<<'BODY'
This is to certify that {salutation} {recipient_name}, {designation} of {school_name} has successfully participated in the {program_title} organized by {sahodaya_name} on {conducted_on} at {venue}.

The programme was designed to enhance professional competencies, strengthen pedagogical practices, and foster collaborative learning among educators. We appreciate the participant's active involvement and commitment to continuous professional growth and excellence in education.
BODY;
    }

    /** Default body for topper congratulations certificates. */
    public static function defaultTopperBody(): string
    {
        return <<<'BODY'
Congratulations! This is to certify that {recipient_name} of {school_name} has excelled in the CBSE {examination_type} (Class {class}) examination for the academic year {academic_year}, securing {percentage} (Rank {rank}).

We commend this outstanding academic achievement and wish continued success.
BODY;
    }

    /** @return list<array{name: string, designation: string, signature_path: ?string}> */
    public static function defaultTrainingSignatories(): array
    {
        return [
            ['name' => '', 'designation' => 'President', 'signature_path' => null],
            ['name' => '', 'designation' => 'General Secretary', 'signature_path' => null],
            ['name' => '', 'designation' => 'Finance Secretary', 'signature_path' => null],
            ['name' => '', 'designation' => 'Venue Director', 'signature_path' => null],
        ];
    }
}
