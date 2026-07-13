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
            ],
            'body' => [
                'top' => 48,
                'left' => 12,
                'width' => 76,
                'font_size' => 13,
            ],
            'certificate_date' => [
                'top' => 72,
                'left' => 8,
                'width' => 42,
                'font_size' => 12,
                'align' => 'left',
            ],
            'uuid' => [
                'top' => 92,
                'left' => 5,
                'width' => 90,
                'font_size' => 8,
            ],
        ];
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

        foreach (['recipient_name', 'body', 'certificate_date', 'uuid', 'participation_label_cover'] as $key) {
            if (! isset($custom[$key]) || ! is_array($custom[$key])) {
                continue;
            }
            $allowed = match ($key) {
                'participation_label_cover' => ['top', 'left', 'width', 'height'],
                'certificate_date' => ['top', 'left', 'width', 'font_size', 'align'],
                default => ['top', 'left', 'width', 'font_size'],
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
