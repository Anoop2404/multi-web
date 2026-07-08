<?php

namespace Tests\Feature;

use App\Models\AccountHead;
use App\Models\FeeReceipt;
use App\Models\LedgerTransaction;
use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\McqSchoolFee;
use App\Models\MembershipPayment;
use App\Models\Registration;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Services\Ledger\FeeReceiptLedgerDispatcher;
use App\Services\Ledger\LedgerPostingService;
use App\Services\Ledger\LedgerService;
use App\Services\Membership\FeeReceiptService;
use App\Support\AcademicYear;
use App\Support\LedgerAccountCatalog;
use Database\Seeders\SahodayaMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LedgerPostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_income_receipt_posts_balanced_debit_and_credit(): void
    {
        $this->seed(SahodayaMasterDataSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Test Sahodaya',
            'domain'    => 'test-sahodaya.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'         => $sahodaya->id,
            'prefix'            => 'TST',
            'student_data_mode' => 'counts_only',
        ]);

        $school = Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Test School',
            'parent_id'         => $sahodaya->id,
            'school_prefix'     => 'TS',
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);

        $registration = Registration::create([
            'school_id'             => $school->id,
            'academic_year'         => AcademicYear::current(),
            'registration_status'   => 'approved',
            'membership_fee_amount' => 1000,
        ]);

        $payment = MembershipPayment::create([
            'school_id'          => $school->id,
            'academic_year'      => AcademicYear::current(),
            'registration_id'    => $registration->id,
            'amount'             => 1000,
            'payment_proof_path' => 'payments/test/proof.png',
            'status'             => 'submitted',
        ]);

        $receipt = app(FeeReceiptService::class)->createForMembershipPayment($payment);
        $receipt->update(['status' => 'approved', 'payment_date' => now()->toDateString()]);

        app(LedgerService::class)->postFeeReceipt($receipt, $sahodaya->id);

        $this->assertSame(2, LedgerTransaction::count());
        $this->assertNotNull(LedgerTransaction::first()->journal_id);
        $this->assertSame(
            LedgerTransaction::first()->journal_id,
            LedgerTransaction::orderByDesc('id')->first()->journal_id
        );

        $debits = LedgerTransaction::where('entry_type', 'debit')->sum('amount');
        $credits = LedgerTransaction::where('entry_type', 'credit')->sum('amount');
        $this->assertEquals(1000, (float) $debits);
        $this->assertEquals(1000, (float) $credits);

        $cash = AccountHead::where('tenant_id', $sahodaya->id)->where('code', 'CASH-BANK')->first();
        $membership = AccountHead::where('tenant_id', $sahodaya->id)->where('code', 'MEMBERSHIP')->first();
        $this->assertNotNull($cash);
        $this->assertNotNull($membership);

        app(LedgerService::class)->postFeeReceipt($receipt, $sahodaya->id);
        $this->assertSame(2, LedgerTransaction::count(), 'Posting is idempotent');
    }

    public function test_mcq_fee_receipt_posts_to_mcq_account_head(): void
    {
        $sahodayaId = (string) Str::uuid();

        $exam = McqExam::create([
            'tenant_id'    => $sahodayaId,
            'title'        => 'Science MCQ',
            'exam_type'    => 'assessment',
            'status'       => 'published',
            'fee_type'     => 'per_student',
            'fee_amount'   => 150,
        ]);

        $schoolId = (string) Str::uuid();

        $class = \App\Models\SchoolClass::create([
            'tenant_id' => $schoolId,
            'name'      => 'Class 10',
            'is_active' => true,
        ]);

        $student = \App\Models\Student::create([
            'tenant_id'       => $schoolId,
            'school_class_id' => $class->id,
            'name'            => 'Test Student',
            'status'          => 'active',
        ]);

        $registration = McqRegistration::create([
            'exam_id'    => $exam->id,
            'student_id' => $student->id,
            'school_id'  => $schoolId,
            'status'     => 'registered',
        ]);

        $receipt = FeeReceipt::create([
            'feeable_type' => McqRegistration::class,
            'feeable_id'   => $registration->id,
            'file_path'    => 'mcq-payments/test/proof.png',
            'amount'       => 150,
            'status'       => 'approved',
            'payment_date' => now()->toDateString(),
        ]);

        $registration->update(['fee_receipt_id' => $receipt->id]);

        app(FeeReceiptLedgerDispatcher::class)->postApproved($receipt, $sahodayaId);

        $this->assertSame(2, LedgerTransaction::count());
        $mcqHead = AccountHead::where('tenant_id', $sahodayaId)
            ->where('code', LedgerAccountCatalog::mcqExamFeeCode($exam->id))
            ->first();
        $this->assertNotNull($mcqHead);
        $this->assertSame('mcq', $mcqHead->category);
        $this->assertEquals(150, (float) LedgerTransaction::where('entry_type', 'credit')->sum('amount'));
    }

    public function test_mcq_school_batch_fee_posts_to_mcq_account_head_on_approval(): void
    {
        $sahodayaId = (string) Str::uuid();
        $schoolId = (string) Str::uuid();

        $school = Tenant::create([
            'id'        => $schoolId,
            'type'      => 'school',
            'name'      => 'Batch School',
            'parent_id' => $sahodayaId,
            'is_active' => true,
        ]);

        $exam = McqExam::create([
            'tenant_id'  => $sahodayaId,
            'title'      => 'District MCQ',
            'exam_type'  => 'assessment',
            'status'     => 'published',
            'fee_type'   => 'flat',
            'fee_amount' => 60,
        ]);

        $schoolFee = McqSchoolFee::create([
            'exam_id'       => $exam->id,
            'school_id'     => $school->id,
            'student_count' => 10,
            'total_due'     => 600,
            'status'        => 'proof_uploaded',
        ]);

        $receipt = FeeReceipt::create([
            'feeable_type' => McqSchoolFee::class,
            'feeable_id'   => $schoolFee->id,
            'file_path'    => 'mcq-payments/batch/proof.png',
            'amount'       => 600,
            'status'       => 'uploaded',
            'payment_date' => now()->toDateString(),
        ]);

        $schoolFee->update(['fee_receipt_id' => $receipt->id]);

        $this->assertSame(0, LedgerTransaction::count());

        $receipt->update(['status' => 'approved']);

        $this->assertSame(2, LedgerTransaction::count());
        $mcqHead = AccountHead::where('tenant_id', $sahodayaId)
            ->where('code', LedgerAccountCatalog::mcqExamFeeCode($exam->id))
            ->first();
        $this->assertNotNull($mcqHead);
        $this->assertSame('mcq', $mcqHead->category);
        $this->assertEquals(600, (float) LedgerTransaction::where('entry_type', 'credit')->sum('amount'));

        $credit = LedgerTransaction::where('entry_type', 'credit')->first();
        $this->assertStringContainsString('Batch School', $credit->description);
        $this->assertStringContainsString('District MCQ', $credit->description);
    }

    public function test_fest_school_batch_fee_posts_to_event_account_head_on_approval(): void
    {
        $sahodayaId = (string) Str::uuid();
        $schoolId = (string) Str::uuid();

        Tenant::create([
            'id'        => $schoolId,
            'type'      => 'school',
            'name'      => 'Fest Fee School',
            'parent_id' => $sahodayaId,
            'is_active' => true,
        ]);

        $event = \App\Models\FestEvent::create([
            'tenant_id'   => $sahodayaId,
            'title'       => 'District Sports Meet',
            'event_type'  => 'sports',
            'level_round' => 'sahodaya',
            'status'      => 'registration_open',
        ]);

        $schoolFee = \App\Models\FestSchoolEventFee::create([
            'event_id'  => $event->id,
            'school_id' => $schoolId,
            'total_due' => 1200,
            'status'    => 'proof_uploaded',
        ]);

        $receipt = FeeReceipt::create([
            'feeable_type' => \App\Models\FestSchoolEventFee::class,
            'feeable_id'   => $schoolFee->id,
            'file_path'    => 'fest-payments/batch/proof.png',
            'amount'       => 1200,
            'status'       => 'uploaded',
            'payment_date' => now()->toDateString(),
        ]);

        $schoolFee->update(['fee_receipt_id' => $receipt->id]);

        $this->assertSame(0, LedgerTransaction::count());

        $receipt->update(['status' => 'approved']);

        $this->assertSame(2, LedgerTransaction::count());
        $eventHead = AccountHead::where('tenant_id', $sahodayaId)
            ->where('code', LedgerAccountCatalog::sportsEventFeeCode($event->id))
            ->first();
        $this->assertNotNull($eventHead);
        $this->assertSame('sports', $eventHead->category);
        $this->assertStringStartsWith('SPT-', $eventHead->code);
        $this->assertEquals(1200, (float) LedgerTransaction::where('entry_type', 'credit')->sum('amount'));

        $credit = LedgerTransaction::where('entry_type', 'credit')->first();
        $this->assertStringContainsString('Fest Fee School', $credit->description);
        $this->assertStringContainsString('District Sports Meet', $credit->description);
    }

    public function test_kalotsav_school_batch_fee_posts_to_fest_event_account_head(): void
    {
        $sahodayaId = (string) Str::uuid();
        $schoolId = (string) Str::uuid();

        Tenant::create([
            'id'        => $schoolId,
            'type'      => 'school',
            'name'      => 'Kalotsav School',
            'parent_id' => $sahodayaId,
            'is_active' => true,
        ]);

        $event = \App\Models\FestEvent::create([
            'tenant_id'   => $sahodayaId,
            'title'       => 'District Kalotsav',
            'event_type'  => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status'      => 'registration_open',
        ]);

        $schoolFee = \App\Models\FestSchoolEventFee::create([
            'event_id'  => $event->id,
            'school_id' => $schoolId,
            'total_due' => 800,
            'status'    => 'proof_uploaded',
        ]);

        $receipt = FeeReceipt::create([
            'feeable_type' => \App\Models\FestSchoolEventFee::class,
            'feeable_id'   => $schoolFee->id,
            'file_path'    => 'fest-payments/kalotsav/proof.png',
            'amount'       => 800,
            'status'       => 'uploaded',
            'payment_date' => now()->toDateString(),
        ]);

        $schoolFee->update(['fee_receipt_id' => $receipt->id]);
        $receipt->update(['status' => 'approved']);

        $head = AccountHead::where('tenant_id', $sahodayaId)
            ->where('code', LedgerAccountCatalog::festIncomeCode($event))
            ->first();

        $this->assertNotNull($head);
        $this->assertSame('event', $head->category);
        $this->assertStringStartsWith('EVT-', $head->code);
    }

    public function test_manual_pair_creates_balanced_journal(): void
    {
        $sahodayaId = (string) Str::uuid();

        $posting = app(LedgerPostingService::class);
        $posting->ensureDefaultHeads($sahodayaId);

        $expense = AccountHead::where('tenant_id', $sahodayaId)->where('code', 'AWARDS-FUND')->firstOrFail();

        $posting->postManualPair(
            $sahodayaId,
            $expense->id,
            'debit',
            250,
            'Prize payout',
            now()->toDateString(),
            1,
        );

        $this->assertSame(2, LedgerTransaction::count());
        $this->assertEquals(250, (float) LedgerTransaction::where('entry_type', 'debit')->sum('amount'));
        $this->assertEquals(250, (float) LedgerTransaction::where('entry_type', 'credit')->sum('amount'));
    }
}
