<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateTemplate extends Model
{
    protected $fillable = [
        'tenant_id', 'event_type', 'event_id', 'item_id', 'certificate_type', 'title', 'body',
        'template_file_path', 'background_path', 'logo_path', 'seal_path', 'signatories',
        'dynamic_fields_json', 'layout_json', 'is_active',
    ];

    protected $casts = [
        'dynamic_fields_json' => 'array',
        'layout_json'         => 'array',
        'signatories'         => 'array',
        'is_active'           => 'boolean',
    ];

    public function event()
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function item()
    {
        return $this->belongsTo(FestEventItem::class, 'item_id');
    }

    /**
     * Default overlay positions (% of page) when a background image is used.
     * Tuned for landscape certificate designs like Malappuram Central Sahodaya.
     *
     * @return array{
     *     orientation: string,
     *     show_recipient_name: bool,
     *     show_participation_label: bool,
     *     bold_variables: bool,
     *     show_logo_overlay: bool,
     *     show_qr: bool,
     *     show_photo: bool,
     *     photo: array{top: float, left: float, size: float},
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
            'orientation' => 'landscape',
            'show_recipient_name' => false,
            'show_participation_label' => true,
            'bold_variables' => true,
            'show_certificate_date' => true,
            'show_logo_overlay' => true,
            'show_qr' => true,
            // Off by default — no existing template has ever reserved space for a photo,
            // so a freshly-added element shouldn't suddenly appear on any of them.
            'show_photo' => false,
            'photo' => [
                'top' => 31,
                'left' => 50,
                'size' => 118,
            ],
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
                'font_size' => 24,
                'font_family' => 'Montserrat',
                'font_weight' => 'bold',
                'font_style' => 'normal',
            ],
            'body' => [
                'top' => 48,
                'left' => 12,
                'width' => 76,
                'font_size' => 12.5,
                'font_family' => 'Montserrat',
                'font_weight' => 'normal',
                'font_style' => 'normal',
            ],
            'certificate_date' => [
                'top' => 72,
                'left' => 8,
                'width' => 42,
                'font_size' => 12,
                'font_family' => 'Montserrat',
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
            'Montserrat',
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
            'Montserrat' => 'Montserrat, Arial, sans-serif',
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

        // Optional lower boundary of the background artwork's fillable zone (e.g. where a
        // "Congratulations" graphic begins below the text). When set, top+bottom together
        // define a fixed-height box instead of a top-anchored one that grows downward
        // unboundedly: short content is centered inside it instead of leaving a visible
        // gap above the artwork, and certificate-fit-text-script.blade.php reads this same
        // box edge as the authoritative overflow boundary instead of guessing from
        // unrelated sibling fields (see fitPage()/computeAllowedBottom() there).
        // Deliberately NOT flex/justify-content:center here — content still flows from the
        // box's top edge exactly as it does without `bottom` set. Centering short content
        // within the zone is instead done as a post-pass in the fit-text script, after
        // shrink/truncate settles on a final height: measuring overflow against a flex-
        // centered box is unreliable (a flexbox can overflow symmetrically above *and*
        // below when centered content exceeds its height, and scrollHeight's accounting
        // for the above-the-box portion is inconsistent), whereas top-down flow keeps the
        // existing offsetTop/scrollHeight overflow check exactly as simple as it is today.
        $bottom = $field['bottom'] ?? $fallback['bottom'] ?? null;
        if ($bottom !== null && $bottom !== '') {
            $parts[] = 'bottom:'.(float) $bottom.'%';
        }

        $align = $field['align'] ?? $fallback['align'] ?? null;
        if (in_array($align, ['left', 'right', 'center', 'justify'], true)) {
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

        foreach (['show_recipient_name', 'show_participation_label', 'bold_variables', 'show_certificate_date', 'show_logo_overlay', 'show_qr', 'show_photo'] as $flag) {
            if (array_key_exists($flag, $custom)) {
                $defaults[$flag] = filter_var($custom[$flag], FILTER_VALIDATE_BOOLEAN);
            }
        }

        if (in_array($custom['orientation'] ?? null, ['landscape', 'portrait'], true)) {
            $defaults['orientation'] = $custom['orientation'];
        }

        $textKeys = ['top', 'left', 'width', 'font_size', 'font_family', 'font_weight', 'font_style', 'align'];

        foreach (['recipient_name', 'body', 'certificate_date', 'uuid', 'participation_label_cover', 'photo'] as $key) {
            if (! isset($custom[$key]) || ! is_array($custom[$key])) {
                continue;
            }
            $allowed = match ($key) {
                'participation_label_cover' => ['top', 'left', 'width', 'height'],
                'photo' => ['top', 'left', 'size'],
                // Only `body` grows with variable content (achievement text, the
                // participation items box) — `bottom` marks the artwork's fillable-zone
                // edge for that field alone (see overlayFieldStyle()).
                'body' => array_merge($textKeys, ['bottom']),
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
This is to certify that Mr./Ms. {recipient_name}, {designation} of {school_name} has successfully participated in the {program_title} organized by {sahodaya_name} on {conducted_on} at {venue}.

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

    /** Default body text with placeholders for fest event certificates. */
    public static function defaultFestBody(): string
    {
        return <<<'BODY'
This is to certify that {recipient_name} of {school_name} has {achievement_line} in {item_title}, {event_title} organized by {sahodaya_name} held on {event_dates}.

We appreciate the participant's talent and dedication and wish continued success in future endeavours.
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
