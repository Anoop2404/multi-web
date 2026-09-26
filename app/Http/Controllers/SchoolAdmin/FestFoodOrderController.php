<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\FestFoodBill;
use App\Models\FestFoodMenuItem;
use App\Models\FestFoodOrderItem;
use App\Models\FestFoodPayment;
use App\Models\Tenant;
use App\Services\Events\FestRegistrationRouterService;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class FestFoodOrderController extends SchoolAdminController
{
    private function foodCutoffAt(FestEvent $event)
    {
        if (! $event->phase_mode_enabled || ! $event->source_phase_id) {
            return null;
        }

        return FestEventPhase::where('event_id', $event->id)
            ->where('source_phase_id', $event->source_phase_id)
            ->first()?->food_cutoff_at;
    }

    /** @return array{opens_at: ?Carbon, closes_at: ?Carbon, status: string} */
    private function orderingWindow(FestEvent $event): array
    {
        $phaseCutoff = $this->foodCutoffAt($event);
        $closesAt = $event->food_order_closes_at;

        // A phase cutoff is a competition-level hard stop. An event-level ordering
        // window may close earlier, but cannot silently extend beyond that cutoff.
        if ($phaseCutoff && (! $closesAt || $phaseCutoff->lt($closesAt))) {
            $closesAt = $phaseCutoff;
        }

        $status = 'open';
        if ($closesAt && now()->gt($closesAt)) {
            $status = 'closed';
        } elseif ($event->food_order_opens_at && now()->lt($event->food_order_opens_at)) {
            $status = 'upcoming';
        }

        return [
            'opens_at' => $event->food_order_opens_at,
            'closes_at' => $closesAt,
            'status' => $status,
        ];
    }

    /** @param array{opens_at: ?Carbon, closes_at: ?Carbon, status: string} $eventWindow */
    private function dayOrderingWindow(FestEvent $event, string $menuDate, array $eventWindow): array
    {
        $configured = ($event->food_order_day_windows ?? [])[$menuDate] ?? null;
        if (! is_array($configured)) {
            return $eventWindow;
        }

        $opensAt = $eventWindow['opens_at'];
        $closesAt = $eventWindow['closes_at'];
        $dayOpensAt = filled($configured['opens_at'] ?? null) ? Carbon::parse($configured['opens_at']) : null;
        $dayClosesAt = filled($configured['closes_at'] ?? null) ? Carbon::parse($configured['closes_at']) : null;

        // Daily windows narrow the event-wide window; they never broaden it.
        if ($dayOpensAt && (! $opensAt || $dayOpensAt->gt($opensAt))) {
            $opensAt = $dayOpensAt;
        }
        if ($dayClosesAt && (! $closesAt || $dayClosesAt->lt($closesAt))) {
            $closesAt = $dayClosesAt;
        }

        $status = 'open';
        if ($closesAt && now()->gt($closesAt)) {
            $status = 'closed';
        } elseif ($opensAt && now()->lt($opensAt)) {
            $status = 'upcoming';
        }

        return [
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
            'status' => $status,
        ];
    }

    private function assertOrderingOpen(FestEvent $event, ?string $menuDate = null): void
    {
        $window = $this->orderingWindow($event);
        if ($menuDate !== null) {
            $window = $this->dayOrderingWindow($event, $menuDate, $window);
        }

        $scope = $menuDate ? ' for '.Carbon::parse($menuDate)->format('d M Y') : '';
        abort_if(
            $window['status'] === 'upcoming',
            422,
            'Food ordering'.$scope.' has not opened yet. Ordering starts '.$window['opens_at']?->format('d M Y, h:i A').'.'
        );
        abort_if($window['status'] === 'closed', 422, 'Food ordering'.$scope.' has closed.');
    }

    private function assertAccess(FestEvent $event, bool $enforceFoodCutoff = true): void
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        // Regions: a school may only order food against its OWN assigned region/finale
        // partition — not the hub directly, and not a sibling region's child event. Food
        // ordering previously had zero partition awareness (see Phase 1 audit — "Reject
        // direct hub and sibling-region ... food ... requests").
        app(FestRegistrationRouterService::class)->assertSchoolCanAccess($event, $this->school->id);

        if ($enforceFoodCutoff) {
            $this->assertOrderingOpen($event);
        }
    }

    public function show(string $tenantId, FestEvent $event)
    {
        // Keep the page readable after the cutoff so schools can review their order,
        // balance, and payment history. Mutating actions still enforce the cutoff.
        $this->assertAccess($event, false);
        $window = $this->orderingWindow($event);

        // Sorted in PHP, not via orderBy('meal_type') — see
        // FestFoodMenuItem::sortForDisplay() for why a SQL sort would be wrong here.
        $menuItems = FestFoodMenuItem::sortForDisplay(
            FestFoodMenuItem::forEvent($event->id)->where('is_available', true)->get()
        );
        $dayWindows = collect(array_keys($event->food_order_day_windows ?? []))
            ->mapWithKeys(function (string $date) use ($event, $window) {
                $dayWindow = $this->dayOrderingWindow($event, $date, $window);

                return [$date => [
                    'opens_at' => $dayWindow['opens_at']?->toIso8601String(),
                    'closes_at' => $dayWindow['closes_at']?->toIso8601String(),
                    'status' => $dayWindow['status'],
                ]];
            });

        $bill = FestFoodBill::where('event_id', $event->id)->where('school_id', $this->school->id)->first();
        $bill?->load(['orderItems', 'payments']);

        // Prefer the bill's OWN snapshotted payee (set once at FestFoodBill::
        // firstOrCreateForSchool() and never rewritten by a later event-setting change —
        // see that method's docblock) so this matches exactly who the bill is actually
        // payable to. Before the school has ordered anything (no bill yet), there's
        // nothing snapshotted, so fall back to the event's current setting.
        $payeeType = $bill?->payee_type ?? $event->food_payee_type;
        $hostSchoolId = $bill?->host_school_id ?? $event->food_host_school_id;

        $hostSchool = $payeeType === 'host_school' && $hostSchoolId
            ? Tenant::find($hostSchoolId)
            : null;

        $payeeDetails = null;
        if ($payeeType === 'host_school' && $hostSchool) {
            $payeeDetails = [
                'name' => $hostSchool->name,
                ...$hostSchool->paymentDetails(),
                'qr_code_url' => $hostSchool->paymentQrCodeUrl(),
            ];
        } elseif ($payeeType !== 'host_school') {
            $sahodayaProfile = \App\Models\SahodayaProfile::where('tenant_id', $event->tenant_id)->first();
            if ($sahodayaProfile) {
                $payeeDetails = [
                    'name' => Tenant::where('id', $event->tenant_id)->value('name'),
                    'bank_name' => $sahodayaProfile->payment_bank_name,
                    'account_no' => $sahodayaProfile->payment_account_no,
                    'ifsc' => $sahodayaProfile->payment_ifsc,
                    'upi' => $sahodayaProfile->payment_upi,
                    'qr_code_url' => $sahodayaProfile->paymentQrCodeUrl(),
                ];
            }
        }
        // No bank/UPI/QR field is worth showing an empty "Where to pay" card for.
        if ($payeeDetails && ! array_filter(array_intersect_key($payeeDetails, array_flip(['bank_name', 'account_no', 'ifsc', 'upi', 'qr_code_url'])))) {
            $payeeDetails = null;
        }

        return $this->inertia('School/Fest/FoodOrder', [
            'event' => $event->only('id', 'title', 'event_type', 'event_start', 'event_end', 'food_payee_type', 'food_host_school_id'),
            'hierarchy' => $event->hierarchyContext(),
            'menuItems' => $menuItems,
            'mealTypes' => FestFoodMenuItem::MEAL_TYPES,
            'bill' => $bill ? [
                ...$bill->only(['id', 'status', 'amount_total', 'amount_paid']),
                'balance_due' => $bill->balanceDue(),
            ] : null,
            'orderItems' => $bill?->orderItems ?? [],
            'payments' => $bill?->payments->map(fn (\App\Models\FestFoodPayment $p) => [
                ...$p->only(['id', 'amount', 'payment_mode', 'receipt_number', 'status', 'transaction_ref', 'bank_name', 'received_at', 'submitted_at', 'rejection_reason']),
                'has_proof' => (bool) $p->proof_path,
            ]) ?? [],
            'payeeLabel' => $payeeType === 'host_school'
                ? ($hostSchool ? "Payable to {$hostSchool->name} (host school)" : 'Payable to the host school')
                : 'Payable to Sahodaya',
            'payeeDetails' => $payeeDetails,
            // foodCutoffAt is retained as an alias for older front-end clients while the
            // explicit opening/closing props describe the complete window.
            'foodCutoffAt' => $window['closes_at']?->toIso8601String(),
            'foodOrderOpensAt' => $window['opens_at']?->toIso8601String(),
            'foodOrderClosesAt' => $window['closes_at']?->toIso8601String(),
            'orderingStatus' => $window['status'],
            'orderingOpen' => $window['status'] === 'open',
            'foodOrderDayWindows' => $dayWindows,
        ]);
    }

    public function addItem(Request $request, string $tenantId, FestEvent $event)
    {
        $this->assertAccess($event, false);

        $data = $request->validate([
            'menu_item_id' => 'required|integer|exists:fest_food_menu_items,id',
            'quantity' => 'required|integer|min:1|max:9999',
        ]);

        $menuItem = FestFoodMenuItem::where('event_id', $event->id)
            ->where('is_available', true)
            ->findOrFail($data['menu_item_id']);
        $this->assertOrderingOpen($event, $menuItem->menu_date->format('Y-m-d'));

        $bill = FestFoodBill::firstOrCreateForSchool($event, $this->school->id);
        abort_if($bill->status !== FestFoodBill::STATUS_OPEN, 422, 'Your food bill for this event is already settled — contact the Sahodaya to reopen it.');

        if ($menuItem->max_per_school) {
            $existingQty = $bill->orderItems()->where('menu_item_id', $menuItem->id)->sum('quantity');
            abort_if($existingQty + $data['quantity'] > $menuItem->max_per_school, 422, "Only {$menuItem->max_per_school} of '{$menuItem->name}' allowed per school.");
        }

        $bill->orderItems()->create(FestFoodOrderItem::fromMenuItem($menuItem, $data['quantity'], $request->user()->id));
        $bill->recalculate();

        return back()->with('success', 'Added to your order.');
    }

    public function removeItem(string $tenantId, FestEvent $event, FestFoodOrderItem $orderItem)
    {
        $this->assertAccess($event, false);

        $bill = FestFoodBill::where('event_id', $event->id)->where('school_id', $this->school->id)->firstOrFail();
        abort_if($orderItem->bill_id !== $bill->id, 404);
        $this->assertOrderingOpen($event, $orderItem->menu_date->format('Y-m-d'));

        $bill->removeOrderItem($orderItem);

        return back()->with('success', 'Removed from your order.');
    }

    /**
     * A school submitting its own payment claim — proof upload + transaction ref, reviewed
     * by whoever this bill is actually payable to (Sahodaya or the designated host school).
     * Sits as 'pending' and doesn't touch the bill's balance until approved — see
     * FestFoodPayment::submitForBill().
     */
    public function submitPayment(Request $request, string $tenantId, FestEvent $event)
    {
        $this->assertAccess($event);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:9999999.99',
            'payment_mode' => ['required', Rule::in(['upi', 'bank_transfer', 'cash', 'other'])],
            'transaction_ref' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:100',
            'proof' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'notes' => 'nullable|string|max:500',
        ]);

        $bill = FestFoodBill::firstOrCreateForSchool($event, $this->school->id);
        abort_if($bill->status !== FestFoodBill::STATUS_OPEN, 422, 'Your food bill for this event is already settled — contact the Sahodaya to reopen it.');

        $proofPath = TenantStorage::storeUploadedFile($request->file('proof'), "food-payments/{$this->school->id}");

        $payment = FestFoodPayment::submitForBill(
            $bill,
            (float) $data['amount'],
            $data['payment_mode'],
            $data['transaction_ref'] ?? null,
            $data['bank_name'] ?? null,
            $proofPath,
            $data['notes'] ?? null,
            $request->user()->id,
        );

        return back()->with('success', "Payment of ₹{$payment->amount} submitted for review.");
    }

    /** Proof file for one of THIS school's own payment submissions. */
    public function paymentProof(string $tenantId, FestEvent $event, FestFoodPayment $payment)
    {
        // Payment evidence remains part of the read-only history after ordering closes.
        $this->assertAccess($event, false);

        $bill = FestFoodBill::where('event_id', $event->id)->where('school_id', $this->school->id)->firstOrFail();
        abort_if($payment->bill_id !== $bill->id, 404);
        abort_unless($payment->proof_path, 404);

        return TenantStorage::downloadResponse($this->school, $payment->proof_path);
    }
}
