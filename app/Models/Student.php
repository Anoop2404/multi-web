<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use App\Support\TenantStorage;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'tenant_id', 'user_id', 'academic_year_id', 'school_class_id', 'school_house_id',
        'admission_number', 'reg_no', 'roll_number', 'name', 'email', 'dob', 'gender', 'blood_group',
        'parent_name', 'parent_phone', 'parent_email', 'address',
        'admission_date', 'status', 'photo', 'notes',
        'verified_at', 'verified_by_user_id',
    ];

    protected $casts = [
        'dob'            => 'date',
        'admission_date' => 'date',
        'verified_at'      => 'datetime',
    ];

    public function tenant()       { return $this->belongsToCentralTenant(); }
    public function user()         { return $this->belongsTo(User::class); }
    public function schoolClass()  { return $this->belongsTo(SchoolClass::class); }
    public function schoolHouse()  { return $this->belongsTo(SchoolHouse::class, 'school_house_id'); }
    public function academicYear() { return $this->belongsTo(AcademicYearRecord::class, 'academic_year_id'); }

    public function scopeActive($q) { return $q->where('status', 'active'); }

    public function scopeVerified($q) { return $q->whereNotNull('verified_at'); }

    public function scopeUnverified($q) { return $q->whereNull('verified_at'); }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /** Single student identifier — used for records, fest, and portal login username. */
    public function portalLoginId(): ?string
    {
        return filled($this->reg_no) ? $this->reg_no : null;
    }

    public function verifiedBy() { return $this->belongsTo(User::class, 'verified_by_user_id'); }

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

        $serveRoute = url(route('school.students.photo', [
            'tenantId' => $this->tenant_id,
            'student'  => $this->id,
        ], absolute: false));

        $version = $this->updated_at?->timestamp ?? 0;

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

        // Local / shared storage is served through the app route (works across disks).
        return $serveRoute.($version ? '?v='.$version : '');
    }
}
