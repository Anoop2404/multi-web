<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use App\Services\Events\FestIdCardQrService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FestFoodCoupon extends Model
{
    use BelongsToCentralTenant;

    public const MEAL_PREFIXES = [
        'breakfast' => 'BF',
        'lunch'     => 'LN',
        'dinner'    => 'DN',
        'snacks'    => 'SN',
        'tea'       => 'TE',
        'other'     => 'OT',
    ];

    public const MEAL_LABELS = [
        'breakfast' => 'Breakfast',
        'lunch'     => 'Lunch',
        'dinner'    => 'Dinner',
        'snacks'    => 'Snacks',
        'tea'       => 'Tea',
        'other'     => 'Other',
    ];

    public const MEAL_COLORS = [
        'breakfast' => ['bg' => '#fef3c7', 'border' => '#f59e0b', 'text' => '#92400e', 'badge' => '#d97706'],
        'lunch'     => ['bg' => '#ecfdf5', 'border' => '#10b981', 'text' => '#065f46', 'badge' => '#059669'],
        'dinner'    => ['bg' => '#e0e7ff', 'border' => '#6366f1', 'text' => '#3730a3', 'badge' => '#4f46e5'],
        'snacks'    => ['bg' => '#fdf2f8', 'border' => '#ec4899', 'text' => '#9d174d', 'badge' => '#db2777'],
        'tea'       => ['bg' => '#fff7ed', 'border' => '#f97316', 'text' => '#9a3412', 'badge' => '#ea580c'],
        'other'     => ['bg' => '#f1f5f9', 'border' => '#64748b', 'text' => '#334155', 'badge' => '#475569'],
    ];

    protected $fillable = [
        'event_id', 'school_id', 'coupon_code', 'sequence_no', 'qr_token',
        'meal_type', 'valid_date', 'head_count', 'is_extra', 'batch_id',
        'status', 'issued_at', 'redeemed_at', 'notes',
    ];

    protected $casts = [
        'valid_date'   => 'date:Y-m-d',
        'issued_at'    => 'datetime',
        'redeemed_at'  => 'datetime',
        'is_extra'     => 'boolean',
        'sequence_no'  => 'integer',
        'head_count'   => 'integer',
    ];

    protected $appends = [
        'school_display_name',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsToCentralTenant('school_id');
    }

    public function getSchoolDisplayNameAttribute(): string
    {
        if ($this->is_extra && ! $this->school_id) {
            return 'General Buffer / Extra';
        }

        return $this->school?->name ?? ($this->school_id ?: 'General');
    }

    /**
     * Map a meal type to its serialization prefix (e.g. breakfast -> BF, lunch -> LN, dinner -> DN).
     */
    public static function prefixForMeal(string $mealType): string
    {
        return self::MEAL_PREFIXES[strtolower($mealType)] ?? 'FC';
    }

    /**
     * Generates a non-serialized, secure unique QR token (e.g. 10 chars uppercase alphanumeric).
     */
    public static function generateQrToken(): string
    {
        do {
            $token = strtoupper(Str::random(10));
        } while (static::where('qr_token', $token)->exists());

        return $token;
    }

    /**
     * Generate the next serialized coupon code for an event and meal type.
     * Uses DB row locking to serialize concurrent requests safely.
     */
    public static function generateSerializedCode(FestEvent $event, string $mealType): array
    {
        return DB::transaction(function () use ($event, $mealType) {
            FestEvent::whereKey($event->id)->lockForUpdate()->first();

            $prefix = static::prefixForMeal($mealType);

            // Fetch the maximum sequence number for this event and meal type
            $maxSeq = static::where('event_id', $event->id)
                ->where('meal_type', $mealType)
                ->max('sequence_no');

            $nextSeq = ($maxSeq ?: 0) + 1;
            $code = $prefix . '-' . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);

            // If by chance the code exists, increment until free
            while (static::where('event_id', $event->id)->where('coupon_code', $code)->exists()) {
                $nextSeq++;
                $code = $prefix . '-' . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
            }

            return [
                'code' => $code,
                'sequence_no' => $nextSeq,
            ];
        });
    }

    /**
     * Legacy generator kept for backward compatibility if called elsewhere.
     */
    public static function generateCode(FestEvent $event): string
    {
        return static::generateSerializedCode($event, 'lunch')['code'];
    }

    /**
     * QR code verification URL for this coupon.
     */
    public function verificationUrl(?string $baseUrl = null): string
    {
        $base = rtrim($baseUrl ?: url('/'), '/');
        return $base . '/food-coupons/verify/' . $this->qr_token;
    }

    /**
     * Generate QR code base64 image data URI.
     */
    public function qrCodeDataUri(?string $baseUrl = null): string
    {
        $url = $this->verificationUrl($baseUrl);
        return app(FestIdCardQrService::class)->dataUri($url);
    }
}
