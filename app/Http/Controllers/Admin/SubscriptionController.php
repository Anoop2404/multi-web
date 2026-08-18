<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionReceipt;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Services\Audit\PlatformAuditLogger;
use App\Support\Licensing\FeatureCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:100',
        ]);

        $plans = SubscriptionPlan::with('planFeatures')->orderBy('price_inr')->get();

        $subscriptions = TenantSubscription::with(['tenant', 'plan'])
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $term = '%'.$filters['search'].'%';
                $q->whereHas('tenant', fn ($t) => $t->where('name', 'like', $term));
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

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

        $tenantsForSelect = Tenant::orderBy('name')->get(['id', 'name']);

        return inertia('Billing/Index', [
            'plans'            => $plans,
            'subscriptions'    => $subscriptions,
            'pendingReceipts'  => $pendingReceipts,
            'stats'            => $stats,
            'filters'          => array_merge(['search' => ''], $filters),
            'tenantsForSelect' => $tenantsForSelect,
            'featureCatalog'   => FeatureCatalog::all(),
        ]);
    }

    public function storePlan(Request $request, PlatformAuditLogger $audit)
    {
        $data = $request->validate([
            'name'              => 'required|string|max:100',
            'slug'              => 'required|string|max:60|unique:subscription_plans',
            'price_inr'         => 'required|numeric|min:0',
            'billing_period'    => 'required|in:annual,monthly',
            'grace_period_days' => 'nullable|integer|min:0|max:365',
            'features'          => 'nullable|array',
        ]);

        $plan = SubscriptionPlan::create($data);

        $audit->subscriptionPlanCreated($plan);

        return back()->with('success', 'Subscription plan created.');
    }

    public function updatePlanFeatures(Request $request, SubscriptionPlan $plan, PlatformAuditLogger $audit)
    {
        $data = $request->validate([
            'features'                => 'required|array',
            'features.*.enabled'      => 'sometimes|boolean',
            'features.*.limit_value'  => 'nullable|integer|min:0',
        ]);

        $catalogKeys = FeatureCatalog::keys();

        foreach ($data['features'] as $key => $row) {
            if (! in_array($key, $catalogKeys, true)) {
                continue;
            }

            \App\Models\PlanFeature::updateOrCreate(
                ['plan_id' => $plan->id, 'feature_key' => $key],
                [
                    'enabled' => (bool) ($row['enabled'] ?? false),
                    'limit_value' => $row['limit_value'] ?? null,
                ]
            );
        }

        $audit->log(
            'subscription.plan_features_updated',
            "Feature flags updated for plan: {$plan->name}",
            $plan,
            ['plan_id' => $plan->id],
            category: 'billing',
        );

        return back()->with('success', 'Plan features updated.');
    }

    public function storeTenantSubscription(Request $request, PlatformAuditLogger $audit)
    {
        $data = $request->validate([
            'tenant_id'    => 'required|exists:tenants,id',
            'plan_id'      => 'nullable|exists:subscription_plans,id',
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after:period_start',
            'status'       => 'required|in:active,grace,readonly,suspended',
            'auto_renew'   => 'sometimes|boolean',
        ]);

        $subscription = TenantSubscription::updateOrCreate(
            ['tenant_id' => $data['tenant_id']],
            $data
        );

        $audit->tenantSubscriptionSaved($subscription);

        return back()->with('success', 'Subscription saved.');
    }

    public function storeInvoice(Request $request, PlatformAuditLogger $audit)
    {
        $data = $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'plan_id'   => 'nullable|exists:subscription_plans,id',
            'amount'    => 'required|numeric|min:0',
            'due_date'  => 'required|date',
        ]);

        $invoice = SubscriptionInvoice::create(array_merge($data, [
            'invoice_number' => SubscriptionInvoice::generateNumber(),
            'status'         => 'sent',
        ]));

        $audit->invoiceCreated($invoice);

        return back()->with('success', 'Invoice generated and sent to tenant.');
    }

    public function approveReceipt(Request $request, SubscriptionReceipt $receipt, PlatformAuditLogger $audit)
    {
        $receipt->update([
            'status'      => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $receipt->invoice->update(['status' => 'approved']);

        // Activate or extend tenant subscription
        $invoice = $receipt->invoice;
        $periodEnd = $invoice->plan?->billing_period === 'monthly'
            ? now()->addMonthNoOverflow()
            : now()->addYear();

        TenantSubscription::updateOrCreate(
            ['tenant_id' => $invoice->tenant_id],
            [
                'plan_id'      => $invoice->plan_id,
                'period_start' => now()->toDateString(),
                'period_end'   => $periodEnd->toDateString(),
                'status'       => 'active',
            ]
        );

        $audit->receiptApproved($receipt);

        return back()->with('success', 'Receipt approved. Subscription activated.');
    }

    public function rejectReceipt(Request $request, SubscriptionReceipt $receipt, PlatformAuditLogger $audit)
    {
        $data = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $receipt->update(array_merge($data, [
            'status'      => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]));

        $audit->receiptRejected($receipt, $data['rejection_reason']);

        return back()->with('success', 'Receipt rejected.');
    }

    public function showReceiptFile(SubscriptionReceipt $receipt)
    {
        abort_unless(Storage::exists($receipt->file_path), 404);

        return Storage::response($receipt->file_path);
    }
}
