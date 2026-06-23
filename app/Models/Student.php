<?php

namespace App\Models;

use App\Support\TenantStorage;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'academic_year_id', 'school_class_id',
        'admission_number', 'reg_no', 'roll_number', 'name', 'email', 'dob', 'gender', 'blood_group',
        'parent_name', 'parent_phone', 'parent_email', 'address',
        'admission_date', 'status', 'photo', 'notes',
    ];

    protected $casts = [
        'dob'            => 'date',
        'admission_date' => 'date',
    ];

    public function tenant()       { return $this->belongsTo(Tenant::class); }
    public function schoolClass()  { return $this->belongsTo(SchoolClass::class); }
    public function academicYear() { return $this->belongsTo(AcademicYearRecord::class, 'academic_year_id'); }

    public function scopeActive($q) { return $q->where('status', 'active'); }

    public function getClassLabelAttribute(): string
    {
        return $this->schoolClass?->name ?? '';
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

        $proxyUrl = route('school.students.photo', [
            'tenantId' => $this->tenant_id,
            'student'  => $this->id,
        ]);

        $tenant = $this->relationLoaded('tenant') ? $this->tenant : null;

        return TenantStorage::assetUrl(
            $tenant ?? Tenant::query()->find($this->tenant_id),
            $this->photo,
            $proxyUrl,
        ) ?? $proxyUrl;
    }
}
