<?php

namespace Tests\Feature;

use App\Models\AccountHead;
use App\Models\BankAccount;
use App\Models\FinanceDocument;
use App\Models\FinancialYear;
use App\Models\LedgerTransaction;
use App\Models\SahodayaPayable;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ledger\FinanceWorkspaceService;
use App\Services\Ledger\FinancialStatementsService;
use App\Services\Ledger\LedgerPostingService;
use App\Services\Ledger\PayableLedgerService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class FinanceWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private FinancialYear $year;

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->tenant = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Finance test', 'is_active' => true]);
        SahodayaProfile::create(['tenant_id' => $this->tenant->id, 'prefix' => 'FW', 'student_data_mode' => 'counts_only']);
        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->admin->assignRole('sahodaya_admin');
        $this->actingAs($this->admin);
        $this->year = FinancialYear::create(['label' => '2026-27', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31', 'is_current' => true]);
        $this->base = '/sahodaya-admin/'.$this->tenant->id.'/finance';
        app(LedgerPostingService::class)->ensureDefaultHeads($this->tenant->id);
    }

    private function voucher(array $extra = []): FinanceDocument
    {
        $head = AccountHead::where('tenant_id', $this->tenant->id)->where('code', 'PRINTING')->firstOrFail();

        return FinanceDocument::create(array_merge(['tenant_id' => $this->tenant->id, 'submission_key' => (string) Str::uuid(), 'kind' => 'expense', 'status' => 'pending', 'financial_year_id' => $this->year->id, 'account_head_id' => $head->id, 'amount' => 100, 'document_date' => '2026-10-25', 'description' => 'Printing bill', 'created_by' => $this->admin->id], $extra));
    }

    private function bank(string $name = 'Test bank'): BankAccount
    {
        return BankAccount::create(['tenant_id' => $this->tenant->id, 'account_name' => $name, 'bank_name' => 'Test', 'account_no' => (string) random_int(100000, 999999), 'is_active' => true]);
    }

    public function test_workspace_renders(): void
    {
        $this->get($this->base.'/workspace')->assertOk();
    }

    public function test_bill_upload_and_repeat_submission_create_one_pending_voucher(): void
    {
        Storage::fake('local');
        $head = AccountHead::where('tenant_id', $this->tenant->id)->where('code', 'PRINTING')->firstOrFail();
        $data = ['submission_key' => (string) Str::uuid(), 'kind' => 'expense', 'account_head_id' => $head->id, 'amount' => 200, 'document_date' => '2026-10-25', 'description' => 'Printing', 'attachment' => UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf')];
        $this->post($this->base.'/documents', $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->post($this->base.'/documents', $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertEquals(1, FinanceDocument::count());
        $this->assertEquals(0, LedgerTransaction::count());
        Storage::disk('local')->assertExists(FinanceDocument::first()->attachment_path);
    }

    public function test_approval_is_balanced_and_idempotent(): void
    {
        $d = $this->voucher();
        $s = app(FinanceWorkspaceService::class);
        $s->approve($d, $this->admin->id);
        $s->approve($d, $this->admin->id);
        $this->assertEquals(2, LedgerTransaction::count());
        $this->assertEquals('posted', $d->fresh()->status);
        $this->assertEquals(100, app(FinancialStatementsService::class)->incomeAndExpenditure($this->tenant->id, $this->year->id)['expense']);
    }

    public function test_income_and_bank_transfer_use_distinct_asset_accounts(): void
    {
        $bank = $this->bank();
        $income = AccountHead::where('tenant_id', $this->tenant->id)->where('code', 'MEMBERSHIP')->first();
        app(FinanceWorkspaceService::class)->approve($this->voucher(['kind' => 'income', 'account_head_id' => $income->id, 'bank_account_id' => $bank->id, 'amount' => 500]), $this->admin->id);
        app(FinanceWorkspaceService::class)->approve($this->voucher(['kind' => 'transfer', 'bank_account_id' => $bank->id, 'destination_bank_id' => null, 'amount' => 200]), $this->admin->id);
        $this->assertEquals('asset', AccountHead::where('code', 'BANK-'.$bank->id)->value('type'));
        $rows = app(FinancialStatementsService::class)->trialBalance($this->tenant->id, $this->year->id);
        $this->assertEquals(300, $rows->firstWhere('code', 'BANK-'.$bank->id)->balance);
        $this->assertEquals(200, $rows->firstWhere('code', 'CASH-BANK')->balance);
    }

    public function test_same_account_transfer_is_rejected_without_posting(): void
    {
        $this->post($this->base.'/documents/'.$this->voucher(['kind' => 'transfer'])->id.'/review', ['action' => 'approve'])->assertStatus(422);
        $this->assertEquals(0, LedgerTransaction::count());
    }

    public function test_advance_settlement_cannot_exceed_advance(): void
    {
        $s = app(FinanceWorkspaceService::class);
        $advance = $s->approve($this->voucher(['kind' => 'advance', 'amount' => 500]), $this->admin->id);
        $s->approve($this->voucher(['kind' => 'settlement', 'amount' => 300, 'parent_id' => $advance->id]), $this->admin->id);
        $settlement = $this->voucher(['kind' => 'settlement', 'amount' => 250, 'parent_id' => $advance->id]);
        $this->post($this->base.'/documents/'.$settlement->id.'/review', ['action' => 'approve'])->assertSessionHasErrors('amount');
        $this->assertEquals('pending', $settlement->fresh()->status);
    }

    public function test_recurring_generates_one_pending_occurrence(): void
    {
        $this->travelTo(Carbon::parse('2026-10-25'));
        $d = $this->voucher(['recurring' => true, 'next_due' => '2026-10-25']);
        $s = app(FinanceWorkspaceService::class);
        $s->approve($d, $this->admin->id);
        $this->assertEquals(1, $s->generateRecurring($this->tenant->id, $this->admin->id));
        $this->assertEquals(0, $s->generateRecurring($this->tenant->id, $this->admin->id));
        $this->assertEquals(1, FinanceDocument::where('status', 'pending')->count());
    }

    public function test_rejection_does_not_post_money(): void
    {
        $d = $this->voucher();
        $this->post($this->base.'/documents/'.$d->id.'/review', ['action' => 'reject', 'reason' => 'Incorrect bill'])->assertRedirect();
        $this->assertEquals('rejected', $d->fresh()->status);
        $this->assertEquals(0, LedgerTransaction::count());
    }

    public function test_cannot_review_or_download_another_tenants_document(): void
    {
        $d = $this->voucher(['tenant_id' => (string) Str::uuid()]);
        $this->post($this->base.'/documents/'.$d->id.'/review', ['action' => 'approve'])->assertForbidden();
        $this->get($this->base.'/documents/'.$d->id.'/attachment')->assertForbidden();
    }

    public function test_statement_import_is_idempotent_and_matches_bank_entry(): void
    {
        $bank = $this->bank();
        $d = $this->voucher(['bank_account_id' => $bank->id]);
        app(FinanceWorkspaceService::class)->approve($d, $this->admin->id);
        $csv = "date,description,amount\n2026-10-25,Printing,-100\n";
        $data = ['bank_account_id' => $bank->id, 'statement' => UploadedFile::fake()->createWithContent('bank.csv', $csv)];
        $this->post($this->base.'/bank-reconciliation/import', $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->post($this->base.'/bank-reconciliation/import', $data)->assertRedirect();
        $this->assertEquals(1, DB::table('finance_statement_lines')->count());
        $line = DB::table('finance_statement_lines')->first();
        $tx = LedgerTransaction::where('bank_account_id', $bank->id)->firstOrFail();
        $this->post($this->base.'/bank-reconciliation/lines/'.$line->id.'/match', ['ledger_transaction_id' => $tx->id])->assertRedirect();
        $this->assertNotNull($tx->fresh()->reconciled_at);
    }

    public function test_bad_csv_rolls_back_every_line(): void
    {
        $bank = $this->bank();
        $csv = "date,description,amount\n2026-10-25,Good,100\nnot-a-date,Bad,100\n";
        $this->post($this->base.'/bank-reconciliation/import', ['bank_account_id' => $bank->id, 'statement' => UploadedFile::fake()->createWithContent('bank.csv', $csv)])->assertSessionHasErrors('statement');
        $this->assertEquals(0, DB::table('finance_statement_lines')->count());
    }

    public function test_budget_actual_uses_posted_vouchers_only(): void
    {
        $d = $this->voucher();
        $this->post($this->base.'/budgets', ['financial_year_id' => $this->year->id, 'account_head_id' => $d->account_head_id, 'amount' => 1000])->assertRedirect();
        app(FinanceWorkspaceService::class)->approve($d, $this->admin->id);
        $this->voucher(['amount' => 900]);
        $this->get($this->base.'/workspace')->assertInertia(fn (AssertableInertia $p) => $p->component('Sahodaya/Finance/Workspace', false)->where('budgets.0.actual', 100)->where('budgets.0.remaining', 900));
    }

    public function test_closing_year_carries_balances_and_blocks_posting(): void
    {
        $income = AccountHead::where('tenant_id', $this->tenant->id)->where('code', 'MEMBERSHIP')->first();
        $s = app(FinanceWorkspaceService::class);
        $s->approve($this->voucher(['kind' => 'income', 'account_head_id' => $income->id, 'amount' => 1000]), $this->admin->id);
        $next = FinancialYear::create(['label' => '2027-28', 'start_date' => '2027-04-01', 'end_date' => '2028-03-31', 'is_current' => false]);
        $s->closeYear($this->tenant->id, $this->year->id, $next->id, $this->admin->id, 'Reviewed');
        $this->assertEquals(1000, app(FinancialStatementsService::class)->trialBalance($this->tenant->id, $next->id)->firstWhere('code', 'CASH-BANK')->balance);
        $head = AccountHead::where('code', 'ADMIN-EXP')->first();
        $this->post('/sahodaya-admin/'.$this->tenant->id.'/ledger/expenses', ['account_head_id' => $head->id, 'amount' => 100, 'transaction_date' => '2026-10-25'])->assertSessionHasErrors('transaction_date');
    }

    public function test_pending_vouchers_prevent_year_closing(): void
    {
        $this->voucher();
        $this->post($this->base.'/years/close', ['financial_year_id' => $this->year->id, 'reason' => 'Review'])->assertStatus(422);
        $this->assertEquals(0, DB::table('finance_period_locks')->count());
    }

    public function test_financial_reports_export_csv(): void
    {
        $this->get($this->base.'/financial-statements?format=csv&report=trialBalance')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_voucher_reversal_preserves_original_and_nets_expense(): void
    {
        $this->travelTo(Carbon::parse('2026-10-25'));
        $d = $this->voucher();
        $service = app(FinanceWorkspaceService::class);
        $service->approve($d, $this->admin->id);
        $this->post($this->base.'/documents/'.$d->id.'/review', ['action' => 'reverse', 'reason' => 'Duplicate invoice'])->assertRedirect();
        $this->assertSame('reversed', $d->fresh()->status);
        $this->assertEquals(4, LedgerTransaction::count());
        $this->assertEquals(0, app(FinancialStatementsService::class)->incomeAndExpenditure($this->tenant->id, $this->year->id)['expense']);
        $service->reverse($d, $this->admin->id, 'Retry');
        $this->assertEquals(4, LedgerTransaction::count());
    }

    public function test_duplicate_partial_payable_payment_is_not_posted_twice(): void
    {
        $payable = SahodayaPayable::create(['tenant_id' => $this->tenant->id, 'financial_year_id' => $this->year->id, 'vendor_name' => 'Printer', 'amount' => 1000, 'status' => 'pending']);
        $key = (string) Str::uuid();
        $data = ['amount' => 200, 'payment_date' => '2026-10-25', 'submission_key' => $key];
        $this->post($this->base.'/payables/'.$payable->id.'/pay', $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->post($this->base.'/payables/'.$payable->id.'/pay', $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertEquals(200, $payable->fresh()->amount_paid);
        $this->assertEquals(4, LedgerTransaction::count());
    }

    public function test_unused_advance_can_be_returned_to_cash(): void
    {
        $service = app(FinanceWorkspaceService::class);
        $advance = $service->approve($this->voucher(['kind' => 'advance', 'amount' => 500]), $this->admin->id);
        $service->approve($this->voucher(['kind' => 'settlement', 'amount' => 300, 'parent_id' => $advance->id]), $this->admin->id);
        $service->approve($this->voucher(['kind' => 'advance_return', 'amount' => 200, 'parent_id' => $advance->id]), $this->admin->id);
        $this->assertEquals(0, app(FinancialStatementsService::class)->trialBalance($this->tenant->id, $this->year->id)->firstWhere('code', 'ADVANCES')->balance);
    }

    public function test_finance_workspace_rejects_non_finance_staff(): void
    {
        $this->admin->syncRoles(['data_entry']);
        $this->get($this->base.'/workspace')->assertForbidden();
    }

    public function test_bank_import_rejects_another_tenants_bank(): void
    {
        $bank = $this->bank();
        $bank->update(['tenant_id' => (string) Str::uuid()]);
        $this->post($this->base.'/bank-reconciliation/import', ['bank_account_id' => $bank->id, 'statement' => UploadedFile::fake()->createWithContent('test.csv', "date,description,amount\n2026-10-25,Test,100\n")])->assertNotFound();
    }

    public function test_only_end_date_filter_is_valid(): void
    {
        $this->get($this->base.'/financial-statements?to=2026-10-25')->assertOk();
    }

    public function test_carried_payable_can_be_paid_in_new_financial_year(): void
    {
        $this->travelTo(Carbon::parse('2026-10-25'));
        $bill = SahodayaPayable::create(['tenant_id' => $this->tenant->id, 'financial_year_id' => $this->year->id, 'vendor_name' => 'Printer', 'amount' => 500, 'incurred_date' => '2026-10-25', 'status' => 'pending']);
        app(PayableLedgerService::class)->recordObligation($bill, $this->admin->id);
        $next = FinancialYear::create(['label' => '2027-28', 'start_date' => '2027-04-01', 'end_date' => '2028-03-31', 'is_current' => false]);
        app(FinanceWorkspaceService::class)->closeYear($this->tenant->id, $this->year->id, $next->id, $this->admin->id, 'Reviewed');
        $this->travelTo(Carbon::parse('2027-04-02'));
        app(PayableLedgerService::class)->markPaid($bill, 500, $this->admin->id, '2027-04-02');
        $this->assertEquals('paid', $bill->fresh()->status);
        $this->assertEquals(2, LedgerTransaction::where('reference_type', 'payable_payment')->where('financial_year_id', $next->id)->count());
    }
}
