<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionReceipt;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SubscriptionController extends Controller
{
    public function index()
    {
        $plans = SubscriptionPlan::orderBy('price_inr')->get();

        $subscriptions = TenantSubscription::with(['tenant', 'plan'])
            ->latest()
            ->paginate(20);

        $pendingReceipts = SubscriptionReceipt::with(['invoice.tenant'])
            ->where('status', 'submitted')
            ->latest()
            ->get();

        $stats = [
            'active'          => TenantSubscription::where('status', 'active')->count(),
            'grace'           => TenantSubscription::where('status', 'grace')->count(),
            'readonly'        => TenantSubscription::where('status', 'readonly')->count(),
            'pending_receipts'=> $pendingReceipts->count(),
        ];

        return inertia('Admin/Billing/Index', compact('plans', 'subscriptions', 'pendingReceipts', 'stats'));
    }

    public function storePlan(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:100',
            'slug'           => 'required|string|max:60|unique:subscription_plans',
            'price_inr'      => 'required|numeric|min:0',
            'billing_period' => 'required|in:annual,monthly',
            'features'       => 'nullable|array',
        ]);

        SubscriptionPlan::create($data);

        return back()->with('success', 'Subscription plan created.');
    }

    public function storeTenantSubscription(Request $request)
    {
        $data = $request->validate([
            'tenant_id'    => 'required|exists:tenants,id',
            'plan_id'      => 'nullable|exists:subscription_plans,id',
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after:period_start',
            'status'       => 'required|in:active,grace,readonly,suspended',
        ]);

        TenantSubscription::updateOrCreate(
            ['tenant_id' => $data['tenant_id']],
            $data
        );

        return back()->with('success', 'Subscription saved.');
    }

    public function storeInvoice(Request $request)
    {
        $data = $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'plan_id'   => 'nullable|exists:subscription_plans,id',
            'amount'    => 'required|numeric|min:0',
            'due_date'  => 'required|date',
        ]);

        SubscriptionInvoice::create(array_merge($data, [
            'invoice_number' => SubscriptionInvoice::generateNumber(),
            'status'         => 'sent',
        ]));

        return back()->with('success', 'Invoice generated and sent to tenant.');
    }

    public function approveReceipt(Request $request, SubscriptionReceipt $receipt)
    {
        $receipt->update([
            'status'      => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $receipt->invoice->update(['status' => 'approved']);

        // Activate or extend tenant subscription
        $invoice = $receipt->invoice;
        TenantSubscription::updateOrCreate(
            ['tenant_id' => $invoice->tenant_id],
            [
                'plan_id'      => $invoice->plan_id,
                'period_start' => now()->toDateString(),
                'period_end'   => now()->addYear()->toDateString(),
                'status'       => 'active',
            ]
        );

        return back()->with('success', 'Receipt approved. Subscription activated.');
    }

    public function rejectReceipt(Request $request, SubscriptionReceipt $receipt)
    {
        $data = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $receipt->update(array_merge($data, [
            'status'      => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]));

        return back()->with('success', 'Receipt rejected.');
    }

    public function showReceiptFile(SubscriptionReceipt $receipt)
    {
        abort_unless(Storage::exists($receipt->file_path), 404);

        return Storage::response($receipt->file_path);
    }
}
