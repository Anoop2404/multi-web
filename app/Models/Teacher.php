<?php

namespace App\Models;

use App\Support\TenantStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Teacher extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'academic_year_id', 'reg_no', 'name', 'email', 'photo',
        'designation', 'subject', 'teaching_type_id', 'status',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYearRecord::class, 'academic_year_id');
    }

    public function teachingType(): BelongsTo
    {
        return $this->belongsTo(TeachingType::class);
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function photoUrl(): ?string
    {
        if (! $this->photo) {
            return null;
        }

        if (str_starts_with($this->photo, 'http://') || str_starts_with($this->photo, 'https://')) {
            return $this->photo;
        }

        if (! $this->tenant_id) {
            return null;
        }

        return route('school.teachers.photo', [
            'tenantId' => $this->tenant_id,
            'teacher'  => $this->id,
        ], absolute: false);
    }

    public function photoDataUri(): ?string
    {
        if (! $this->photo || ! $this->tenant_id) {
            return null;
        }

        $tenant = $this->relationLoaded('tenant') ? $this->tenant : Tenant::find($this->tenant_id);

        return TenantStorage::photoDataUri($tenant, $this->photo);
    }
}
