<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestFoodBill;
use App\Models\FestFoodMenuItem;
use App\Models\FestFoodOrderItem;
use App\Models\FestFoodPayment;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\FestPartitionService;
use App\Services\Exports\CsvExportDispatcher;
use App\Support\FestPageActivity;
use App\Support\TenantBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FestFoodBillingController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event, FestPartitionService $partitions)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $bills = FestFoodBill::forTenant($this->sahodaya->id)
            ->where('event_id', $event->id)
            ->withCount('orderItems')
            ->orderByDesc('updated_at')
            ->get();

        $schools = Tenant::whereIn('id', $bills->pluck('school_id')->unique())->pluck('name', 'id');
        $hostSchoolName = $event->food_host_school_id
            ? Tenant::where('id', $event->food_host_school_id)->value('name')
            : null;

        // On a partitioned hub, bills live on each region's own child event — this event's
        // own row is empty by construction, so surface a cross-region rollup instead of
        // an empty page. See docs/REGION_SCOPED_ADMIN_AND_EVENT_FLOW_PLAN.md §2.9 (Gap J).
        $isPartitionedHub = $partitions->isPartitionedHub($event);
        $regionFoodSummary = $isPartitionedHub ? $partitions->combinedFoodSummary($event) : null;

        return $this->inertia('Sahodaya/Events/FoodBilling', $this->withEventActivity($event, FestPageActivity::FOOD_BILLING, [
            'event' => $event->only('id', 'title', 'food_payee_type', 'food_host_school_id'),
            'hostSchoolName' => $hostSchoolName,
            'isPartitionedHub' => $isPartitionedHub,
            'regionFoodSummary' => $regionFoodSummary,
            'bills' => $bills->map(fn (FestFoodBill $b) => [
                'id' => $b->id,
                'school_id' => $b->school_id,
                'school_name' => $schools[$b->school_id] ?? $b->school_id,
                'status' => $b->status,
                'payment_mode' => $b->payment_mode,
                'amount_total' => (float) $b->amount_total,
                'amount_paid' => (float) $b->amount_paid,
                'balance_due' => $b->balanceDue(),
                'items_count' => $b->order_items_count,
                'updated_at' => $b->updated_at,
            ]),
            'summary' => [
                'total' => (float) $bills->sum('amount_total'),
                'paid' => (float) $bills->sum('amount_paid'),
                'balance' => (float) $bills->sum(fn (FestFoodBill $b) => $b->balanceDue()),
            ],
            'schoolOptions' => Tenant::whereIn('id', $this->regionScopedSchoolIds(
                Tenant::where('parent_id', $this->sahodaya->id)->where('type', 'school')->pluck('id')->all()
            ))
                ->orderBy('name')
                ->get(['id', 'name']),
        ]));
    }

    /** Open a bill for a school (e.g. before the school has ordered anything themselves, or for a walk-in). */
    public function store(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'school_id' => [
                'required',
                Rule::exists('tenants', 'id')->where('parent_id', $this->sahodaya->id)->where('type', 'school'),
            ],
        ]);

        abort_if($this->regionScopedSchoolIds([$data['school_id']]) === [], 403, 'This school is outside your assigned region.');

        $bill = FestFoodBill::firstOrCreateForSchool($event, $data['school_id']);

        return redirect("/sahodaya-admin/{$this->sahodaya->id}/events/{$event->id}/food-billing/{$bill->id}");
    }

    public function show(string $tenantId, FestEvent $event, FestFoodBill $bill)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($bill->event_id !== $event->id, 404);

        $bill->load(['orderItems', 'payments']);
        $school = Tenant::find($bill->school_id);
        $hostSchool = $bill->host_school_id ? Tenant::find($bill->host_school_id) : null;

        $menuItems = FestFoodMenuItem::forEvent($event->id)
            ->where('is_available', true)
            ->orderBy('menu_date')->orderBy('meal_type')->orderBy('name')
            ->get();

        return $this->inertia('Sahodaya/Events/FoodBillingShow', $this->withEventActivity($event, FestPageActivity::FOOD_BILLING, [
            'event' => $event->only('id', 'title'),
            'bill' => [
                ...$bill->only(['id', 'status', 'payment_mode', 'payee_type', 'amount_total', 'amount_paid', 'notes']),
                'balance_due' => $bill->balanceDue(),
                'school_name' => $school?->name ?? $bill->school_id,
                'host_school_name' => $hostSchool?->name,
            ],
            'orderItems' => $bill->orderItems,
            'payments' => $bill->payments,
            'menuItems' => $menuItems,
        ]));
    }

    public function addItem(Request $request, string $tenantId, FestEvent $event, FestFoodBill $bill, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($bill->event_id !== $event->id, 404);
        abort_if($bill->status !== FestFoodBill::STATUS_OPEN, 422, 'This bill is settled/cancelled and no longer editable.');

        $data = $request->validate([
            'menu_item_id' => 'required|integer|exists:fest_food_menu_items,id',
            'quantity' => 'required|integer|min:1|max:9999',
        ]);

        $menuItem = FestFoodMenuItem::where('event_id', $event->id)->findOrFail($data['menu_item_id']);

        if ($menuItem->max_per_school) {
            $existingQty = $bill->orderItems()->where('menu_item_id', $menuItem->id)->sum('quantity');
            abort_if($existingQty + $data['quantity'] > $menuItem->max_per_school, 422, "Only {$menuItem->max_per_school} of '{$menuItem->name}' allowed per school.");
        }

        $bill->orderItems()->create(FestFoodOrderItem::fromMenuItem($menuItem, $data['quantity'], $request->user()->id));
        $bill->recalculate();

        $audit->festEvent($event, FestPageActivity::FOOD_BILLING, 'fest.food_billing.item_added', "{$data['quantity']} x {$menuItem->name} added to bill", [
            'bill_id' => $bill->id,
        ]);

        return back()->with('success', 'Item added.');
    }

    public function removeItem(string $tenantId, FestEvent $event, FestFoodBill $bill, FestFoodOrderItem $orderItem, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($bill->event_id !== $event->id, 404);
        abort_if($orderItem->bill_id !== $bill->id, 404);
        abort_if($bill->status !== FestFoodBill::STATUS_OPEN, 422, 'This bill is settled/cancelled and no longer editable.');

        $name = $orderItem->item_name;
        $orderItem->delete();
        $bill->recalculate();

        $audit->festEvent($event, FestPageActivity::FOOD_BILLING, 'fest.food_billing.item_removed', "'{$name}' removed from bill", [
            'bill_id' => $bill->id,
        ]);

        return back()->with('success', 'Item removed.');
    }

    public function recordPayment(Request $request, string $tenantId, FestEvent $event, FestFoodBill $bill, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($bill->event_id !== $event->id, 404);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:9999999.99',
            'payment_mode' => ['required', Rule::in(['cash', 'upi', 'bank_transfer', 'other'])],
            'notes' => 'nullable|string|max:500',
        ]);

        // recordForBill() enforces (under a row lock, so it's race-safe): the bill isn't
        // already settled/cancelled, and the amount doesn't exceed the outstanding
        // balance — see FestFoodPayment::recordForBill()'s docblock (Phase 4 audit items
        // 1, 2, 5). Previously neither check existed at all.
        $payment = FestFoodPayment::recordForBill(
            $bill,
            (float) $data['amount'],
            $data['payment_mode'],
            $data['notes'] ?? null,
            $request->user()->id,
        );

        $audit->festEvent($event, FestPageActivity::FOOD_BILLING, 'fest.food_billing.payment_recorded', "Payment of ₹{$data['amount']} recorded ({$payment->receipt_number})", [
            'bill_id' => $bill->id,
            'payment_id' => $payment->id,
        ]);

        return back()->with('success', "Payment recorded ({$payment->receipt_number}).");
    }

    public function voidPayment(string $tenantId, FestEvent $event, FestFoodBill $bill, FestFoodPayment $payment, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($bill->event_id !== $event->id, 404);
        abort_if($payment->bill_id !== $bill->id, 404);

        $receiptNumber = $payment->receipt_number;
        $amount = (float) $payment->amount;
        $payment->voidPayment();

        $audit->festEvent($event, FestPageActivity::FOOD_BILLING, 'fest.food_billing.payment_voided', "Payment of ₹{$amount} voided ({$receiptNumber})", [
            'bill_id' => $bill->id,
        ]);

        return back()->with('success', "Payment {$receiptNumber} voided.");
    }

    public function settle(Request $request, string $tenantId, FestEvent $event, FestFoodBill $bill, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($bill->event_id !== $event->id, 404);
        abort_if($bill->balanceDue() > 0.0, 422, 'This bill has an outstanding balance of ₹'.number_format($bill->balanceDue(), 2).' — record the remaining payment before settling.');

        $bill->update([
            'status' => FestFoodBill::STATUS_SETTLED,
            'settled_at' => now(),
            'settled_by_user_id' => $request->user()->id,
        ]);

        $audit->festEvent($event, FestPageActivity::FOOD_BILLING, 'fest.food_billing.settled', 'Bill marked settled', [
            'bill_id' => $bill->id,
        ]);

        return back()->with('success', 'Bill settled.');
    }

    public function reopen(string $tenantId, FestEvent $event, FestFoodBill $bill, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($bill->event_id !== $event->id, 404);

        $bill->update(['status' => FestFoodBill::STATUS_OPEN, 'settled_at' => null, 'settled_by_user_id' => null]);

        $audit->festEvent($event, FestPageActivity::FOOD_BILLING, 'fest.food_billing.reopened', 'Bill reopened', [
            'bill_id' => $bill->id,
        ]);

        return back()->with('success', 'Bill reopened.');
    }

    /**
     * Cancel a bill entirely (e.g. the school withdrew from the event before ordering was
     * finalized). Distinct from reopen()'s settled→open transition — this is a terminal
     * state, same as FestRegistration's withdrawn. See Phase 4 audit item 6: STATUS_CANCELLED
     * existed as a constant and was defensively checked elsewhere, but nothing ever set it.
     */
    public function cancel(Request $request, string $tenantId, FestEvent $event, FestFoodBill $bill, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($bill->event_id !== $event->id, 404);
        abort_if($bill->status === FestFoodBill::STATUS_CANCELLED, 422, 'Bill is already cancelled.');
        abort_if($bill->amount_paid > 0, 422, 'This bill has payments recorded — void the payment(s) first so the refund is explicit, then cancel.');

        $bill->update(['status' => FestFoodBill::STATUS_CANCELLED]);

        $audit->festEvent($event, FestPageActivity::FOOD_BILLING, 'fest.food_billing.cancelled', 'Bill cancelled', [
            'bill_id' => $bill->id,
        ]);

        return back()->with('success', 'Bill cancelled.');
    }

    /**
     * Printable bill receipt. Uses manual model resolution rather than implicit route-model
     * binding — see BoardResultVerificationController::downloadPdf() for why: file/PDF
     * download routes in this app have been found to unreliably receive the resolved model
     * via implicit binding, so every such route resolves explicitly instead.
     */
    public function pdf(string $tenantId, FestEvent $event, string $bill)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        $bill = FestFoodBill::findOrFail($bill);
        abort_if($bill->event_id !== $event->id, 404);

        $bill->load(['orderItems', 'payments', 'school', 'hostSchool']);

        return Pdf::loadView('fest.food.bill', [
            'event' => $event,
            'sahodaya' => $this->sahodaya,
            'logoSrc' => TenantBranding::logoEmbedSrc($this->sahodaya),
            'bill' => $bill,
        ])->download('food-bill-'.($bill->school?->school_prefix ?: $bill->school_id).'-'.$event->id.'.pdf');
    }

    public function exportCsv(string $tenantId, FestEvent $event, CsvExportDispatcher $exports)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        // On a partitioned hub, bills live on each region's own child event — exporting
        // strictly by $event->id previously produced an empty CSV with no indication why,
        // even though the index page already surfaces a cross-region rollup for the same
        // hub (see index() above, Gap J). Matches every other reportableEventIds() fix in
        // this project (Phase 7 audit).
        $eventIds = $event->reportableEventIds();
        $bills = FestFoodBill::forTenant($this->sahodaya->id)->whereIn('event_id', $eventIds)->get();
        $schools = Tenant::whereIn('id', $bills->pluck('school_id')->unique())->pluck('name', 'id');
        $regionLabels = count($eventIds) > 1
            ? FestEvent::whereIn('id', $eventIds)->pluck('cluster_label', 'id')
            : collect();

        // CsvExportDispatcher casts any non-array row to array before calling $mapRow, which
        // mangles Eloquent models (private/protected props, not attributes) — so build plain
        // arrays here first, same convention as PaymentHistoryController::export.
        $rows = $bills->map(fn (FestFoodBill $b) => [
            'region' => $regionLabels[$b->event_id] ?? '',
            'school' => $schools[$b->school_id] ?? $b->school_id,
            'status' => $b->status,
            'payee' => $b->payee_type === 'host_school' ? 'Host school' : 'Sahodaya',
            'total' => (float) $b->amount_total,
            'paid' => (float) $b->amount_paid,
            'balance' => $b->balanceDue(),
        ])->values();

        $filename = 'food-billing-'.$event->id.'.csv';

        return $exports->dispatch(
            request()->user(),
            'fest_food_billing',
            $filename,
            $rows,
            ['Region', 'School', 'Status', 'Payee', 'Total', 'Paid', 'Balance due'],
            fn (array $r) => [
                $r['region'],
                $r['school'],
                $r['status'],
                $r['payee'],
                number_format($r['total'], 2, '.', ''),
                number_format($r['paid'], 2, '.', ''),
                number_format($r['balance'], 2, '.', ''),
            ],
        );
    }
}
