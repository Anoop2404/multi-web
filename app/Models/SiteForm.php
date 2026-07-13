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
