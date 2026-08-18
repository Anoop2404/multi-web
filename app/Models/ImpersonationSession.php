<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Audit trail + single-use handoff token for "impersonate this tenant admin" (FRD-13 §12,
 * business rule #4: "Support access through impersonation must always be logged"). Always
 * central-connection, even while tenancy is initialized for the target during consume().
 */
class ImpersonationSession extends Model
{
    use CentralConnection;

    protected $fillable = [
        'actor_platform_user_id', 'target_user_id', 'target_tenant_id', 'reason',
        'consume_token', 'token_expires_at', 'consumed_at', 'ended_at', 'ip_address',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'actor_platform_user_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'target_tenant_id');
    }

    public function isActive(): bool
    {
        return $this->consumed_at !== null && $this->ended_at === null;
    }
}
