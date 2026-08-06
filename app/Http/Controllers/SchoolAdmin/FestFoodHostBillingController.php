<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\FestEvent;
use App\Models\FestFoodBill;
use App\Models\FestFoodMenuItem;
use App\Models\FestFoodOrderItem;
use App\Models\FestFoodPayment;
use App\Models\Tenant;
use App\Services\Exports\CsvExportDispatcher;
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
    private function assertIsHost(FestEvent $event): void
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        abort_unless(
            $event->food_payee_type === 'host_school' && $event->food_host_school_id === $this->school->id,
            403,
            'Your school is not the designated food payee for this event.'
        );
    }

    public function index(string $tenantId, FestEvent $event)
    {
        $this->assertIsHost($event);

        $bills = FestFoodBill::where('event_id', $event->id)
            ->withCount('orderItems')
            ->orderByDesc('updated_at')
            ->get();

        $schools = Tenant::whereIn('id', $bills->pluck('school_id')->unique())->pluck('name', 'id');

        return $this->inertia('School/Fest/FoodHostBilling', [
            'event' => $event->only('id', 'title'),
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

    public function show(string $tenantId, FestEvent $event, FestFoodBill $bill)
    {
        $this->assertIsHost($event);
        abort_if($bill->event_id !== $event->id, 404);

        $bill->load(['orderItems', 'payments']);
        $school = Tenant::find($bill->school_id);

        $menuItems = FestFoodMenuItem::forEvent($event->id)
            ->where('is_available', true)
            ->orderBy('menu_date')->orderBy('meal_type')->orderBy('name')
            ->get();

        return $this->inertia('School/Fest/FoodHostBillingShow', [
            'event' => $event->only('id', 'title'),
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

    public function addItem(Request $request, string $tenantId, FestEvent $event, FestFoodBill $bill)
    {
        $this->assertIsHost($event);
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

        return back()->with('success', 'Item added.');
    }

    public function removeItem(string $tenantId, FestEvent $event, FestFoodBill $bill, FestFoodOrderItem $orderItem)
    {
        $this->assertIsHost($event);
        abort_if($bill->event_id !== $event->id, 404);
        abort_if($orderItem->bill_id !== $bill->id, 404);
        abort_if($bill->status !== FestFoodBill::STATUS_OPEN, 422, 'This bill is settled/cancelled and no longer editable.');

        $orderItem->delete();
        $bill->recalculate();

        return back()->with('success', 'Item removed.');
    }

    public function recordPayment(Request $request, string $tenantId, FestEvent $event, FestFoodBill $bill)
    {
        $this->assertIsHost($event);
        abort_if($bill->event_id !== $event->id, 404);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:9999999.99',
            'payment_mode' => ['required', Rule::in(['cash', 'upi', 'bank_transfer', 'other'])],
            'notes' => 'nullable|string|max:500',
        ]);

        $payment = $bill->payments()->create([
            ...$data,
            'receipt_number' => FestFoodPayment::generateReceiptNumber($bill),
            'received_by_user_id' => $request->user()->id,
            'received_at' => now(),
        ]);
        $bill->recalculate();

        return back()->with('success', "Payment recorded ({$payment->receipt_number}).");
    }

    public function settle(string $tenantId, FestEvent $event, FestFoodBill $bill)
    {
        $this->assertIsHost($event);
        abort_if($bill->event_id !== $event->id, 404);

        $bill->update([
            'status' => FestFoodBill::STATUS_SETTLED,
            'settled_at' => now(),
            'settled_by_user_id' => request()->user()->id,
        ]);

        return back()->with('success', 'Bill settled.');
    }

    public function reopen(string $tenantId, FestEvent $event, FestFoodBill $bill)
    {
        $this->assertIsHost($event);
        abort_if($bill->event_id !== $event->id, 404);

        $bill->update(['status' => FestFoodBill::STATUS_OPEN, 'settled_at' => null, 'settled_by_user_id' => null]);

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

        $bills = FestFoodBill::where('event_id', $event->id)->get();
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
