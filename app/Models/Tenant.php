<?php

namespace App\Models;

use App\Support\TenantCache;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $fillable = [
        'id', 'type', 'name', 'domain', 'subdomain',
        'parent_id', 'plan', 'is_active',
        'school_prefix', 'membership_status', 'application_payload', 'prefixes_locked',
    ];

    protected $casts = [
        'is_active'           => 'boolean',
        'data'                => 'array',
        'application_payload' => 'array',
        'prefixes_locked'     => 'boolean',
    ];

    public static function getCustomColumns(): array
    {
        return [
            'id', 'type', 'name', 'domain', 'subdomain', 'parent_id', 'plan', 'is_active',
            'school_prefix', 'membership_status', 'application_payload', 'prefixes_locked',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function parent()       { return $this->belongsTo(Tenant::class, 'parent_id'); }
    public function children()     { return $this->hasMany(Tenant::class, 'parent_id'); }
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

    public function invalidateCache(): void
    {
        TenantCache::flushTenant($this->id);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($q)    { return $q->where('is_active', true); }
    public function scopeSchools($q)   { return $q->where('type', 'school'); }
    public function scopeSahodayas($q) { return $q->where('type', 'sahodaya'); }

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
