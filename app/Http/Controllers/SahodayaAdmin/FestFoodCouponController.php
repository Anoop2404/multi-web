<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\FestPageActivity;
use App\Models\FestCateringOrder;
use App\Models\FestEvent;
use App\Models\FestFoodBill;
use App\Models\FestFoodCoupon;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\FestPartitionService;
use App\Support\TenantBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class FestFoodCouponController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event, FestPartitionService $partitions)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $coupons = FestFoodCoupon::where('event_id', $event->id)
            ->orderByDesc('valid_date')
            ->orderBy('school_id')
            ->get();

        $schools = Tenant::whereIn('id', $coupons->pluck('school_id')->unique())
            ->pluck('name', 'id');

        // Coupons are always empty on a partitioned hub by construction — they're issued
        // per region, against each region's own catering orders/bills. Without hub
        // awareness here this page silently showed an empty list with no explanation why.
        $isPartitionedHub = $partitions->isPartitionedHub($event);

        return $this->inertia('Sahodaya/Events/FoodCoupons', $this->withEventActivity($event, FestPageActivity::FOOD_COUPONS, [
            'event'   => $event,
            'hierarchy' => $event->hierarchyContext(),
            'isPartitionedHub' => $isPartitionedHub,
            'foodRegionSummary' => $isPartitionedHub ? $partitions->foodRegionDrillDownSummary($event) : [],
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

    /**
     * Older headcount/coupon flow. FestCateringOrder is free-form headcount with no price
     * or payment record at all, so once an event requires payment for coupons, this flow
     * has nothing to check payment against and is blocked outright rather than silently
     * issuing free coupons on a "payment required" event — see Food Module audit
     * 2026-08-17, Finding 1: this was previously a complete, unguarded bypass of
     * require_payment_for_coupons via the legacy flow. Events that don't require payment
     * are unaffected. The flag's "real" enforcement is issueFromBill() below, the
     * priced-menu equivalent, which actually has a balance to check.
     * See docs/REGION_SCOPED_ADMIN_AND_EVENT_FLOW_PLAN.md §2.6/§2.7.
     */
    public function issueFromCatering(string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if(
            $event->require_payment_for_coupons,
            422,
            'This event requires payment for food coupons. The free catering flow has no payment record to check, so it cannot be used to issue coupons here — use Food Billing (priced menu) instead.'
        );

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

    /**
     * Priced-menu/billing equivalent of issueFromCatering() — closes the gap where schools
     * ordering through FestFoodBill/FestFoodOrderItem got no coupons at all (the old method
     * only ever read FestCateringOrder). One coupon per (school, menu_date, meal_type) group
     * of ordered quantities, mirroring the shape issueFromCatering() already produces so the
     * redemption/print flow (FestFoodCoupon, print(), redeem()) needs no changes.
     *
     * When $event->require_payment_for_coupons is on, only settled bills are eligible —
     * unlike the free catering flow, a FestFoodBill has a real balance, so "paid" is
     * meaningful here.
     */
    public function issueFromBill(string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $requirePayment = (bool) ($event->require_payment_for_coupons ?? false);

        $bills = FestFoodBill::forTenant($this->sahodaya->id)
            ->where('event_id', $event->id)
            ->where('status', '!=', FestFoodBill::STATUS_CANCELLED)
            ->when($requirePayment, fn ($q) => $q->where('status', FestFoodBill::STATUS_SETTLED))
            ->with('orderItems')
            ->get();

        $created = 0;
        foreach ($bills as $bill) {
            $grouped = $bill->orderItems->groupBy(
                fn ($item) => $item->menu_date->toDateString().'|'.$item->meal_type
            );

            foreach ($grouped as $key => $items) {
                [$menuDate, $mealType] = explode('|', $key, 2);
                $headCount = (int) $items->sum('quantity');

                if ($headCount <= 0) {
                    continue;
                }

                $exists = FestFoodCoupon::where('event_id', $event->id)
                    ->where('school_id', $bill->school_id)
                    ->where('valid_date', $menuDate)
                    ->where('meal_type', $mealType)
                    ->exists();

                if ($exists) {
                    continue;
                }

                FestFoodCoupon::create([
                    'event_id'    => $event->id,
                    'school_id'   => $bill->school_id,
                    'coupon_code' => FestFoodCoupon::generateCode($event),
                    'meal_type'   => $mealType,
                    'valid_date'  => $menuDate,
                    'head_count'  => $headCount,
                    'status'      => 'issued',
                    'issued_at'   => now(),
                    'notes'       => "Issued from priced food-menu order (bill #{$bill->id})",
                ]);
                $created++;
            }
        }

        $audit->festEvent($event, FestPageActivity::FOOD_COUPONS, 'fest.food_coupons.issued', "{$created} food coupon(s) issued from food billing", [
            'count' => $created,
            'require_payment' => $requirePayment,
        ]);

        return back()->with('success', "{$created} food coupon(s) issued from ".($requirePayment ? 'settled' : 'open').' food bills.');
    }

    public function redeem(string $tenantId, FestEvent $event, FestFoodCoupon $coupon, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($coupon->event_id !== $event->id, 404);

        // Prevent double-redeem: only issued coupons can be marked redeemed.
        abort_if($coupon->status !== 'issued', 422, 'Only issued coupons can be redeemed.');

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
