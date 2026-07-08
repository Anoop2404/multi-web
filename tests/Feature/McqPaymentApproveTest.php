<?php

namespace Tests\Feature;

use App\Models\FeeReceipt;
use App\Models\LedgerTransaction;
use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\McqSchoolFee;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Mcq\McqSchoolFeeService;
use App\Support\LedgerAccountCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class McqPaymentApproveTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_approve_issues_hall_tickets_and_posts_ledger(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Test Sahodaya',
            'domain'    => 'mcq-approve.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'           => $sahodaya->id,
            'prefix'              => 'TS',
            'student_data_mode'   => 'counts_only',
            'receipt_next_number' => 1,
        ]);

        $school = Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Test School',
            'parent_id'         => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id]);
        Role::findByName('sahodaya_admin', 'web');
        $admin->assignRole('sahodaya_admin');

        $exam = McqExam::create([
            'tenant_id'            => $sahodaya->id,
            'title'                => 'Math MCQ',
            'exam_type'            => 'assessment',
            'status'               => 'published',
            'fee_type'             => 'flat',
            'fee_amount'           => 50,
            'next_hall_ticket_no'  => 1001,
        ]);

        $class = SchoolClass::create([
            'tenant_id' => $school->id,
            'name'      => 'Class 10',
            'is_active' => true,
        ]);

        $registrations = collect([1, 2])->map(function (int $n) use ($exam, $school, $class) {
            $student = Student::create([
                'tenant_id'       => $school->id,
                'school_class_id' => $class->id,
                'name'            => "Student {$n}",
                'status'          => 'active',
            ]);

            return McqRegistration::create([
                'exam_id'         => $exam->id,
                'student_id'      => $student->id,
                'school_id'       => $school->id,
                'status'          => 'registered',
                'approval_status' => 'pending_payment',
            ]);
        });

        $schoolFee = McqSchoolFee::create([
            'exam_id'       => $exam->id,
            'school_id'     => $school->id,
            'student_count' => 2,
            'total_due'     => 100,
            'status'        => 'proof_uploaded',
        ]);

        $receipt = FeeReceipt::create([
            'feeable_type' => McqSchoolFee::class,
            'feeable_id'   => $schoolFee->id,
            'file_path'    => 'mcq-payments/test/proof.png',
            'amount'       => 100,
            'status'       => 'uploaded',
            'payment_date' => now()->toDateString(),
        ]);

        $schoolFee->update(['fee_receipt_id' => $receipt->id]);

        $this->assertSame(0, LedgerTransaction::count());

        $approvedCount = app(McqSchoolFeeService::class)->approve($schoolFee->fresh(), $admin->id);

        $this->assertSame(2, $approvedCount);

        $receipt->refresh();
        $schoolFee->refresh();

        $this->assertSame('approved', $receipt->status);
        $this->assertSame('approved', $schoolFee->status);
        $this->assertNotNull($receipt->receipt_number);

        foreach ($registrations as $index => $registration) {
            $registration->refresh();
            $this->assertSame('approved', $registration->approval_status);
            $this->assertSame((string) (1001 + $index), $registration->hall_ticket_no);
        }

        $this->assertSame(2, LedgerTransaction::count());
        $mcqHead = \App\Models\AccountHead::where('tenant_id', $sahodaya->id)
            ->where('code', LedgerAccountCatalog::mcqExamFeeCode($exam->id))
            ->first();
        $this->assertNotNull($mcqHead);
        $this->assertEquals(100, (float) LedgerTransaction::where('entry_type', 'credit')->sum('amount'));
    }
}
