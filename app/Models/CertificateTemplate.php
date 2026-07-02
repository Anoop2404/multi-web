<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateTemplate extends Model
{
    protected $fillable = ["tenant_id", "event_type", "certificate_type", "template_file_path", "dynamic_fields_json"];

    protected $casts = [
        'dynamic_fields_json' => 'array',
    ];
}
