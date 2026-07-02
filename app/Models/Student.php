<?php

namespace App\Models;

use App\Support\TenantStorage;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'academic_year_id', 'school_class_id', 'school_house_id',
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
    public function schoolHouse()  { return $this->belongsTo(SchoolHouse::class, 'school_house_id'); }
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

        $tenant = $this->relationLoaded('tenant') ? $this->tenant : null;
        $tenant = $tenant ?? Tenant::query()->find($this->tenant_id);

        if (! $tenant) {
            return null;
        }

        $serveRoute = route('school.students.photo', [
            'tenantId' => $this->tenant_id,
            'student'  => $this->id,
        ], absolute: false);

        if (TenantStorage::isS3Configured()) {
            try {
                if (\Illuminate\Support\Facades\Storage::disk('s3')->exists($this->photo)) {
                    $fromStorage = TenantStorage::assetUrl($tenant, $this->photo);
                    if ($fromStorage) {
                        return $fromStorage;
                    }
                }
            } catch (\Throwable) {
                // S3 unreachable — fall back to app route below.
            }
        }

        // Local / tenant-suffixed storage is served through the app route, not /storage.
        return $serveRoute;
    }
}
