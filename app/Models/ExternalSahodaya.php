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
        'state_program_id', 'name', 'contact_name', 'contact_phone', 'contact_email',
        'access_code', 'status', 'otp_code_hash', 'otp_expires_at', 'otp_last_sent_at', 'otp_attempts',
    ];

    protected $casts = [
        'otp_expires_at'   => 'datetime',
        'otp_last_sent_at' => 'datetime',
    ];

    protected $hidden = ['otp_code_hash'];

    /**
     * Whether this record requires OTP verification before granting portal access. Records
     * created before contact_email was captured (or that never set one) fall back to
     * access-code-only rather than being locked out — see migration 2026_08_11_000001.
     */
    public function requiresOtp(): bool
    {
        return filled($this->contact_email);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(FestStateProgram::class, 'state_program_id');
    }

    public function schools(): HasMany
    {
        return $this->hasMany(ExternalSchool::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public static function generateAccessCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (self::where('access_code', $code)->exists());

        return $code;
    }
}
