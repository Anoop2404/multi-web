<?php

namespace Tests\Feature;

use App\Models\FeeReceipt;
use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\McqSchoolFee;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Mcq\McqPrintableDocumentService;
use App\Services\Mcq\McqReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class McqReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_rows_and_pdf_reports_resolve_batch_school_fee_status(): void
    {
        $sahodayaId = (string) Str::uuid();
        $schoolId = (string) Str::uuid();

        $school = Tenant::create([
            'id'        => $schoolId,
            'type'      => 'school',
            'name'      => 'Ideal English School',
            'parent_id' => $sahodayaId,
            'is_active' => true,
        ]);

        $exam = McqExam::create([
            'tenant_id'  => $sahodayaId,
            'title'      => 'STSE Exam 1',
            'exam_type'  => 'assessment',
            'status'     => 'published',
            'fee_type'   => 'flat',
            'fee_amount' => 50,
        ]);

        $class = SchoolClass::create([
            'tenant_id' => $schoolId,
            'name'      => '7',
            'is_active' => true,
        ]);

        $student = Student::create([
            'tenant_id'       => $schoolId,
            'school_class_id' => $class->id,
            'name'            => 'Goutham V',
            'admission_number'=> '9551',
            'status'          => 'active',
        ]);

        $registration = McqRegistration::create([
            'exam_id'         => $exam->id,
            'student_id'      => $student->id,
            'school_id'       => $schoolId,
            'status'          => 'registered',
            'approval_status' => 'pending_payment',
        ]);

        $schoolFee = McqSchoolFee::create([
            'exam_id'       => $exam->id,
            'school_id'     => $schoolId,
            'student_count' => 1,
            'total_due'     => 50,
            'status'        => 'proof_uploaded',
        ]);

        $receipt = FeeReceipt::create([
            'feeable_type' => McqSchoolFee::class,
            'feeable_id'   => $schoolFee->id,
            'file_path'    => 'proof.jpg',
            'amount'       => 50,
            'status'       => 'uploaded',
        ]);

        $schoolFee->update(['fee_receipt_id' => $receipt->id]);

        $reportService = app(McqReportService::class);
        $rows = $reportService->registrationRows($exam, $schoolId);

        $this->assertCount(1, $rows);
        $this->assertSame('Goutham V', $rows[0]['student_name']);
        $this->assertSame('uploaded', $rows[0]['fee_status']);

        // Verify PDF generation does not throw exception
        $printable = app(McqPrintableDocumentService::class);
        $response = $printable->classWiseRegistrationPdf($exam, $schoolId, null, true);
        $this->assertSame(200, $response->getStatusCode());
    }
}
