<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SahodayaRegistrationWindow extends Model
{
    protected $fillable = [
        'sahodaya_id', 'academic_year', 'registration_starts_at', 'registration_ends_at',
    ];

    protected $casts = [
        'registration_starts_at' => 'date',
        'registration_ends_at'   => 'date',
    ];

    public function sahodaya() { return $this->belongsTo(Tenant::class, 'sahodaya_id'); }
}
