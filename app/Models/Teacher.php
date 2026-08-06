<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use App\Support\TenantStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Model
{
    use BelongsToCentralTenant;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'user_id', 'academic_year_id', 'reg_no', 'login_code', 'employee_code', 'name', 'gender', 'dob',
        'email', 'mobile', 'address', 'photo',
        'designation', 'designation_id', 'subject', 'subject_ids', 'teaching_type_id',
        'qualification', 'experience_years', 'date_of_joining', 'employment_status',
        'status', 'verified_at', 'verified_by_user_id', 'rejection_reason',
    ];

    protected $casts = [
        'dob'              => 'date',
        'date_of_joining'  => 'date',
        'verified_at'      => 'datetime',
        'experience_years' => 'integer',
        'subject_ids'      => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsToCentralTenant();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYearRecord::class, 'academic_year_id');
    }

    public function teachingType(): BelongsTo
    {
        return $this->belongsTo(TeachingType::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    /**
     * Subjects are central master data while teachers live in the tenant database,
     * so a cross-database pivot join is impossible. Subject ids are stored on the
     * teacher row (JSON) and resolved from the central Subject master here.
     *
     * @return \Illuminate\Support\Collection<int, Subject>
     */
    public function getSubjectsAttribute(): \Illuminate\Support\Collection
    {
        $ids = $this->subject_ids ?? [];
        if (empty($ids)) {
            return collect();
        }

        return Subject::whereIn('id', $ids)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();
    }

    /**
     * The only code path allowed to write the `subject` column. `subject` is a
     * denormalized, comma-joined label string derived from `subject_ids` (kept
     * around for CSV/XLSX export and legacy display) — writing it from anywhere
     * else risks silently desyncing it from `subject_ids`.
     */
    public function syncSubjectIds(array $subjectIds): void
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($subjectIds, fn ($id) => filled($id)))));

        $labels = $ids === []
            ? []
            : Subject::whereIn('id', $ids)->orderBy('sort_order')->orderBy('label')->pluck('label')->all();

        $this->update([
            'subject_ids' => $ids ?: null,
            'subject'     => $labels !== [] ? implode(', ', $labels) : null,
        ]);
    }

    public function schoolClasses(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'teacher_school_class')
            ->withPivot('section')
            ->withTimestamps();
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function scopeVerified($q)
    {
        return $q->whereNotNull('verified_at');
    }

    public function scopeUnverified($q)
    {
        return $q->whereNull('verified_at');
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
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

    /** Photo URL accessible from the teacher portal (own account only). */
    public function portalPhotoUrl(): ?string
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

        return route('portal.teacher.photo', [
            'tenantId' => $this->tenant_id,
        ], absolute: false);
    }

    /**
     * Cached, downscaled base64 data URI for this teacher's photo — used by ID card
     * PDF generation (FestIdCardService, TrainingIdCardService), which can embed
     * hundreds/thousands of these in a single bulk PDF. Uses photoBase64DataUri()
     * (downscales + never hands the renderer a bare filesystem path) instead of the
     * plain photoDataUri(), and caches per teacher (keyed on updated_at) so repeat PDF
     * generations don't re-decode the same full-resolution photo from scratch. See
     * docs/N1_AND_REPORT_MEMORY_AUDIT_2026_08_03.md §2.
     */
    public function photoDataUri(): ?string
    {
        if (! $this->photo || ! $this->tenant_id) {
            return null;
        }

        // photoBase64DataUri() deliberately refuses to fetch remote http(s) URLs
        // (unlike the plain photoDataUri() this replaces) — preserve the old
        // pass-through behavior for the rare teacher whose `photo` column already
        // holds a full external URL rather than a relative storage path.
        if (str_starts_with($this->photo, 'http://') || str_starts_with($this->photo, 'https://')) {
            return $this->photo;
        }

        $cacheKey = 'teacher-photo-thumb:'.$this->id.':'.($this->updated_at?->timestamp ?? 0);

        return \Illuminate\Support\Facades\Cache::remember(
            $cacheKey,
            now()->addDays(30),
            function () {
                $tenant = $this->relationLoaded('tenant') ? $this->tenant : Tenant::find($this->tenant_id);

                return TenantStorage::photoBase64DataUri($tenant, $this->photo);
            },
        );
    }
}
