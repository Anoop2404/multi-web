<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One canonical Sahodaya identity per state — the State module's own directory, resolving both
 * Sahodayas that run on the platform and those that do not, to a single id that survives promotion.
 *
 * See the creating migration for why the raw source_tenant_id could not serve as that identity.
 */
class StateSahodaya extends StateModel
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'state_sahodayas';

    public const ORIGIN_MANAGED = 'managed';

    public const ORIGIN_EXTERNAL = 'external';

    protected $fillable = [
        'state_id', 'name', 'code', 'district',
        'tenant_id', 'external_sahodaya_id',
        'origin', 'is_active', 'promoted_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active'   => 'boolean',
            'promoted_at' => 'datetime',
        ];
    }

    public function intakes(): HasMany
    {
        return $this->hasMany(StateQualifierIntake::class, 'sahodaya_id');
    }

    /**
     * Whether this Sahodaya runs on the platform right now — which is not the same question as how
     * it first arrived. A promoted external Sahodaya is managed today and keeps origin 'external',
     * so reports can still answer "how many took part from outside the platform".
     */
    public function isOnPlatform(): bool
    {
        return $this->tenant_id !== null;
    }

    public function arrivedExternal(): bool
    {
        return $this->origin === self::ORIGIN_EXTERNAL;
    }

    /**
     * The raw source keys that resolve to this identity. A promoted Sahodaya legitimately has two —
     * its history arrived as "external:{uuid}" and its later submissions as the bare tenant uuid —
     * which is precisely why queries should group on the canonical id instead.
     *
     * @return list<string>
     */
    public function sourceKeys(): array
    {
        return array_values(array_filter([
            $this->tenant_id,
            $this->external_sahodaya_id ? "external:{$this->external_sahodaya_id}" : null,
        ]));
    }

    public function scopeForState($query, ?string $stateId)
    {
        return $stateId === null ? $query->whereRaw('1 = 0') : $query->where('state_id', $stateId);
    }
}
