<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A school under an ExternalSahodaya (not a platform tenant). Enters its own qualified
 * students directly — see docs/STATE_LEVEL_KALOTSAV_ROLLOUT_PLAN.md §2.1.
 */
class ExternalSchool extends Model
{
    use CentralConnection, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'external_sahodaya_id', 'name', 'username', 'contact_name', 'contact_phone',
        'access_code', 'password', 'plain_password', 'status',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function sahodaya(): BelongsTo
    {
        return $this->belongsTo(ExternalSahodaya::class, 'external_sahodaya_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasLogin(): bool
    {
        return $this->username !== null && $this->password !== null;
    }

    public static function generateAccessCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (self::where('access_code', $code)->exists());

        return $code;
    }
}
