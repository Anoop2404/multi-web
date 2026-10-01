<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestCateringOrder;
use App\Models\FestEvent;
use App\Models\FestFoodBill;
use App\Models\FestFoodCoupon;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\FestIdCardQrService;
use App\Services\Events\FestPartitionService;
use App\Support\FestPageActivity;
use App\Support\TenantBranding;
use App\Support\TenantStorage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FestFoodCouponController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event, FestPartitionService $partitions, Request $request)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $query = FestFoodCoupon::where('event_id', $event->id)
            ->with('school');

        // Optional filters
        if ($meal = $request->query('meal_type')) {
            $query->where('meal_type', $meal);
        }
        if ($schoolId = $request->query('school_id')) {
            $query->where('school_id', $schoolId);
        }
        if ($date = $request->query('valid_date')) {
            $query->where('valid_date', $date);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($request->query('extra_only') === '1') {
            $query->where('is_extra', true);
        }

        $coupons = $query->orderBy('valid_date', 'desc')
            ->orderBy('meal_type')
            ->orderBy('sequence_no')
            ->get();

        $allEventCoupons = FestFoodCoupon::where('event_id', $event->id)->get();

        $schools = Tenant::where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->orderBy('name')
            ->get(['id', 'name']);

        $schoolMap = $schools->pluck('name', 'id')->all();

        $isPartitionedHub = $partitions->isPartitionedHub($event);

        // Dates for filtering
        $eventDates = [];
        if ($event->event_start && $event->event_end) {
            for ($d = $event->event_start->copy(); $d->lte($event->event_end); $d->addDay()) {
                $eventDates[] = $d->format('Y-m-d');
            }
        }
        if (empty($eventDates)) {
            $eventDates = $allEventCoupons->pluck('valid_date')
                ->filter()
                ->map(fn ($d) => $d->format('Y-m-d'))
                ->unique()
                ->sort()
                ->values()
                ->all();
        }

        return $this->inertia('Sahodaya/Events/FoodCoupons', $this->withEventActivity($event, FestPageActivity::FOOD_COUPONS, [
            'event'   => [
                ...$event->only('id', 'title', 'event_type', 'event_start', 'event_end', 'require_payment_for_coupons'),
                'food_coupon_bg_image_url' => $event->foodCouponBgImageUrl($this->sahodaya),
                'has_template_bg' => filled($event->food_coupon_bg_image),
            ],
            'hierarchy' => $event->hierarchyContext(),
            'isPartitionedHub' => $isPartitionedHub,
            'foodRegionSummary' => $isPartitionedHub ? $partitions->foodRegionDrillDownSummary($event) : [],
            'coupons' => $coupons->map(fn (FestFoodCoupon $c) => [
                'id' => $c->id,
                'coupon_code' => $c->coupon_code,
                'qr_token' => $c->qr_token,
                'sequence_no' => $c->sequence_no,
                'meal_type' => $c->meal_type,
                'valid_date' => $c->valid_date?->format('Y-m-d'),
                'head_count' => $c->head_count,
                'is_extra' => (bool) $c->is_extra,
                'batch_id' => $c->batch_id,
                'status' => $c->status,
                'issued_at' => $c->issued_at?->toIso8601String(),
                'redeemed_at' => $c->redeemed_at?->toIso8601String(),
                'notes' => $c->notes,
                'school_id' => $c->school_id,
                'school_name' => $c->school_id ? ($schoolMap[$c->school_id] ?? $c->school_id) : 'General Buffer / Extra',
            ]),
            'schools' => $schools,
            'eventDates' => $eventDates,
            'mealTypes' => FestFoodCoupon::MEAL_LABELS,
            'mealPrefixes' => FestFoodCoupon::MEAL_PREFIXES,
            'summary' => [
                'total'    => $allEventCoupons->count(),
                'issued'   => $allEventCoupons->where('status', 'issued')->count(),
                'redeemed' => $allEventCoupons->where('status', 'redeemed')->count(),
                'extra'    => $allEventCoupons->where('is_extra', true)->count(),
                'breakfast'=> $allEventCoupons->where('meal_type', 'breakfast')->count(),
                'lunch'    => $allEventCoupons->where('meal_type', 'lunch')->count(),
                'dinner'   => $allEventCoupons->where('meal_type', 'dinner')->count(),
                'snacks'   => $allEventCoupons->where('meal_type', 'snacks')->count(),
                'tea'      => $allEventCoupons->where('meal_type', 'tea')->count(),
                'other'    => $allEventCoupons->where('meal_type', 'other')->count(),
            ],
            'filters' => [
                'meal_type' => $request->query('meal_type', ''),
                'school_id' => $request->query('school_id', ''),
                'valid_date' => $request->query('valid_date', ''),
                'status' => $request->query('status', ''),
                'extra_only' => $request->query('extra_only') === '1',
            ],
        ]));
    }

    /**
     * Issue individual 1-head food coupons from confirmed catering orders.
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

        $batchId = 'cat_' . Str::random(8);
        $created = 0;

        DB::transaction(function () use ($event, $orders, $batchId, &$created) {
            foreach ($orders as $order) {
                $headCount = (int) $order->head_count;
                if ($headCount <= 0) {
                    continue;
                }

                $alreadyIssued = (int) FestFoodCoupon::where('event_id', $event->id)
                    ->where('school_id', $order->school_id)
                    ->where('valid_date', $order->meal_date)
                    ->where('meal_type', $order->meal_type)
                    ->where('is_extra', false)
                    ->sum('head_count');

                $toCreate = max(0, $headCount - $alreadyIssued);

                for ($i = 0; $i < $toCreate; $i++) {
                    $codeData = FestFoodCoupon::generateSerializedCode($event, $order->meal_type);

                    FestFoodCoupon::create([
                        'event_id'    => $event->id,
                        'school_id'   => $order->school_id,
                        'coupon_code' => $codeData['code'],
                        'sequence_no' => $codeData['sequence_no'],
                        'qr_token'    => FestFoodCoupon::generateQrToken(),
                        'meal_type'   => $order->meal_type,
                        'valid_date'  => $order->meal_date,
                        'head_count'  => 1,
                        'is_extra'    => false,
                        'batch_id'    => $batchId,
                        'status'      => 'issued',
                        'issued_at'   => now(),
                        'notes'       => $order->notes ?: "Catering order #{$order->id}",
                    ]);
                    $created++;
                }
            }
        });

        $audit->festEvent($event, FestPageActivity::FOOD_COUPONS, 'fest.food_coupons.issued', "{$created} food coupon(s) issued from confirmed catering orders", [
            'count' => $created,
            'batch_id' => $batchId,
        ]);

        return back()->with('success', "{$created} food coupon(s) issued from confirmed catering orders.");
    }

    /**
     * Issue food coupons from settled food bills.
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

        $batchId = 'bill_' . Str::random(8);
        $created = 0;

        DB::transaction(function () use ($event, $bills, $batchId, &$created) {
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

                    $alreadyIssued = (int) FestFoodCoupon::where('event_id', $event->id)
                        ->where('school_id', $bill->school_id)
                        ->where('valid_date', $menuDate)
                        ->where('meal_type', $mealType)
                        ->where('is_extra', false)
                        ->sum('head_count');

                    $toCreate = max(0, $headCount - $alreadyIssued);

                    for ($i = 0; $i < $toCreate; $i++) {
                        $codeData = FestFoodCoupon::generateSerializedCode($event, $mealType);

                        FestFoodCoupon::create([
                            'event_id'    => $event->id,
                            'school_id'   => $bill->school_id,
                            'coupon_code' => $codeData['code'],
                            'sequence_no' => $codeData['sequence_no'],
                            'qr_token'    => FestFoodCoupon::generateQrToken(),
                            'meal_type'   => $mealType,
                            'valid_date'  => $menuDate,
                            'head_count'  => 1,
                            'is_extra'    => false,
                            'batch_id'    => $batchId,
                            'status'      => 'issued',
                            'issued_at'   => now(),
                            'notes'       => "Issued from priced food-menu order (bill #{$bill->id})",
                        ]);
                        $created++;
                    }
                }
            }
        });

        $audit->festEvent($event, FestPageActivity::FOOD_COUPONS, 'fest.food_coupons.issued', "{$created} food coupon(s) issued from food bills", [
            'count' => $created,
            'batch_id' => $batchId,
            'require_payment' => $requirePayment,
        ]);

        if ($created === 0) {
            if ($requirePayment && $bills->isEmpty()) {
                return back()->with('error', 'No settled food orders found for this event to issue coupons from. Ensure participating schools have placed orders and payments are approved.');
            }
            return back()->with('info', 'All food coupons for settled orders have already been issued.');
        }

        return back()->with('success', "{$created} food coupon(s) issued from ".($requirePayment ? 'settled' : 'open').' food bills.');
    }

    /**
     * Admin generator for extra/buffer food coupons.
     */
    public function generateExtra(string $tenantId, FestEvent $event, Request $request, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'meal_type'  => 'required|string|in:breakfast,lunch,dinner,snacks,tea,other',
            'valid_date' => 'required|date',
            'quantity'   => 'required|integer|min:1|max:500',
            'school_id'  => 'nullable|string',
            'notes'      => 'nullable|string|max:255',
        ]);

        $batchId = 'extra_' . Str::random(8);
        $quantity = (int) $data['quantity'];
        $created = 0;

        DB::transaction(function () use ($event, $data, $batchId, $quantity, &$created) {
            for ($i = 0; $i < $quantity; $i++) {
                $codeData = FestFoodCoupon::generateSerializedCode($event, $data['meal_type']);

                FestFoodCoupon::create([
                    'event_id'    => $event->id,
                    'school_id'   => !empty($data['school_id']) ? $data['school_id'] : null,
                    'coupon_code' => $codeData['code'],
                    'sequence_no' => $codeData['sequence_no'],
                    'qr_token'    => FestFoodCoupon::generateQrToken(),
                    'meal_type'   => $data['meal_type'],
                    'valid_date'  => $data['valid_date'],
                    'head_count'  => 1,
                    'is_extra'    => true,
                    'batch_id'    => $batchId,
                    'status'      => 'issued',
                    'issued_at'   => now(),
                    'notes'       => !empty($data['notes']) ? $data['notes'] : 'Admin extra / buffer',
                ]);
                $created++;
            }
        });

        $audit->festEvent($event, FestPageActivity::FOOD_COUPONS, 'fest.food_coupons.extra_generated', "{$created} extra food coupon(s) generated for {$data['meal_type']}", [
            'count' => $created,
            'meal_type' => $data['meal_type'],
            'batch_id' => $batchId,
        ]);

        return back()->with('success', "{$created} extra food coupon(s) generated successfully.");
    }

    /**
     * Ungenerate / delete unredeemed coupons.
     */
    public function ungenerate(string $tenantId, FestEvent $event, Request $request, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'scope'      => 'required|string|in:all_unredeemed,extra_only,by_meal_type,selected',
            'meal_type'  => 'nullable|string|in:breakfast,lunch,dinner,snacks,tea,other',
            'valid_date' => 'nullable|date',
            'coupon_ids' => 'nullable|array',
            'coupon_ids.*' => 'integer',
        ]);

        $query = FestFoodCoupon::where('event_id', $event->id)
            ->where('status', 'issued'); // NEVER delete redeemed coupons

        if ($data['scope'] === 'extra_only') {
            $query->where('is_extra', true);
        } elseif ($data['scope'] === 'by_meal_type') {
            if (!empty($data['meal_type'])) {
                $query->where('meal_type', $data['meal_type']);
            }
            if (!empty($data['valid_date'])) {
                $query->where('valid_date', $data['valid_date']);
            }
        } elseif ($data['scope'] === 'selected') {
            $ids = $data['coupon_ids'] ?? [];
            if (empty($ids)) {
                return back()->with('error', 'No coupons were selected to ungenerate.');
            }
            $query->whereIn('id', $ids);
        }

        $deletedCount = $query->delete();

        $audit->festEvent($event, FestPageActivity::FOOD_COUPONS, 'fest.food_coupons.ungenerated', "{$deletedCount} unredeemed food coupon(s) ungenerated/removed", [
            'count' => $deletedCount,
            'scope' => $data['scope'],
        ]);

        return back()->with('success', "{$deletedCount} unredeemed coupon(s) removed.");
    }

    /**
     * Upload template background image for 10-per-A4 sheet coupons.
     */
    public function uploadTemplateBackground(string $tenantId, FestEvent $event, Request $request, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $request->validate([
            'background_image' => 'required|image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        $path = TenantStorage::storeUploadedFile(
            $request->file('background_image'),
            "events/{$event->id}/food-coupon-template",
            'public'
        );

        $event->update(['food_coupon_bg_image' => $path]);

        $audit->festEvent($event, FestPageActivity::FOOD_COUPONS, 'fest.food_coupons.template_updated', 'Food coupon template background image uploaded', [
            'path' => $path,
        ]);

        return back()->with('success', 'Food coupon template background image saved.');
    }

    /**
     * Remove template background image.
     */
    public function removeTemplateBackground(string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $event->update(['food_coupon_bg_image' => null]);

        $audit->festEvent($event, FestPageActivity::FOOD_COUPONS, 'fest.food_coupons.template_removed', 'Food coupon template background image removed', []);

        return back()->with('success', 'Food coupon template background image removed.');
    }

    /**
     * Mark a coupon as redeemed.
     */
    public function redeem(string $tenantId, FestEvent $event, FestFoodCoupon $coupon, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($coupon->event_id !== $event->id, 404);
        abort_if($coupon->status !== 'issued', 422, 'Only issued coupons can be redeemed.');

        $coupon->update(['status' => 'redeemed', 'redeemed_at' => now()]);

        $audit->festEvent($event, FestPageActivity::FOOD_COUPONS, 'fest.food_coupon.redeemed', 'Food coupon marked redeemed', [
            'coupon_id' => $coupon->id,
            'coupon_code' => $coupon->coupon_code,
        ]);

        return back()->with('success', "Coupon {$coupon->coupon_code} marked redeemed.");
    }

    /**
     * Print issued coupons in 10-per-A4 sheet format.
     */
    public function print(string $tenantId, FestEvent $event, Request $request, FestIdCardQrService $qrService)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $query = FestFoodCoupon::where('event_id', $event->id)
            ->where('status', 'issued')
            ->with('school');

        if ($meal = $request->query('meal_type')) {
            $query->where('meal_type', $meal);
        }
        if ($schoolId = $request->query('school_id')) {
            $query->where('school_id', $schoolId);
        }
        if ($date = $request->query('valid_date')) {
            $query->where('valid_date', $date);
        }
        if ($request->query('extra_only') === '1') {
            $query->where('is_extra', true);
        }

        $coupons = $query->orderBy('meal_type')
            ->orderBy('sequence_no')
            ->get();

        if ($coupons->isEmpty()) {
            return back()->with('error', 'No issued food coupons match the selected filter criteria to print.');
        }

        $bgDataUri = $event->foodCouponBgImageDataUri($this->sahodaya);
        $baseUrl = url('/');

        $preparedCoupons = [];
        foreach ($coupons as $c) {
            $verifyUrl = $c->verificationUrl($baseUrl);
            $qrData = $qrService->dataUri($verifyUrl);
            $headCount = max(1, (int) $c->head_count);

            for ($i = 0; $i < $headCount; $i++) {
                $subCode = $headCount > 1 ? ($c->coupon_code . '-' . ($i + 1)) : $c->coupon_code;
                $preparedCoupons[] = [
                    'id' => $c->id,
                    'coupon_code' => $subCode,
                    'qr_token' => $c->qr_token,
                    'meal_type' => $c->meal_type,
                    'formatted_date' => $c->valid_date?->format('d M Y') ?? 'N/A',
                    'head_count' => 1,
                    'is_extra' => $c->is_extra,
                    'school_name' => $c->school_display_name,
                    'qr_src' => $qrData,
                ];
            }
        }

        return Pdf::loadView('fest.catering.food-coupons', [
            'event'     => $event,
            'sahodaya'  => $this->sahodaya,
            'logoSrc'   => TenantBranding::logoEmbedSrc($this->sahodaya),
            'bgDataUri' => $bgDataUri,
            'coupons'   => $preparedCoupons,
        ])->setPaper('a4', 'portrait')
          ->download('food-coupons-'.$event->id.'.pdf');
    }
}
