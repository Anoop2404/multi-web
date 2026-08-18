<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\FestEvent;
use App\Models\FestFoodBill;
use App\Models\FestFoodMenuItem;
use App\Models\FestFoodOrderItem;
use App\Models\FestFoodPayment;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Exports\CsvExportDispatcher;
use App\Support\FestPageActivity;
use App\Support\TenantBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Food billing management for a school that has been designated the "host school" for an
 * event's food payments (FestEvent::food_payee_type === 'host_school' &&
 * food_host_school_id === this school). Mirrors SahodayaAdmin\FestFoodBillingController —
 * same operations, but only reachable by the one school the Sahodaya assigned as payee.
 * Sahodaya admins keep their own read/manage access via the Sahodaya-side page regardless.
 */
class FestFoodHostBillingController extends SchoolAdminController
{
    /**
     * Gate access to the FEATURE (can this school reach the food-host billing pages at
     * all for this event) — either as the event's CURRENT designated host, or as a
     * FORMER host with at least one bill still snapshotted to them (see
     * FestFoodBill::firstOrCreateForSchool()'s payee-snapshot doc). Individual bill
     * actions below additionally check the bill's OWN snapshot via assertBillBelongsToHost()
     * — this method alone is not sufficient to authorize access to a specific bill, since
     * the event's current host_school_id can change after bills already exist (Phase 4
     * audit item 4: the old version gated purely on the event's CURRENT host, which meant
     * a newly-designated host could see billing they were never actually payee for, while
     * changing the host silently locked the real original host out of their own history).
     */
    private function assertIsHost(FestEvent $event): void
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $isCurrentHost = $event->food_payee_type === 'host_school' && $event->food_host_school_id === $this->school->id;
        $wasFormerHost = FestFoodBill::where('event_id', $event->id)
            ->where('payee_type', 'host_school')
            ->where('host_school_id', $this->school->id)
            ->exists();

