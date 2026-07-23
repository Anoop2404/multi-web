<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FeeReceipt;
use App\Models\FestEvent;
use App\Models\FestFeeCredit;
use App\Models\FestSchoolEventFee;
use App\Models\McqSchoolFee;
use App\Models\MembershipPayment;
use App\Models\NotificationLog;
use App\Models\TrainingRegistration;
use App\Services\Ledger\LedgerReportingService;
use App\Support\LedgerAccountCatalog;
use App\Support\TenancyDatabase;

class FinanceHubController extends SahodayaAdminController
{
    public function index(LedgerReportingService $reporting)
    {
        $eventIds = FestEvent::where('tenant_id', $this->sahodaya->id)->pluck('id');

        $schoolIds = TenancyDatabase::schoolIdsFor($this->sahodaya->id);

        $festPending = FestSchoolEventFee::whereIn('event_id', $eventIds)
            ->where('status', 'proof_uploaded')
            ->whereHas('feeReceipt', fn ($q) => $q->where('status', 'uploaded'))
            ->count();

        $festOutstandingFeeIds = FestSchoolEventFee::whereIn('event_id', $eventIds)
            ->forAmountAggregation()
            ->whereNotIn('status', ['approved', 'waived']);

        $festOutstanding = (clone $festOutstandingFeeIds)->sum('total_due');

        // Money already owed BACK to schools (FestFeeCredit) — shown alongside the raw
        // outstanding total rather than netted into it, so this headline figure keeps its
        // existing meaning (gross amount still billed) for anyone already relying on it; the
        // credit total is new, additive context. See docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §14.
        $festCredit = FestFeeCredit::outstanding()
            ->whereIn('fest_school_event_fee_id', (clone $festOutstandingFeeIds)->pluck('id'))
            ->sum('amount');

        $membershipPending = MembershipPayment::whereIn('school_id', $schoolIds)
            ->where('status', 'submitted')
            ->count();

        $membershipOutstanding = MembershipPayment::whereIn('school_id', $schoolIds)
            ->whereNotIn('status', ['verified', 'waived', 'superseded'])
            ->sum('amount');

        $mcqPending = McqSchoolFee::whereHas('exam', fn ($q) => $q->where('tenant_id', $this->sahodaya->id))
            ->where('status', 'proof_uploaded')
            ->count();

        $mcqOutstanding = McqSchoolFee::whereHas('exam', fn ($q) => $q->where('tenant_id', $this->sahodaya->id))
            ->whereNotIn('status', ['approved', 'waived'])
            ->sum('total_due');

        $trainingPending = TrainingRegistration::whereIn('school_id', $schoolIds)
            ->whereHas('feeReceipt', fn ($q) => $q->where('status', 'uploaded'))
            ->count();

        $trainingOutstanding = (float) FeeReceipt::query()
            ->where('feeable_type', TrainingRegistration::class)
            ->whereIn('status', ['uploaded', 'pending'])
            ->whereHasMorph('feeable', [TrainingRegistration::class], fn ($q) => $q->whereIn('school_id', $schoolIds))
            ->sum('amount');

        $failedEmails = NotificationLog::where('status', 'failed')->count();
        $failedReceiptEmails = FeeReceipt::where('receipt_email_status', 'failed')->count();

        $byCategory = $reporting->summaryByCategory($this->sahodaya->id)
            ->groupBy('category')
            ->map(fn ($group, $category) => [
                'category' => $category,
                'credit'   => (float) $group->where('entry_type', 'credit')->sum('total'),
                'debit'    => (float) $group->where('entry_type', 'debit')->sum('total'),
            ])
            ->values();

        $monthlyCollection = FeeReceipt::query()
            ->where('status', 'approved')
            ->where('payment_date', '>=', now()->subMonths(11)->startOfMonth())
            ->get()
            ->groupBy(fn (FeeReceipt $r) => $r->payment_date?->format('Y-m') ?? 'unknown')
            ->map(fn ($group, $month) => [
                'month'  => $month,
                'amount' => round((float) $group->sum('amount'), 2),
                'count'  => $group->count(),
            ])
            ->sortKeys()
            ->values();

        return $this->inertia('Sahodaya/Finance/Hub', [
            'summary' => [
                'fest_pending'           => $festPending,
                'fest_outstanding'       => round((float) $festOutstanding, 2),
                'fest_credit'            => round((float) $festCredit, 2),
                'membership_pending'     => $membershipPending,
                'membership_outstanding' => round((float) $membershipOutstanding, 2),
                'mcq_pending'            => $mcqPending,
                'mcq_outstanding'        => round((float) $mcqOutstanding, 2),
                'training_pending'       => $trainingPending,
                'training_outstanding'   => round((float) $trainingOutstanding, 2),
                'total_pending'          => $festPending + $membershipPending + $mcqPending + $trainingPending,
                'failed_emails'          => $failedEmails,
                'failed_receipt_emails'  => $failedReceiptEmails,
            ],
            'links' => [
                'unified_payments'    => "/sahodaya-admin/{$this->sahodaya->id}/finance/payments",
                'email_delivery'      => "/sahodaya-admin/{$this->sahodaya->id}/finance/email-delivery",
                'receipt_emails'      => "/sahodaya-admin/{$this->sahodaya->id}/finance/receipt-emails",
                'fest_payments'       => "/sahodaya-admin/{$this->sahodaya->id}/fest/payments",
                'membership_payments' => "/sahodaya-admin/{$this->sahodaya->id}/membership/payments",
                'mcq_payments'        => "/sahodaya-admin/{$this->sahodaya->id}/mcq/payments",
                'training_programs'   => "/sahodaya-admin/{$this->sahodaya->id}/training",
                'ledger'              => "/sahodaya-admin/{$this->sahodaya->id}/ledger",
                'ledger_reports'     => "/sahodaya-admin/{$this->sahodaya->id}/ledger/reports",
                'opening_balances'   => "/sahodaya-admin/{$this->sahodaya->id}/ledger/opening-balances",
                'payables'           => "/sahodaya-admin/{$this->sahodaya->id}/finance/payables",
                'receivables'        => "/sahodaya-admin/{$this->sahodaya->id}/finance/receivables",
            ],
            'ledgerByCategory' => $byCategory,
            'categoryLabels'   => LedgerAccountCatalog::categoryLabels(),
            'monthlyCollection' => $monthlyCollection,
        ]);
    }

