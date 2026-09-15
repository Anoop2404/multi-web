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
        'state_program_id', 'name', 'district', 'contact_name', 'contact_phone', 'contact_email',
        'access_code', 'status', 'is_appeal_pool', 'source',
    ];

    protected function casts(): array
    {
        return [
            'is_appeal_pool' => 'boolean',
        ];
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
