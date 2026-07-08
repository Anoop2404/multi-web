<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;

class SahodayaRegistrationWindow extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'sahodaya_id', 'academic_year', 'academic_year_id',
        'registration_starts_at', 'registration_ends_at',
        'add_open', 'add_close', 'edit_open', 'edit_close',
    ];

    protected $casts = [
        'registration_starts_at' => 'date',
        'registration_ends_at'   => 'date',
        'add_open'               => 'datetime',
        'add_close'              => 'datetime',
        'edit_open'              => 'datetime',
        'edit_close'             => 'datetime',
    ];

    public function sahodaya() { return $this->belongsToCentralTenant('sahodaya_id'); }
    public function academicYearRecord() { return $this->belongsTo(AcademicYearRecord::class, 'academic_year_id'); }
}
