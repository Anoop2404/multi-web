<?php

use App\Models\BankAccount;
use App\Models\FinancialYear;
use App\Models\LedgerTransaction;
use App\Models\SahodayaPayable;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ledger\FinancialStatementsService;
use App\Services\Ledger\LedgerPostingService;
use App\Services\Ledger\OpeningBalanceService;
use App\Services\Ledger\PayableLedgerService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountsModuleTest extends TestCase
{
    use RefreshDatabase;

    private $tenant;

    private $user;

    private $year;

    private $posting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Audit', 'is_active' => true]);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->year = FinancialYear::create(['label' => '2026-27', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31', 'is_current' => true]);
        $this->posting = app(LedgerPostingService::class);
        $this->posting->ensureDefaultHeads($this->tenant->id);
    }

    private function pair($code, $type, $amount, $date = '2026-10-01')
    {
        $head = $this->posting->ensureHead($this->tenant->id, $code);

        return $this->posting->postManualPair($this->tenant->id, $head->id, $type, $amount, 'Audit', $date, $this->user->id);
    }

    public function test_expense_posts_balanced_cash_payment()
    {
        $rows = $this->pair('ADMIN-EXP', 'debit', 250);
        $this->assertCount(2, $rows);
        $this->assertEquals(250, LedgerTransaction::where('entry_type', 'debit')->sum('amount'));
        $this->assertEquals(250, app(FinancialStatementsService::class)->incomeAndExpenditure($this->tenant->id, $this->year->id)['expense']);
    }

    public function test_income_reversal_is_deducted_from_income()
    {
        $this->pair('MEMBERSHIP', 'credit', 1000);
        $this->pair('MEMBERSHIP', 'debit', 200);
        $this->assertEquals(800, app(FinancialStatementsService::class)->incomeAndExpenditure($this->tenant->id, $this->year->id)['income']);
    }

    public function test_opening_balance_is_not_double_counted()
    {
        $head = $this->posting->ensureHead($this->tenant->id, 'CASH-BANK');
        app(OpeningBalanceService::class)->save($this->tenant->id, $this->year->id, $head->id, 'debit', 1000, null, $this->user->id);
        $row = app(FinancialStatementsService::class)->trialBalance($this->tenant->id, $this->year->id)->firstWhere('code', 'CASH-BANK');
        $this->assertEquals(1000, $row->balance);
    }

    public function test_filtered_cash_book_keeps_prior_balance()
    {
        $this->pair('MEMBERSHIP', 'credit', 1000, '2026-09-01');
        $this->pair('ADMIN-EXP', 'debit', 100, '2026-10-01');
        $row = app(FinancialStatementsService::class)->cashBook($this->tenant->id, $this->year->id, '2026-10-01')->first();
        $this->assertEquals(900, $row->running_balance);
    }

    public function test_partial_payable_payment_reduces_balance()
    {
        $p = SahodayaPayable::create(['tenant_id' => $this->tenant->id, 'financial_year_id' => $this->year->id, 'vendor_name' => 'Test vendor', 'amount' => 1000, 'status' => 'pending']);
        $service = app(PayableLedgerService::class);
        $service->recordObligation($p, $this->user->id);
        $p = $service->markPaid($p, 400, $this->user->id);
        $this->assertEquals(600, $p->balanceDue());
        $this->assertSame('partial', $p->status);
    }

    public function test_cancelled_payable_cannot_be_paid()
    {
        $p = SahodayaPayable::create(['tenant_id' => $this->tenant->id, 'financial_year_id' => $this->year->id, 'vendor_name' => 'Test vendor', 'amount' => 1000, 'status' => 'cancelled']);
        $this->expectException(InvalidArgumentException::class);
        app(PayableLedgerService::class)->markPaid($p, 100, $this->user->id);
    }

    public function test_journal_rejects_unbalanced_lines()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->posting->postJournal($this->tenant->id, [['code' => 'ADMIN-EXP', 'entry_type' => 'debit', 'amount' => 100]], null, null, null, $this->user->id);
    }

    public function test_payable_overpayment_is_rejected()
    {
        $p = SahodayaPayable::create(['tenant_id' => $this->tenant->id, 'financial_year_id' => $this->year->id, 'vendor_name' => 'Test vendor', 'amount' => 1000, 'status' => 'pending']);
        $this->expectException(InvalidArgumentException::class);
        app(PayableLedgerService::class)->markPaid($p, 1100, $this->user->id);
    }

    private function admin()
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        SahodayaProfile::create(['tenant_id' => $this->tenant->id, 'prefix' => 'AUD', 'student_data_mode' => 'counts_only']);
        $this->user->assignRole('sahodaya_admin');
        $this->actingAs($this->user);
    }

    public function test_finance_pages_load()
    {
        $this->admin();
        foreach (['finance', 'ledger', 'ledger/reports', 'ledger/opening-balances', 'finance/financial-statements', 'finance/payables', 'finance/receivables', 'finance/bank-reconciliation', 'finance/payments', 'finance/payment-reconciliation', 'finance/receipt-emails', 'finance/email-delivery'] as $path) {
            $response = $this->get('/sahodaya-admin/'.$this->tenant->id.'/'.$path);
            $response->assertOk();
        }
    }

    public function test_bank_reconciliation_rejects_another_tenants_bank()
    {
        $this->admin();
        $other = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Other', 'is_active' => true]);
        $bank = BankAccount::create(['tenant_id' => $other->id, 'account_name' => 'Other bank', 'bank_name' => 'Test', 'account_no' => '123456', 'ifsc' => 'TEST0000001', 'is_active' => true]);
        $tx = $this->pair('ADMIN-EXP', 'debit', 100)[0];
        $response = $this->post('/sahodaya-admin/'.$this->tenant->id.'/finance/bank-reconciliation/'.$tx->id.'/reconcile', ['bank_account_id' => $bank->id, 'reconciled' => true]);
        $response->assertSessionHasNoErrors();
        $this->assertNull($tx->fresh()->bank_account_id);
        $response->assertForbidden();
    }

    public function test_cancel_payable_reverses_unpaid_obligation()
    {
        $this->admin();
        $p = SahodayaPayable::create(['tenant_id' => $this->tenant->id, 'financial_year_id' => $this->year->id, 'vendor_name' => 'Test vendor', 'amount' => 1000, 'status' => 'pending']);
        app(PayableLedgerService::class)->recordObligation($p, $this->user->id);
        $this->post('/sahodaya-admin/'.$this->tenant->id.'/finance/payables/'.$p->id.'/cancel')->assertRedirect();
        $head = $this->posting->ensureHead($this->tenant->id, 'ACC-PAYABLE');
        $credits = LedgerTransaction::where('account_head_id', $head->id)->where('entry_type', 'credit')->sum('amount');
        $debits = LedgerTransaction::where('account_head_id', $head->id)->where('entry_type', 'debit')->sum('amount');
        $this->assertEquals(0, $credits - $debits);
    }

    public function test_admin_can_record_expense_through_endpoint()
    {
        $this->admin();
        $head = $this->posting->ensureHead($this->tenant->id, 'ADMIN-EXP');
        $this->post('/sahodaya-admin/'.$this->tenant->id.'/ledger/expenses', ['account_head_id' => $head->id, 'amount' => 275.50, 'transaction_date' => '2026-10-25', 'description' => 'Test-only printing bill'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertEquals(275.50, LedgerTransaction::where('account_head_id', $head->id)->where('entry_type', 'debit')->sum('amount'));
        $this->assertCount(2, LedgerTransaction::all());
    }

    public function test_expense_endpoint_rejects_income_head()
    {
        $this->admin();
        $head = $this->posting->ensureHead($this->tenant->id, 'MEMBERSHIP');
        $this->post('/sahodaya-admin/'.$this->tenant->id.'/ledger/expenses', ['account_head_id' => $head->id, 'amount' => 100, 'transaction_date' => '2026-10-25'])->assertStatus(422);
        $this->assertEquals(0, LedgerTransaction::count());
    }

    public function test_expense_endpoint_rejects_zero_amount()
    {
        $this->admin();
        $head = $this->posting->ensureHead($this->tenant->id, 'ADMIN-EXP');
        $this->post('/sahodaya-admin/'.$this->tenant->id.'/ledger/expenses', ['account_head_id' => $head->id, 'amount' => 0, 'transaction_date' => '2026-10-25'])->assertSessionHasErrors('amount');
        $this->assertEquals(0, LedgerTransaction::count());
    }

    public function test_expense_endpoint_rejects_another_tenants_head()
    {
        $this->admin();
        $other = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Other', 'is_active' => true]);
        $head = $this->posting->ensureHead($other->id, 'ADMIN-EXP');
        $this->post('/sahodaya-admin/'.$this->tenant->id.'/ledger/expenses', ['account_head_id' => $head->id, 'amount' => 100, 'transaction_date' => '2026-10-25'])->assertForbidden();
        $this->assertEquals(0, LedgerTransaction::count());
    }
}
