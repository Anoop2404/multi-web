<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FestCertificatePhase extends Model
{
    protected $fillable = ['event_id', 'name', 'venue', 'date_start', 'date_end'];

    protected $casts = ['date_start' => 'date:Y-m-d', 'date_end' => 'date:Y-m-d'];

    public function conductDetails(): array
    {
        return [
            'venue' => $this->venue,
            'date_start' => $this->date_start?->format('Y-m-d'),
            'date_end' => $this->date_end?->format('Y-m-d'),
        ];
    }
}
