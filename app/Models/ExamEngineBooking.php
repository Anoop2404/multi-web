<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/** Platform capacity booked by one online engine exam (central DB). */
class ExamEngineBooking extends Model
{
    use CentralConnection;

    public const ACTIVE_STATUSES = ['booked', 'armed'];

    protected $fillable = [
        'tenant_id', 'exam_id', 'title', 'live_starts_at', 'live_ends_at',
        'expected_count', 'entry_mode', 'status',
    ];

    protected $casts = [
        'live_starts_at' => 'datetime',
        'live_ends_at'   => 'datetime',
        'expected_count' => 'integer',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
