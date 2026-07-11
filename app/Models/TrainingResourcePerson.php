<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingResourcePerson extends Model
{
    protected $table = 'training_resource_persons';

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'mobile',
        'designation',
        'bio',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(
            TrainingProgram::class,
            'training_program_resource_person',
            'resource_person_id',
            'program_id'
        )->withPivot(['honorarium', 'role'])->withTimestamps();
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class, 'resource_person_id');
    }
}
