<?php

namespace App\Http\Controllers;

use App\Models\FestEvent;
use App\Models\FestFoodCoupon;
use App\Models\Tenant;
use App\Support\TenancyDatabase;
use Illuminate\Http\Request;

class FestFoodCouponVerificationController extends Controller
{
    /**
     * Public / mobile scanner endpoint for verifying a food coupon via QR code.
     */
    public function verify(string $token)
    {
        $token = strtoupper(trim($token));
        $resolved = $this->resolveCoupon($token);

        if (! $resolved) {
            return view('fest.catering.food-coupon-verify', [
                'valid' => false,
                'coupon' => null,
                'token' => $token,
                'event' => null,
                'sahodaya' => null,
                'schoolName' => null,
                'message' => 'Invalid or unknown coupon code.',
            ]);
        }

        $coupon = $resolved['coupon'];
        $tenant = $resolved['tenant'];
        $event = $resolved['event'];
        $schoolName = $resolved['schoolName'];

        return view('fest.catering.food-coupon-verify', [
            'valid' => true,
            'coupon' => $coupon,
            'token' => $token,
            'event' => $event,
            'sahodaya' => $tenant,
            'schoolName' => $schoolName,
            'canRedeem' => $coupon->status === 'issued',
        ]);
    }

    /**
     * Mark coupon redeemed from the verification screen.
     */
    public function redeem(string $token, Request $request)
    {
        $token = strtoupper(trim($token));
        $resolved = $this->resolveCoupon($token);

        if (! $resolved) {
            return back()->with('error', 'Coupon not found.');
        }

        $coupon = $resolved['coupon'];
        $tenant = $resolved['tenant'];

        if ($coupon->status !== 'issued') {
            return back()->with('error', 'Coupon is already ' . $coupon->status . '.');
        }

        TenancyDatabase::withTenantDatabase($tenant, function () use ($coupon) {
            FestFoodCoupon::whereKey($coupon->id)->update([
                'status' => 'redeemed',
                'redeemed_at' => now(),
            ]);
        });

        return back()->with('success', 'Coupon ' . $coupon->coupon_code . ' marked as REDEEMED successfully!');
    }

    /**
     * Resolve coupon and its tenant context across single-tenant or multi-tenant scope.
     *
     * @return array{coupon: FestFoodCoupon, tenant: Tenant, event: FestEvent, schoolName: string}|null
     */
    private function resolveCoupon(string $token): ?array
    {
        if (tenancy()->initialized) {
            $coupon = FestFoodCoupon::where('qr_token', $token)
                ->orWhere('coupon_code', $token)
                ->first();
            if ($coupon) {
                $tenant = tenancy()->tenant;
                $event = FestEvent::find($coupon->event_id);
                return [
                    'coupon' => $coupon,
                    'tenant' => $tenant instanceof Tenant ? $tenant : Tenant::find($tenant->getTenantKey()),
                    'event' => $event,
                    'schoolName' => $coupon->school_display_name,
                ];
            }
        }

        // Central context: search active Sahodayas
        $sahodayas = Tenant::where('type', 'sahodaya')->get();
        foreach ($sahodayas as $sahodaya) {
            $found = TenancyDatabase::withTenantDatabase($sahodaya, function () use ($token) {
                return FestFoodCoupon::where('qr_token', $token)
                    ->orWhere('coupon_code', $token)
                    ->first();
            });

            if ($found) {
                $event = TenancyDatabase::withTenantDatabase($sahodaya, fn () => FestEvent::find($found->event_id));
                $schoolName = TenancyDatabase::withTenantDatabase($sahodaya, fn () => $found->school_display_name);

                return [
                    'coupon' => $found,
                    'tenant' => $sahodaya,
                    'event' => $event,
                    'schoolName' => $schoolName,
                ];
            }
        }

        return null;
    }
}