        abort_unless($isCurrentHost || $wasFormerHost, 403, 'Your school is not the designated food payee for this event.');
    }

    /**
     * Per-bill authorization: the bill must actually be snapshotted to THIS school as
     * payee — never derived from the event's current food_host_school_id, which may have
     * moved on since this bill was created. See assertIsHost()'s docblock above.
     */
    private function assertBillBelongsToHost(FestFoodBill $bill): void
    {
        abort_unless(
            $bill->payee_type === 'host_school' && $bill->host_school_id === $this->school->id,
            403,
            'This bill is not payable to your school.'
        );
    }

    public function index(string $tenantId, FestEvent $event)
    {
        $this->assertIsHost($event);

        // Scoped to bills actually snapshotted to THIS school as host — not every bill on
        // the event (see assertIsHost()'s docblock; the previous version showed all bills
        // regardless of payee, including 'sahodaya'-payee ones this school has no business
        // seeing).
        $bills = FestFoodBill::where('event_id', $event->id)
            ->where('payee_type', 'host_school')
            ->where('host_school_id', $this->school->id)
            ->withCount('orderItems')
            ->orderByDesc('updated_at')
            ->get();

        $schools = Tenant::whereIn('id', $bills->pluck('school_id')->unique())->pluck('name', 'id');

        return $this->inertia('School/Fest/FoodHostBilling', [
            'event' => $event->only('id', 'title'),
            'hierarchy' => $event->hierarchyContext(),
            'bills' => $bills->map(fn (FestFoodBill $b) => [
                'id' => $b->id,
                'school_name' => $schools[$b->school_id] ?? $b->school_id,
                'status' => $b->status,
                'amount_total' => (float) $b->amount_total,
                'amount_paid' => (float) $b->amount_paid,
                'balance_due' => $b->balanceDue(),
                'items_count' => $b->order_items_count,
            ]),
            'summary' => [
                'total' => (float) $bills->sum('amount_total'),
                'paid' => (float) $bills->sum('amount_paid'),
                'balance' => (float) $bills->sum(fn (FestFoodBill $b) => $b->balanceDue()),
            ],
        ]);
    }

    /**
     * Day x meal-type x item breakdown across every school's bill payable to this host —
     * same report as the Sahodaya side, scoped to bills snapshotted to this school as
     * payee. See FestFoodOrderItem::dayMealReport().
     */
    public function report(string $tenantId, FestEvent $event)
    {
        $this->assertIsHost($event);

        return $this->inertia('School/Fest/FoodHostBillingReport', [
            'event' => $event->only('id', 'title'),
            'hierarchy' => $event->hierarchyContext(),
            'report' => FestFoodOrderItem::dayMealReport($event->id, $this->school->id),
        ]);
    }

    public function reportExportCsv(string $tenantId, FestEvent $event, CsvExportDispatcher $exports)
    {
        $this->assertIsHost($event);

        $rows = [];
        foreach (FestFoodOrderItem::dayMealReport($event->id, $this->school->id) as $day) {
            foreach ($day['meals'] as $meal) {
                foreach ($meal['items'] as $item) {
                    $rows[] = [
                        'date' => $day['date'],
                        'meal_type' => $meal['meal_type'],
                        'item_name' => $item['item_name'],
                        'quantity' => $item['quantity'],
                        'revenue' => $item['revenue'],
                        'schools_count' => $item['schools_count'],
                    ];
                }
            }
        }

        return $exports->dispatch(
            request()->user(),
            'fest_food_billing_report',
            'food-order-report-'.$event->id.'.csv',
            $rows,
            ['Date', 'Meal', 'Item', 'Quantity', 'Revenue', 'Schools ordering'],
            fn (array $r) => [
                $r['date'],
                $r['meal_type'],
                $r['item_name'],
                $r['quantity'],
                number_format($r['revenue'], 2, '.', ''),
                $r['schools_count'],
            ],
        );
    }

    public function show(string $tenantId, FestEvent $event, FestFoodBill $bill)
    {
        $this->assertIsHost($event);
        abort_if($bill->event_id !== $event->id, 404);
        $this->assertBillBelongsToHost($bill);

        $bill->load(['orderItems', 'payments']);
        $school = Tenant::find($bill->school_id);

        $menuItems = FestFoodMenuItem::forEvent($event->id)
            ->where('is_available', true)
            ->orderBy('menu_date')->orderBy('meal_type')->orderBy('name')
            ->get();

        return $this->inertia('School/Fest/FoodHostBillingShow', [
            'event' => $event->only('id', 'title'),
            'hierarchy' => $event->hierarchyContext(),
            'bill' => [
                ...$bill->only(['id', 'status', 'amount_total', 'amount_paid', 'notes']),
                'balance_due' => $bill->balanceDue(),
                'school_name' => $school?->name ?? $bill->school_id,
            ],
            'orderItems' => $bill->orderItems,
            'payments' => $bill->payments,
            'menuItems' => $menuItems,
        ]);
    }

    public function addItem(Request $request, string $tenantId, FestEvent $event, FestFoodBill $bill, PlatformAuditLogger $audit)
    {
        $this->assertIsHost($event);
        abort_if($bill->event_id !== $event->id, 404);
        $this->assertBillBelongsToHost($bill);
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
        $this->assertIsHost($event);
        abort_if($bill->event_id !== $event->id, 404);
        $this->assertBillBelongsToHost($bill);
        abort_if($orderItem->bill_id !== $bill->id, 404);

        $name = $orderItem->item_name;
        $bill->removeOrderItem($orderItem);

        $audit->festEvent($event, FestPageActivity::FOOD_BILLING, 'fest.food_billing.item_removed', "'{$name}' removed from bill", [
            'bill_id' => $bill->id,
        ]);

        return back()->with('success', 'Item removed.');
    }

    public function recordPayment(Request $request, string $tenantId, FestEvent $event, FestFoodBill $bill, PlatformAuditLogger $audit)
    {
        $this->assertIsHost($event);
        abort_if($bill->event_id !== $event->id, 404);
        $this->assertBillBelongsToHost($bill);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:9999999.99',
            'payment_mode' => ['required', Rule::in(['cash', 'upi', 'bank_transfer', 'other'])],
            'notes' => 'nullable|string|max:500',
        ]);

        // See FestFoodPayment::recordForBill() — atomically enforces the bill isn't
        // settled/cancelled and the amount doesn't exceed the outstanding balance
        // (Phase 4 audit items 1, 2, 5).
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

    public function settle(Request $request, string $tenantId, FestEvent $event, FestFoodBill $bill, PlatformAuditLogger $audit)
    {
        $this->assertIsHost($event);
        abort_if($bill->event_id !== $event->id, 404);
        $this->assertBillBelongsToHost($bill);

        $bill->settle($request->user()->id);

        $audit->festEvent($event, FestPageActivity::FOOD_BILLING, 'fest.food_billing.settled', 'Bill marked settled', [
            'bill_id' => $bill->id,
        ]);

        return back()->with('success', 'Bill settled.');
    }

    public function voidPayment(string $tenantId, FestEvent $event, FestFoodBill $bill, FestFoodPayment $payment, PlatformAuditLogger $audit)
    {
        $this->assertIsHost($event);
        abort_if($bill->event_id !== $event->id, 404);
        $this->assertBillBelongsToHost($bill);
        abort_if($payment->bill_id !== $bill->id, 404);

        $receiptNumber = $payment->receipt_number;
        $amount = (float) $payment->amount;
        $payment->voidPayment();

        $audit->festEvent($event, FestPageActivity::FOOD_BILLING, 'fest.food_billing.payment_voided', "Payment of ₹{$amount} voided ({$receiptNumber})", [
            'bill_id' => $bill->id,
        ]);

        return back()->with('success', "Payment {$receiptNumber} voided.");
    }

    public function reopen(string $tenantId, FestEvent $event, FestFoodBill $bill, PlatformAuditLogger $audit)
    {
        $this->assertIsHost($event);
        abort_if($bill->event_id !== $event->id, 404);
        $this->assertBillBelongsToHost($bill);

        $bill->reopen();

        $audit->festEvent($event, FestPageActivity::FOOD_BILLING, 'fest.food_billing.reopened', 'Bill reopened', [
            'bill_id' => $bill->id,
        ]);

        return back()->with('success', 'Bill reopened.');
    }

    /**
     * Printable bill receipt. Resolves the model manually rather than relying on implicit
     * route-model binding — see BoardResultVerificationController::downloadPdf() for why
     * file/PDF download routes in this app need that defensive pattern.
     */
    public function pdf(string $tenantId, FestEvent $event, string $bill)
    {
        $this->assertIsHost($event);
        $bill = FestFoodBill::findOrFail($bill);
        abort_if($bill->event_id !== $event->id, 404);
        $this->assertBillBelongsToHost($bill);

        $bill->load(['orderItems', 'payments', 'school', 'hostSchool']);
        $sahodaya = Tenant::find($event->tenant_id);

        return Pdf::loadView('fest.food.bill', [
            'event' => $event,
            'sahodaya' => $sahodaya,
            'logoSrc' => $sahodaya ? TenantBranding::logoEmbedSrc($sahodaya) : null,
            'bill' => $bill,
        ])->download('food-bill-'.($bill->school?->school_prefix ?: $bill->school_id).'-'.$event->id.'.pdf');
    }

    public function exportCsv(string $tenantId, FestEvent $event, CsvExportDispatcher $exports)
    {
        $this->assertIsHost($event);

        $bills = FestFoodBill::where('event_id', $event->id)
            ->where('payee_type', 'host_school')
            ->where('host_school_id', $this->school->id)
            ->get();
        $schools = Tenant::whereIn('id', $bills->pluck('school_id')->unique())->pluck('name', 'id');

        $rows = $bills->map(fn (FestFoodBill $b) => [
            'school' => $schools[$b->school_id] ?? $b->school_id,
            'status' => $b->status,
            'total' => (float) $b->amount_total,
            'paid' => (float) $b->amount_paid,
            'balance' => $b->balanceDue(),
        ])->values();

        return $exports->dispatch(
            request()->user(),
            'fest_food_billing',
            'food-billing-'.$event->id.'.csv',
            $rows,
            ['School', 'Status', 'Total', 'Paid', 'Balance due'],
            fn (array $r) => [
                $r['school'],
                $r['status'],
                number_format($r['total'], 2, '.', ''),
                number_format($r['paid'], 2, '.', ''),
                number_format($r['balance'], 2, '.', ''),
            ],
        );
    }
}
