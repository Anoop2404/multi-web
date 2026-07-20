<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\FestPageActivity;
use App\Models\FestCateringOrder;
use App\Models\FestEvent;
use App\Models\FestFoodCoupon;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use App\Support\TenantBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class FestFoodCouponController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $coupons = FestFoodCoupon::where('event_id', $event->id)
            ->orderByDesc('valid_date')
            ->orderBy('school_id')
            ->get();

        $schools = Tenant::whereIn('id', $coupons->pluck('school_id')->unique())
            ->pluck('name', 'id');

        return $this->inertia('Sahodaya/Events/FoodCoupons', $this->withEventActivity($event, FestPageActivity::FOOD_COUPONS, [
            'event'   => $event,
            'coupons' => $coupons->map(fn (FestFoodCoupon $c) => [
                ...$c->toArray(),
                'school_name' => $schools[$c->school_id] ?? $c->school_id,
            ]),
            'summary' => [
                'issued'   => $coupons->where('status', 'issued')->count(),
                'redeemed' => $coupons->where('status', 'redeemed')->count(),
            ],
        ]));
    }

    public function issueFromCatering(string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $orders = FestCateringOrder::where('event_id', $event->id)
            ->where('status', 'confirmed')
            ->get();

        $created = 0;
        foreach ($orders as $order) {
            $exists = FestFoodCoupon::where('event_id', $event->id)
                ->where('school_id', $order->school_id)
                ->where('valid_date', $order->meal_date)
                ->where('meal_type', $order->meal_type)
                ->exists();

            if ($exists) {
                continue;
            }

            FestFoodCoupon::create([
                'event_id'    => $event->id,
                'school_id'   => $order->school_id,
                'coupon_code' => FestFoodCoupon::generateCode($event),
                'meal_type'   => $order->meal_type,
                'valid_date'  => $order->meal_date,
                'head_count'  => $order->head_count,
                'status'      => 'issued',
                'issued_at'   => now(),
                'notes'       => $order->notes,
            ]);
            $created++;
        }

        $audit->festEvent($event, FestPageActivity::FOOD_COUPONS, 'fest.food_coupons.issued', "{$created} food coupon(s) issued", [
            'count' => $created,
        ]);

        return back()->with('success', "{$created} food coupon(s) issued from confirmed catering orders.");
    }

    public function redeem(string $tenantId, FestEvent $event, FestFoodCoupon $coupon, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($coupon->event_id !== $event->id, 404);

        $coupon->update(['status' => 'redeemed', 'redeemed_at' => now()]);

        $audit->festEvent($event, FestPageActivity::FOOD_COUPONS, 'fest.food_coupon.redeemed', 'Food coupon marked redeemed', [
            'coupon_id' => $coupon->id,
        ]);

        return back()->with('success', 'Coupon marked redeemed.');
    }

    public function print(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $coupons = FestFoodCoupon::where('event_id', $event->id)
            ->where('status', 'issued')
            ->with('school')
            ->orderBy('valid_date')
            ->get();

        return Pdf::loadView('fest.catering.food-coupons', [
            'event'    => $event,
            'sahodaya' => $this->sahodaya,
            'logoSrc'  => TenantBranding::logoEmbedSrc($this->sahodaya),
            'coupons'  => $coupons,
        ])->download('food-coupons-'.$event->id.'.pdf');
    }
}
