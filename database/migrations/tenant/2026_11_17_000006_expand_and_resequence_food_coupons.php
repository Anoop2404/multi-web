<?php

use App\Models\FestFoodCoupon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_food_coupons')) {
            return;
        }

        // 1. Expand any legacy multi-head coupons into individual 1-head records
        $multiHeadCoupons = DB::table('fest_food_coupons')
            ->where('head_count', '>', 1)
            ->get();

        foreach ($multiHeadCoupons as $coupon) {
            $totalHead = (int) $coupon->head_count;

            // Reduce existing row to head_count = 1
            DB::table('fest_food_coupons')
                ->where('id', $coupon->id)
                ->update(['head_count' => 1]);

            // Create (totalHead - 1) additional individual rows
            for ($i = 1; $i < $totalHead; $i++) {
                $uniqueToken = static::generateUniqueToken();

                DB::table('fest_food_coupons')->insert([
                    'event_id'    => $coupon->event_id,
                    'school_id'   => $coupon->school_id,
                    'coupon_code' => 'TEMP-' . Str::random(8),
                    'sequence_no' => 0,
                    'qr_token'    => $uniqueToken,
                    'meal_type'   => $coupon->meal_type,
                    'valid_date'  => $coupon->valid_date,
                    'head_count'  => 1,
                    'is_extra'    => $coupon->is_extra,
                    'batch_id'    => $coupon->batch_id,
                    'status'      => $coupon->status,
                    'issued_at'   => $coupon->issued_at,
                    'redeemed_at' => null,
                    'notes'       => $coupon->notes,
                    'created_at'  => $coupon->created_at ?? now(),
                    'updated_at'  => now(),
                ]);
            }
        }

        // 2. Ensure every coupon has a distinct qr_token
        $allCoupons = DB::table('fest_food_coupons')->select('id', 'qr_token')->get();
        $seenTokens = [];
        foreach ($allCoupons as $c) {
            if (empty($c->qr_token) || isset($seenTokens[$c->qr_token])) {
                $newToken = static::generateUniqueToken();
                DB::table('fest_food_coupons')->where('id', $c->id)->update(['qr_token' => $newToken]);
                $seenTokens[$newToken] = true;
            } else {
                $seenTokens[$c->qr_token] = true;
            }
        }

        // 3. Resequence coupons continuously across all schools per (event_id, meal_type)
        $eventMeals = DB::table('fest_food_coupons')
            ->select('event_id', 'meal_type')
            ->distinct()
            ->get();

        $prefixes = [
            'breakfast' => 'BF',
            'lunch'     => 'LN',
            'dinner'    => 'DN',
            'snacks'    => 'SN',
            'tea'       => 'TE',
            'other'     => 'OT',
        ];

        foreach ($eventMeals as $em) {
            $prefix = $prefixes[strtolower($em->meal_type)] ?? 'FC';

            $couponsToSequence = DB::table('fest_food_coupons')
                ->where('event_id', $em->event_id)
                ->where('meal_type', $em->meal_type)
                ->orderBy('school_id')
                ->orderBy('id')
                ->get();

            // Set temporary coupon codes to prevent unique constraint violation during re-ordering
            foreach ($couponsToSequence as $c) {
                DB::table('fest_food_coupons')
                    ->where('id', $c->id)
                    ->update(['coupon_code' => 'TMP_' . $c->id . '_' . Str::random(5)]);
            }

            $seq = 1;
            foreach ($couponsToSequence as $c) {
                $code = $prefix . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

                DB::table('fest_food_coupons')
                    ->where('id', $c->id)
                    ->update([
                        'sequence_no' => $seq,
                        'coupon_code' => $code,
                    ]);

                $seq++;
            }
        }
    }

    public function down(): void
    {
        // No down migration needed for expanding to 1-head coupons
    }

    private static function generateUniqueToken(): string
    {
        do {
            $token = strtoupper(Str::random(10));
        } while (DB::table('fest_food_coupons')->where('qr_token', $token)->exists());

        return $token;
    }
};
