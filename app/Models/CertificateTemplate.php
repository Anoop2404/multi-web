<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateTemplate extends Model
{
    protected $fillable = [
        'tenant_id', 'event_type', 'certificate_type', 'title', 'body',
        'template_file_path', 'logo_path', 'seal_path', 'signatories',
        'dynamic_fields_json', 'is_active',
    ];

    protected $casts = [
        'dynamic_fields_json' => 'array',
        'signatories'         => 'array',
        'is_active'           => 'boolean',
    ];

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
