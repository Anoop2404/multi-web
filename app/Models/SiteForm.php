<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SiteForm extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'tenant_id', 'site_id', 'name', 'slug', 'fields_json',
        'success_message', 'notify_email', 'is_active', 'honeypot_enabled',
    ];

    protected $casts = [
        'fields_json' => 'array',
        'is_active' => 'boolean',
        'honeypot_enabled' => 'boolean',
    ];

    public function submissions(): HasMany
    {
        return $this->hasMany(SiteFormSubmission::class, 'site_form_id');
    }

    /** @return list<array<string, mixed>> */
    public static function defaultContactFields(): array
    {
        return [
            ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ['key' => 'subject', 'label' => 'Subject', 'type' => 'text', 'required' => false],
            ['key' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true],
        ];
    }

    public static function ensureDefaultContact(string $tenantId, ?int $siteId = null): self
    {
        return self::firstOrCreate(
            ['tenant_id' => $tenantId, 'slug' => 'contact'],
            [
                'site_id' => $siteId ?? WebsiteSite::ensurePrimary($tenantId)->id,
                'name' => 'Contact form',
                'fields_json' => self::defaultContactFields(),
                'success_message' => 'Thank you — we received your message.',
                'honeypot_enabled' => true,
                'is_active' => true,
            ],
        );
    }

    public static function uniqueSlug(string $tenantId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'form';
        $candidate = $base;
        $n = 2;
        while (
            self::where('tenant_id', $tenantId)
                ->where('slug', $candidate)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $candidate = $base.'-'.$n++;
        }

        return $candidate;
    }
}
