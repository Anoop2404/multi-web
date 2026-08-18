<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A geographic/administrative State — the tier above Sahodaya clusters.
 * Deliberately not named `State`: App\Models\State\* is already a namespace
 * for the operational (separate-database) fest tier, and a same-named class
 * one directory over would be a footgun for anyone grepping the codebase.
 */
class PlatformState extends Model
{
    use CentralConnection;

    protected $table = 'states';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'code', 'name', 'is_active',
        'contact_name', 'contact_email', 'contact_phone', 'branding',
        'default_academic_year', 'financial_year_start_month',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'branding' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $state) {
            if (! $state->id) {
                $state->id = (string) Str::uuid();
            }
        });
    }

    public function platformUsers(): HasMany
    {
        return $this->hasMany(PlatformUser::class, 'state_id');
    }

    public function festStatePrograms(): HasMany
    {
        return $this->hasMany(FestStateProgram::class, 'state_id');
    }
}
