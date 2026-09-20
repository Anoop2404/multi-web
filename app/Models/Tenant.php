<?php

namespace App\Models;

use App\Support\TenantCache;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\DatabaseConfig;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    /**
     * Skip empty DB username/password so Stancl keeps the central connection credentials.
     * Password is optional when assigning a tenant database.
     */
    public function database(): DatabaseConfig
    {
        return new class($this) extends DatabaseConfig {
            public function tenantConfig(): array
            {
                return array_filter(
                    parent::tenantConfig(),
                    static fn ($value) => $value !== null && $value !== '',
                );
            }
        };
    }

    protected $fillable = [
        'id', 'type', 'name', 'domain', 'subdomain',
        'parent_id', 'plan', 'is_active', 'fest_registration_closed',
        'school_prefix', 'school_no', 'membership_status', 'is_non_affiliated', 'is_appeal_pool', 'renewal_status', 'application_payload', 'prefixes_locked',
        'school_setup_wizard_dismissed', 'nav_overrides',
    ];

    protected $casts = [
        'is_active'                 => 'boolean',
        'fest_registration_closed'  => 'boolean',
        'is_non_affiliated'         => 'boolean',
        'is_appeal_pool'            => 'boolean',
        'data'                      => 'array',
        'application_payload'       => 'array',
        'nav_overrides'             => 'array',
        'prefixes_locked'           => 'boolean',
        'school_setup_wizard_dismissed' => 'boolean',
        'school_no'                 => 'integer',
    ];

    public static function getCustomColumns(): array
    {
        return [
            'id', 'type', 'name', 'domain', 'subdomain', 'parent_id', 'plan', 'is_active',
            'fest_registration_closed',
            'school_prefix', 'school_no', 'membership_status', 'is_non_affiliated', 'is_appeal_pool', 'renewal_status', 'application_payload', 'prefixes_locked',
            'school_setup_wizard_dismissed', 'nav_overrides',
        ];
    }
    public function getNameAttribute($value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (($this->attributes['type'] ?? $this->type) === 'school') {
            return mb_strtoupper((string) $value, 'UTF-8');
        }

        return $value;
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function parent()       { return $this->belongsTo(Tenant::class, 'parent_id'); }
    public function children()     { return $this->hasMany(Tenant::class, 'parent_id'); }
    public function subscription() { return $this->hasOne(TenantSubscription::class, 'tenant_id'); }
    public function settings()     { return $this->hasMany(TenantSetting::class, 'tenant_id'); }
    public function sections()     { return $this->hasMany(SiteSection::class, 'tenant_id'); }
    public function news()         { return $this->hasMany(NewsArticle::class, 'tenant_id'); }
    public function events()       { return $this->hasMany(Event::class, 'tenant_id'); }
    public function albums()       { return $this->hasMany(GalleryAlbum::class, 'tenant_id'); }
    public function staff()        { return $this->hasMany(StaffMember::class, 'tenant_id'); }
    public function achievements() { return $this->hasMany(Achievement::class, 'tenant_id'); }
    public function testimonials() { return $this->hasMany(Testimonial::class, 'tenant_id'); }
    public function alumni()       { return $this->hasMany(Alumni::class, 'tenant_id'); }
    public function downloads()    { return $this->hasMany(Download::class, 'tenant_id'); }
    public function vacancies()    { return $this->hasMany(JobVacancy::class, 'tenant_id'); }
    public function boardResults() { return $this->hasMany(BoardResult::class, 'tenant_id'); }
    public function admissionEnquiries() { return $this->hasMany(AdmissionEnquiry::class, 'tenant_id'); }
    public function tcRequests()   { return $this->hasMany(TcRequest::class, 'tenant_id'); }
    public function schoolClasses(){ return $this->hasMany(SchoolClass::class, 'tenant_id'); }
    public function students()     { return $this->hasMany(Student::class, 'tenant_id'); }
    // Sahodaya-specific
    public function officeBearers() { return $this->hasMany(OfficeBearers::class, 'tenant_id'); }
    public function circulars()     { return $this->hasMany(Circular::class, 'tenant_id'); }
    public function kalotsavEvents() { return $this->hasMany(KalotsavEvent::class, 'tenant_id'); }
    public function sahodayaProfile() { return $this->hasOne(SahodayaProfile::class, 'tenant_id'); }
    public function registrations()   { return $this->hasMany(Registration::class, 'school_id'); }
    public function submissions()     { return $this->hasMany(SchoolYearSubmission::class, 'school_id'); }

    /**
     * A school's printable "code" for ID cards etc. — {Sahodaya prefix}-{permanent
     * per-Sahodaya school number, zero-padded to 3 digits}, e.g. "MCS-027". The
     * number is assigned lazily (on first call, for whichever school asks first) and
     * then stays fixed forever — see assignNextSchoolNo() — so cards already printed
     * never go stale. Returns null for a non-school tenant, or a school whose
     * Sahodaya has no SahodayaProfile.prefix set yet.
     */
    public function schoolCode(): ?string
    {
        if ($this->type !== 'school' || ! $this->parent_id) {
            return null;
        }

        $prefix = Tenant::find($this->parent_id)?->sahodayaProfile?->prefix;
        if (! $prefix) {
            return null;
        }

        // Cached on this instance too, not just re-fetched — schoolCode() can be
        // called repeatedly on the same in-memory Tenant (e.g. once per card on a
        // print run) and must not re-run assignNextSchoolNo() each time, which would
        // otherwise see this instance's own $school_no still null and hand out a
        // second, wrong number for the same school.
        $schoolNo = $this->school_no ?? static::assignNextSchoolNo($this->parent_id, $this->id);
        $this->school_no = $schoolNo;

        return $prefix.'-'.str_pad((string) $schoolNo, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Assigns the next free per-Sahodaya school number (1, 2, 3...) to $schoolId and
     * persists it — locks every school row under this Sahodaya for the duration so two
     * concurrent callers (e.g. two ID cards rendering at once for different
     * first-time schools) can't both compute the same next number. Re-checks the
     * target row's own school_no under lock first, so calling this twice for a school
     * that already has one (a stale caller, or a concurrent assignment that just won
     * the race) returns the existing number instead of handing out a second one.
     */
    public static function assignNextSchoolNo(string $sahodayaId, string $schoolId): int
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($sahodayaId, $schoolId) {
            $existing = static::where('id', $schoolId)->lockForUpdate()->value('school_no');
            if ($existing) {
                return $existing;
            }

            // Postgres rejects FOR UPDATE combined with an aggregate (max()) in one
            // query — lock the rows with a plain SELECT instead and take the max in
            // PHP from the locked set.
            $max = static::where('parent_id', $sahodayaId)->where('type', 'school')
                ->lockForUpdate()
                ->pluck('school_no')
                ->max();
            $next = ((int) $max) + 1;
            static::where('id', $schoolId)->update(['school_no' => $next]);

            return $next;
        });
    }

    public function isMembershipApproved(): bool
    {
        return $this->type !== 'school' || $this->membership_status === 'approved';
    }

    // ── Settings helpers ─────────────────────────────────────────────────────

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return TenantCache::remember($this->id, "setting:{$key}", 3600, function () use ($key, $default) {
            return $this->settings()->where('key', $key)->first()?->value ?? $default;
        });
    }

    public function setSetting(string $key, mixed $value): void
    {
        $this->settings()->updateOrCreate(['key' => $key], ['value' => $value]);
        TenantCache::forget($this->id, "setting:{$key}");
        $this->invalidateCache();
    }

    public function getTheme(): array
    {
        return $this->getSetting('theme', []) ?? [];
    }

    public function getWidgets(): array
    {
        return $this->getSetting('widgets', []) ?? [];
    }

    /**
     * A school's own bank/UPI details — the same shape as SahodayaProfile's payment_*
     * columns, but stored via the generic settings store rather than a dedicated table,
     * since school-side settings never got a SahodayaProfile-style profile model. Used
     * when this school is designated the "host school" for an event's food payments
     * (FestEvent::food_payee_type === 'host_school') — see FestFoodOrderController::show().
     *
     * @return array{bank_name: ?string, account_no: ?string, ifsc: ?string, upi: ?string, qr_code: ?string}
     */
    public function paymentDetails(): array
    {
        return array_merge(
            ['bank_name' => null, 'account_no' => null, 'ifsc' => null, 'upi' => null, 'qr_code' => null],
            $this->getSetting('payment', []) ?? [],
        );
    }

    /** Formatted payment details for a school ordering food from this host — mirrors SahodayaProfile::paymentDetailsText(). */
    public function paymentDetailsText(): string
    {
        $d = $this->paymentDetails();

        return implode("\n", array_filter([
            $d['bank_name'] ? "Bank: {$d['bank_name']}" : null,
            $d['account_no'] ? "Account: {$d['account_no']}" : null,
            $d['ifsc'] ? "IFSC: {$d['ifsc']}" : null,
            $d['upi'] ? "UPI: {$d['upi']}" : null,
        ]));
    }

    /** Mirrors SahodayaProfile::paymentQrCodeUrl(). */
    public function paymentQrCodeUrl(): ?string
    {
        $path = $this->paymentDetails()['qr_code'];
        if (blank($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return \App\Support\TenantStorage::assetUrl(null, $path)
            ?? \App\Support\TenantStorage::logoUrl(null, $path)
            ?? ('/storage/'.ltrim($path, '/'));
    }

    public function invalidateCache(): void
    {
        TenantCache::flushTenant($this->id);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($q)    { return $q->where('is_active', true); }
    public function scopeSchools($q)   { return $q->where('type', 'school'); }
    public function scopeSahodayas($q) { return $q->where('type', 'sahodaya'); }

    /** Real, competing schools only — excludes the per-Sahodaya wildcard-appeal placeholder. */
    public function scopeExcludingAppealPools($q) { return $q->where('is_appeal_pool', false); }

    /**
     * The school ids that must never be credited in a real school's championship
     * total (each is a per-Sahodaya placeholder wildcard-appeal registrations are
     * filed under). Callers should fetch this once per scoreboard computation and
     * reuse it across the mark loop, not re-query per mark.
     *
     * @return array<string, true>
     */
    public static function appealPoolSchoolIds(): array
    {
        return self::query()->where('is_appeal_pool', true)->pluck('id')
            ->flip()->map(fn () => true)->all();
    }

    /**
     * Find-or-create the one placeholder "Appeal School" tenant a Sahodaya files
     * wildcard/court-order registrations under. A school-type tenant whose parent is a
     * Sahodaya shares that Sahodaya's own database (TenantObserver::creating()) — no new
     * database is provisioned, so this is cheap and side-effect-free.
     */
    public static function ensureAppealPoolSchool(string $sahodayaId): self
    {
        $id = "appeal-school-{$sahodayaId}";

        $existing = self::find($id);
        if ($existing) {
            return $existing;
        }

        try {
            return self::create([
                'id'             => $id,
                'type'           => 'school',
                'name'           => 'Appeal School',
                'parent_id'      => $sahodayaId,
                'is_active'      => true,
                'is_appeal_pool' => true,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Lost a create race against a concurrent caller for the same Sahodaya —
            // the row now exists, so just return it.
            return self::findOrFail($id);
        }
    }

    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MembershipPayment::class, 'school_id');
    }

    /**
     * Tenant-scoped models (settings, sections, students, …) live in the Sahodaya
     * database when database_per_sahodaya is enabled. Without this override, Eloquent
     * inherits the central connection from this model and queries the wrong database.
     */
    protected function newRelatedInstance($class)
    {
        return tap(new $class, function ($instance) {
            if ($instance->getConnectionName()) {
                return;
            }

            // In dedicated-DB mode, leave connection unset when the default connection
            // already points at a tenant database (Stancl "tenant" or superadmin runtime).
            if (config('tenancy.database_per_sahodaya', true)) {
                $central = config('tenancy.database.central_connection');
                if (tenancy()->initialized || config('database.default') !== $central) {
                    return;
                }
            }

            $instance->setConnection($this->getConnectionName());
        });
    }
}
