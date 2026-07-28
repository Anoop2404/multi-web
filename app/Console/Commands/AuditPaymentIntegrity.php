<?php

namespace App\Console\Commands;

use App\Models\FeeReceipt;
use App\Models\CreditPayout;
use App\Models\FestEvent;
use App\Models\FestFeeCredit;
use App\Models\FestSchoolEventFee;
use App\Models\LedgerTransaction;
use App\Models\McqSchoolFee;
use App\Models\ProgramFeeCredit;
use App\Models\Tenant;
use App\Models\TrainingSchoolFee;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class AuditPaymentIntegrity extends Command
{
    protected $signature = 'finance:audit-payment-integrity
        {--sahodaya= : Sahodaya UUID or subdomain}
        {--event= : Limit Fest checks to one event ID}
        {--json : Print machine-readable JSON}';

    protected $description = 'Read-only audit of fee totals, approved receipts, credits, and ledger postings';

    public function handle(): int
    {
        $tenants = Tenant::query()
            ->where('type', 'sahodaya')
            ->when($this->option('sahodaya'), function ($query, $value) {
                $query->where(fn ($inner) => $inner->where('id', $value)->orWhere('subdomain', $value));
            })
            ->orderBy('name')
            ->get();

        if ($tenants->isEmpty()) {
            $this->error('No matching Sahodaya tenant.');

            return self::FAILURE;
        }

        $reports = [];
        foreach ($tenants as $tenant) {
            try {
                $reports[] = $tenant->run(fn () => $this->auditTenant($tenant));
            } catch (\Throwable $exception) {
                $reports[] = [
                    'sahodaya_id' => $tenant->id,
                    'sahodaya' => $tenant->name,
                    'error' => $exception->getMessage(),
                    'issues' => [],
                ];
            } finally {
                if (function_exists('tenancy') && tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($reports, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        foreach ($reports as $report) {
            $this->newLine();
            $this->info("{$report['sahodaya']} ({$report['sahodaya_id']})");
            if (isset($report['error'])) {
                $this->error($report['error']);
                continue;
            }

            $this->line(
                "Checked {$report['checked']['carriers']} fee records, "
                ."{$report['checked']['receipts']} approved receipts, "
                ."{$report['checked']['credits']} credits, "
                ."{$report['checked']['payouts']} payouts, and "
                ."{$report['checked']['journals']} journals."
            );
            if ($report['issues'] === []) {
                $this->info('No payment-integrity exceptions found.');
                continue;
            }

            $this->table(
                ['Issue', 'Source', 'ID', 'Expected', 'Actual', 'Difference', 'Detail'],
                collect($report['issues'])->map(fn (array $row) => [
                    $row['issue'],
                    $row['source'],
                    $row['id'],
                    $row['expected'] ?? '—',
                    $row['actual'] ?? '—',
                    $row['difference'] ?? '—',
                    $row['detail'] ?? '',
                ])->all(),
            );
        }

        $this->newLine();
        $this->comment('Read-only audit: no receipts, credits, fee records, or ledger rows were changed.');

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function auditTenant(Tenant $tenant): array
    {
        $issues = collect();
        $carriers = collect();

        $fest = FestSchoolEventFee::query()
            ->when($this->option('event'), fn ($query, $eventId) => $query->where('event_id', $eventId))
            ->forAmountAggregation()
            ->get();
        $carriers = $carriers->concat($fest);

        if (! $this->option('event')) {
            $carriers = $carriers
                ->concat(McqSchoolFee::all())
                ->concat(TrainingSchoolFee::all());
        }

        foreach ($carriers as $carrier) {
            $approved = round((float) $carrier->receipts()->where('status', FeeReceipt::STATUS_APPROVED)->sum('amount'), 2);
            $storedPaid = round((float) ($carrier->amount_paid ?? 0), 2);
            $source = class_basename($carrier);

            if (abs($approved - $storedPaid) > 0.01) {
                $issues->push($this->issue(
                    'amount_paid_drift',
                    $source,
                    $carrier->getKey(),
                    $approved,
                    $storedPaid,
                    "Stored amount_paid does not equal approved receipts."
                ));
            }

            $due = round((float) ($carrier->total_due ?? 0), 2);
            $excess = round(max(0, $approved - $due), 2);
            if ($excess <= 0) {
                continue;
            }

            $credit = $this->outstandingCreditFor($carrier);
            $missing = round(max(0, $excess - $credit), 2);
            $eventLabel = $carrier instanceof FestSchoolEventFee
                ? ' Event '.(FestEvent::find($carrier->event_id)?->title ?? "#{$carrier->event_id}").'.'
                : '';

            $issues->push($this->issue(
                $missing > 0 ? 'overpayment_without_credit' : 'overpayment_credit_recorded',
                $source,
                $carrier->getKey(),
                $excess,
                $credit,
                "{$eventLabel} Due {$due}; approved receipts {$approved}; school {$carrier->school_id}."
            ));
        }

        $feeableTypes = [
            FestSchoolEventFee::class,
            McqSchoolFee::class,
            TrainingSchoolFee::class,
            \App\Models\MembershipPayment::class,
            \App\Models\FestRegistration::class,
            \App\Models\McqRegistration::class,
            \App\Models\TrainingRegistration::class,
        ];

        $approvedReceipts = FeeReceipt::query()
            ->where('status', FeeReceipt::STATUS_APPROVED)
            ->whereIn('feeable_type', $feeableTypes)
            ->get();

        foreach ($approvedReceipts as $receipt) {
            $posted = round((float) LedgerTransaction::query()
                ->where('tenant_id', $tenant->id)
                ->where('reference_type', FeeReceipt::class)
                ->where('reference_id', $receipt->id)
                ->where('entry_type', 'credit')
                ->sum('amount'), 2);
            $amount = round((float) $receipt->amount, 2);

            if (abs($posted - $amount) > 0.01) {
                $issues->push($this->issue(
                    $posted <= 0 ? 'approved_receipt_not_posted' : 'receipt_ledger_mismatch',
                    class_basename($receipt->feeable_type),
                    $receipt->id,
                    $amount,
                    $posted,
                    "Feeable #{$receipt->feeable_id}; receipt {$receipt->receipt_number}."
                ));
            }
        }

        $credits = FestFeeCredit::all()->concat(ProgramFeeCredit::all());
        foreach ($credits as $credit) {
            $posted = round((float) LedgerTransaction::query()
                ->where('tenant_id', $tenant->id)
                ->where('reference_type', $credit::class)
                ->where('reference_id', $credit->id)
                ->where('entry_type', 'credit')
                ->whereHas('accountHead', fn ($query) => $query->where('code', 'FEE-CREDIT-PAYABLE'))
                ->sum('amount'), 2);
            $amount = round((float) $credit->amount, 2);

            if (abs($posted - $amount) > 0.01) {
                $issues->push($this->issue(
                    'credit_not_posted_to_payable',
                    class_basename($credit),
                    $credit->id,
                    $amount,
                    $posted,
                    'Credit exists operationally but the Fee Credits Payable ledger head does not match.'
                ));
            }
        }

        $payouts = CreditPayout::all();
        foreach ($payouts as $payout) {
            $posted = round((float) LedgerTransaction::query()
                ->where('tenant_id', $tenant->id)
                ->where('reference_type', CreditPayout::class)
                ->where('reference_id', $payout->id)
                ->where('entry_type', 'debit')
                ->whereHas('accountHead', fn ($query) => $query->where('code', 'FEE-CREDIT-PAYABLE'))
                ->sum('amount'), 2);
            $amount = round((float) $payout->amount, 2);

            if (abs($posted - $amount) > 0.01) {
                $issues->push($this->issue(
                    'credit_payout_not_posted',
                    class_basename($payout),
                    $payout->id,
                    $amount,
                    $posted,
                    "School {$payout->school_id}; bank reference {$payout->bank_ref}."
                ));
            }
        }

        $journals = LedgerTransaction::query()
            ->where('tenant_id', $tenant->id)
            ->selectRaw("journal_id, SUM(CASE WHEN entry_type = 'debit' THEN amount ELSE 0 END) AS debits, SUM(CASE WHEN entry_type = 'credit' THEN amount ELSE 0 END) AS credits")
            ->groupBy('journal_id')
            ->get();

        foreach ($journals as $journal) {
            $debits = round((float) $journal->debits, 2);
            $journalCredits = round((float) $journal->credits, 2);
            if (abs($debits - $journalCredits) > 0.01) {
                $issues->push($this->issue(
                    'unbalanced_journal',
                    'LedgerTransaction',
                    $journal->journal_id,
                    $debits,
                    $journalCredits,
                    'Journal debits and credits do not balance.'
                ));
            }
        }

        return [
            'sahodaya_id' => $tenant->id,
            'sahodaya' => $tenant->name,
            'checked' => [
                'carriers' => $carriers->count(),
                'receipts' => $approvedReceipts->count(),
                'credits' => $credits->count(),
                'payouts' => $payouts->count(),
                'journals' => $journals->count(),
            ],
            'issues' => $issues->values()->all(),
        ];
    }

    private function outstandingCreditFor(Model $carrier): float
    {
        if ($carrier instanceof FestSchoolEventFee) {
            return round((float) FestFeeCredit::query()
                ->where('fest_school_event_fee_id', $carrier->id)
                ->whereNull('applied_at')
                ->sum('amount'), 2);
        }

        return round((float) ProgramFeeCredit::query()
            ->where('creditable_type', $carrier::class)
            ->where('creditable_id', $carrier->getKey())
            ->whereNull('applied_at')
            ->sum('amount'), 2);
    }

    /** @return array<string, mixed> */
    private function issue(
        string $issue,
        string $source,
        int|string $id,
        float $expected,
        float $actual,
        string $detail,
    ): array {
        return [
            'issue' => $issue,
            'source' => $source,
            'id' => $id,
            'expected' => number_format($expected, 2, '.', ''),
            'actual' => number_format($actual, 2, '.', ''),
            'difference' => number_format($actual - $expected, 2, '.', ''),
            'detail' => $detail,
        ];
    }
}
