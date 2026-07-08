<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McqCertificate extends Model
{
    protected $fillable = [
        'registration_id', 'certificate_template_id', 'design_snapshot_json', 'file_path',
        'verification_uuid', 'generated_at',
    ];

    protected $casts = [
        'design_snapshot_json' => 'array',
        'generated_at'         => 'datetime',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(McqRegistration::class, 'registration_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(McqCertificateTemplate::class, 'certificate_template_id');
    }
}
