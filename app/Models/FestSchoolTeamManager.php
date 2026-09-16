<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestSchoolTeamManager extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'tenant_id',
        'event_id',
        'school_id',
        'manager_name_1',
        'manager_phone_1',
        'manager_email_1',
        'manager_role_1',
        'manager_name_2',
        'manager_phone_2',
        'manager_email_2',
        'manager_role_2',
        'notes',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'school_id');
    }
}
