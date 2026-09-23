<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A Sahodaya that is NOT a platform tenant but needs to submit State Kalolsavam qualifiers.
 * See docs/STATE_LEVEL_KALOTSAV_ROLLOUT_PLAN.md §2.1.
 */
class ExternalSahodaya extends Model
{
    use CentralConnection, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'state_program_id', 'state_id', 'tenant_id', 'name', 'district', 'contact_name',
        'contact_phone', 'contact_email', 'access_code', 'status', 'is_appeal_pool', 'source',
        'promotion_status', 'promotion_error', 'promoted_at',
    ];

    protected function casts(): array
    {
        return [
            'is_appeal_pool' => 'boolean',
            'promoted_at'    => 'datetime',
        ];
    }

    public const PROMOTION_NOT_STARTED = 'not_started';

    public const PROMOTION_QUEUED = 'queued';

    public const PROMOTION_PROVISIONING = 'provisioning';

    public const PROMOTION_READY = 'ready';

    public const PROMOTION_FAILED = 'failed';

    public function program(): BelongsTo
    {
        return $this->belongsTo(FestStateProgram::class, 'state_program_id');
    }

    public function schools(): HasMany
    {
        return $this->hasMany(ExternalSchool::class);
    }

    /** The real tenant this Sahodaya was promoted into, once it has been. */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(PlatformState::class, 'state_id');
    }

    /**
     * Promoted means "there is a real tenant behind this row now". It is deliberately keyed on
     * tenant_id rather than promotion_status: status is progress reporting, tenant_id is the fact,
     * and the portal lockdown / idempotency checks must not be fooled by a stale status string.
     */
    public function isPromoted(): bool
    {
        return $this->tenant_id !== null;
    }

    public function scopePromoted($query)
    {
        return $query->whereNotNull('tenant_id');
    }

    public function scopePendingPromotion($query)
    {
        return $query->whereNull('tenant_id');
    }

    /**
     * The Appeal Sahodaya is a synthetic pool for appeal entries, not a real body with schools,
     * students or a Kalotsav of its own — promoting it would create a tenant nobody logs into.
     */
    public function isPromotable(): bool
    {
        return ! $this->is_appeal_pool && ! $this->isPromoted() && $this->isActive();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** The Appeal Sahodaya (one per state program) never pays a registration fee. */
    public function requiresFee(): bool
    {
        return ! $this->is_appeal_pool;
    }

    public static function generateAccessCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (self::where('access_code', $code)->exists());

        return $code;
    }
}
