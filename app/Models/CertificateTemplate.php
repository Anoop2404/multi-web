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
            // Deliberately no positional defaults — there is no admin UI to configure
            // this box's position (only the on/off toggle exists), so seeding a guessed
            // top/left/width/height here meant every template that ever unchecked "Show
            // participation label" painted a same-shaped cream patch at that fixed spot
            // regardless of whether the background art has anything there to cover. Left
            // empty, certificate-body.blade.php skips rendering the cover entirely unless
            // a real position has actually been set (e.g. a future UI, or a direct
            // layout_json edit) — see the `!empty($c)` guard there.
            'participation_label_cover' => [],
            // Null (both) means the historical default: stretch the artwork to fill the
            // whole A4 canvas (`background-size: 100% 100%` in certificate-print.blade.php),
            // which is exact for a background already cut to true A4 proportions but
            // distorts anything else. Setting both lets the admin declare the artwork's
            // real physical size so it renders true-to-scale, centered on the A4 page
            // (letterboxed if smaller, cropped if larger) — see
            // CertificateTemplate::backgroundSizePercentages().
            'background' => [
                'width_mm' => null,
                'height_mm' => null,
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
            // Independently-positioned text fields beyond the fixed recipient_name/body/
            // certificate_date trio — for a background whose own artwork already lays
            // out several separate blanks (e.g. "Master/Miss ___ of class ___", "from
            // ___", "who won ___ place with ___ grade in ___" each on their own line),
            // where one flowing body paragraph can't land each value on its own
            // pre-printed line. Each entry: {text, top, left, width, font_size,
            // font_family, font_weight, font_style, align} — `text` runs through the
            // same {token} substitution as `body` (see substituteTokens()).
            'custom_fields' => [],
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

    /**
     * Substitutes every {token} in $text with its resolved value from $fieldValues —
     * shared by the single `body` paragraph and each independently-positioned
     * `custom_fields` entry (see certificate-body.blade.php), so both go through
     * identical escaping/bold-wrapping/special-casing instead of two copies drifting
     * apart. Extracted from certificate-body.blade.php's original inline loop.
     *
     * @param  array<string, mixed>  $fieldValues
     */
    public static function substituteTokens(string $text, array $fieldValues, bool $boldVariables): string
    {
        $itemTitlesList = $fieldValues['item_titles'] ?? [];

        foreach ($fieldValues as $key => $value) {
            if (is_array($value)) {
                continue;
            }
            // Already-rendered, self-contained HTML (FestCertificateService::
            // participationItemsBoxHtml()) — never escaped or bold-wrapped like a plain
            // text token.
            if ($key === 'participation_items_box') {
                $text = str_replace('{'.$key.'}', $value, $text);
                continue;
            }
            // certificate_date carries a real <sup> tag around its ordinal suffix —
            // server-built, never user input, so skipping escaping here is safe and
            // lets the tag actually render instead of showing as literal text.
            $safe = $key === 'certificate_date' ? (string) $value : e((string) $value);
            if ($boldVariables && $safe !== '') {
                $safe = '<strong>'.$safe.'</strong>';
            }
            if (($key === 'item_title' || $key === 'item_details') && count($itemTitlesList) > 3) {
                $safe = '<span class="cert-item-list" data-items-json="'.e(json_encode($itemTitlesList)).'">'.$safe.'</span>';
            }
            $text = str_replace('{'.$key.'}', $safe, $text);
        }

        return $text;
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

        foreach (['recipient_name', 'body', 'certificate_date', 'uuid', 'participation_label_cover', 'photo', 'background'] as $key) {
            if (! isset($custom[$key]) || ! is_array($custom[$key])) {
                continue;
            }
            $allowed = match ($key) {
                'participation_label_cover' => ['top', 'left', 'width', 'height'],
                'photo' => ['top', 'left', 'size'],
                'background' => ['width_mm', 'height_mm'],
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

        if (isset($custom['custom_fields']) && is_array($custom['custom_fields'])) {
            $allowed = array_merge($textKeys, ['text']);
            $defaults['custom_fields'] = collect($custom['custom_fields'])
                ->filter(fn ($field) => is_array($field))
                ->map(fn ($field) => array_intersect_key($field, array_flip($allowed)))
                ->values()
                ->all();
        }

        return $defaults;
    }

    /**
     * CSS `background-size` width/height percentages for artwork declared at its true
     * physical size (see `background.width_mm`/`height_mm` in overlayLayout()), rather
     * than the historical `100% 100%` full-canvas stretch. A percentage — not a fixed
     * px/mm value — because it renders identically whether the container is the admin
     * preview's pixel canvas or the print page's real mm dimensions, both of which are
     * already true A4 proportions. Returns null (caller keeps the 100% 100% stretch
     * default) unless both dimensions are actually set.
     *
     * @return array{width: float, height: float}|null
     */
    public static function backgroundSizePercentages(array $layout, string $orientation): ?array
    {
        $widthMm = $layout['background']['width_mm'] ?? null;
        $heightMm = $layout['background']['height_mm'] ?? null;
        if (! is_numeric($widthMm) || ! is_numeric($heightMm) || $widthMm <= 0 || $heightMm <= 0) {
            return null;
        }

        [$pageWidthMm, $pageHeightMm] = $orientation === 'portrait' ? [210, 297] : [297, 210];

        return [
            'width' => round(($widthMm / $pageWidthMm) * 100, 2),
            'height' => round(($heightMm / $pageHeightMm) * 100, 2),
        ];
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
