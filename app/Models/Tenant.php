<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDomains;

    protected $fillable = [
        'id', 'type', 'name', 'domain', 'subdomain',
        'parent_id', 'plan', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'data'      => 'array',
    ];

    public static function getCustomColumns(): array
    {
        return ['id', 'type', 'name', 'domain', 'subdomain', 'parent_id', 'plan', 'is_active'];
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
    // Sahodaya-specific
    public function officeBearers() { return $this->hasMany(OfficeBearers::class, 'tenant_id'); }
    public function circulars()     { return $this->hasMany(Circular::class, 'tenant_id'); }
    public function kalotsavEvents() { return $this->hasMany(KalotsavEvent::class, 'tenant_id'); }

    // ── Settings helpers ─────────────────────────────────────────────────────

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return Cache::tags(["tenant:{$this->id}"])->remember("setting:{$key}", 3600, function () use ($key, $default) {
            $setting = $this->settings()->where('key', $key)->first();
            return $setting ? $setting->value : $default;
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
        Cache::tags(["tenant:{$this->id}"])->flush();
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($q)    { return $q->where('is_active', true); }
    public function scopeSchools($q)   { return $q->where('type', 'school'); }
    public function scopeSahodayas($q) { return $q->where('type', 'sahodaya'); }
}