    public function receivables()
    {
        $eventIds = FestEvent::where('tenant_id', $this->sahodaya->id)->pluck('id');

        $schoolIds = TenancyDatabase::schoolIdsFor($this->sahodaya->id);

        $festRows = FestSchoolEventFee::whereIn('event_id', $eventIds)
            ->with(['event:id,title,event_type', 'school:id,name'])
            ->whereNotIn('status', ['approved', 'waived'])
            ->orderByDesc('total_due')
            ->get()
            ->map(fn (FestSchoolEventFee $f) => [
                'source'           => 'fest',
                'school'           => $f->school?->name,
                'school_id'        => $f->school_id,
                'program'          => $f->event?->title,
                'amount'           => (float) $f->total_due,
                // New, additive — the "amount" field above keeps its existing meaning
                // (gross total_due) so nothing that already reads it changes; this is
                // context on top. See docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §14.
                'available_credit' => $f->outstandingCredit(),
                'status'           => $f->status,
                'updated_at'       => $f->updated_at?->format('j M Y'),
            ]);

        $membershipRows = MembershipPayment::whereIn('school_id', $schoolIds)
            ->with('school:id,name')
            ->whereNotIn('status', ['verified', 'waived', 'superseded'])
            ->orderByDesc('amount')
            ->get()
            ->map(fn (MembershipPayment $p) => [
                'source'           => 'membership',
                'school'           => $p->school?->name,
                'school_id'        => $p->school_id,
                'program'          => 'Annual membership',
                'amount'           => (float) $p->amount,
                'available_credit' => 0.0, // no credit concept for membership payments
                'status'           => $p->status,
                'updated_at'       => $p->updated_at?->format('j M Y'),
            ]);

        $rows = $festRows->concat($membershipRows)->sortByDesc('amount')->values();

        return $this->inertia('Sahodaya/Finance/Receivables', [
            'rows'    => $rows,
            'totals'  => [
                'count'  => $rows->count(),
                'amount' => round($rows->sum('amount'), 2),
                'credit' => round($rows->sum('available_credit'), 2),
            ],
        ]);
    }
}
