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

    public function test_attendance_excludes_unissued_numbers_and_cancelled_registrations(): void
    {
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Attendance School',
        ]);
        $exam = McqExam::create([
            'tenant_id' => (string) Str::uuid(),
            'title' => 'Talent Search',
            'status' => 'published',
        ]);
        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => '7']);
        foreach ([null, '', '   ', '180567', '180568'] as $index => $number) {
            $student = Student::create([
                'tenant_id' => $school->id,
                'school_class_id' => $class->id,
                'name' => 'Candidate '.$index,
                'status' => 'active',
            ]);
            McqRegistration::create([
                'exam_id' => $exam->id,
                'school_id' => $school->id,
                'student_id' => $student->id,
                'hall_ticket_no' => $number,
                'status' => $index === 4 ? 'cancelled' : 'registered',
            ]);
        }

        $reports = app(McqReportService::class);
        // General registration reports must still show candidates awaiting a number.
        $this->assertCount(4, $reports->registrationRows($exam));
        $rows = $reports->registrationRows($exam, $school->id, attendanceOnly: true);
        $this->assertCount(1, $rows);
        $this->assertSame('180567', $rows[0]['hall_ticket_no']);

        // Check the PDF dataset before grouping/counting and rendering.
        $method = new \ReflectionMethod(McqPrintableDocumentService::class, 'registrationRows');
        $pdfRows = $method->invoke(app(McqPrintableDocumentService::class), $exam, false, false, $school->id, null, true);
        $this->assertCount(1, $pdfRows);
        $this->assertSame('180567', $pdfRows[0]['hall_ticket_no']);
        $this->assertSame(1, $pdfRows[0]['sl']);
        $response = app(McqPrintableDocumentService::class)->attendanceSheetPdf(
            $exam, schoolId: $school->id, selectedClass: '7', sahodaya: $school
        );
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringStartsWith('%PDF-', $response->getContent());

        for ($index = 0; $index < 40; $index++) {
            $student = Student::create([
                'tenant_id' => $school->id,
                'school_class_id' => $class->id,
                'name' => 'Batch Candidate '.$index,
                'status' => 'active',
            ]);
            McqRegistration::create([
                'exam_id' => $exam->id,
                'school_id' => $school->id,
                'student_id' => $student->id,
                'hall_ticket_no' => (string) (190000 + $index),
                'status' => 'registered',
            ]);
        }
        $batches = [];
        \Illuminate\Support\Facades\View::composer('mcq.attendance-sheet', function ($view) use (&$batches) {
            $batches[] = $view->getData();
        });
        $response = app(McqPrintableDocumentService::class)->attendanceSheetPdf(
            $exam, schoolId: $school->id, selectedClass: '7', inline: true, sahodaya: $school
        );
        $this->assertCount(2, $batches);
        $this->assertCount(40, $batches[0]['groupedRows'][7]);
        $this->assertCount(1, $batches[1]['groupedRows'][7]);
        $this->assertSame(41, $batches[1]['groupedRows'][7][0]['sl']);
        $this->assertSame(41, $batches[1]['classCounts'][7]);
        $this->assertSame(41, $batches[1]['totalCount']);
        $parser = new \setasign\Fpdi\Fpdi();
        $pageCount = $parser->setSourceFile(\setasign\Fpdi\PdfParser\StreamReader::createByString($response->getContent()));
        $this->assertGreaterThanOrEqual(2, $pageCount);
        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));

    }

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

    public function test_new_registration_defaults_attendance_to_pending(): void
    {
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Default Pending School',
        ]);
        $exam = McqExam::create([
            'tenant_id' => (string) Str::uuid(),
            'title' => 'Default Pending Exam',
            'status' => 'published',
        ]);
        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => '8']);
        $student = Student::create([
            'tenant_id' => $school->id,
            'school_class_id' => $class->id,
            'name' => 'Default Candidate',
            'status' => 'active',
        ]);

        $registration = McqRegistration::create([
            'exam_id' => $exam->id,
            'school_id' => $school->id,
            'student_id' => $student->id,
            'status' => 'registered',
        ]);

        $this->assertSame('pending', $registration->fresh()->attendance_status);
        $this->assertNull($registration->fresh()->attendance_marked_at);
        $this->assertFalse(app(\App\Services\Mcq\McqAttendanceCorrectionService::class)->requiresApproval($registration->fresh()));
    }

    public function test_school_wise_absent_report_groups_absentees_by_school(): void
    {
        $sahodayaId = (string) Str::uuid();
        $schoolA = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Alpha School',
            'parent_id' => $sahodayaId,
        ]);
        $schoolB = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Beta School',
            'parent_id' => $sahodayaId,
        ]);
        $exam = McqExam::create([
            'tenant_id' => $sahodayaId,
            'title' => 'Absent Report Exam',
            'status' => 'published',
        ]);

        foreach ([[$schoolA, 'A1', 'absent'], [$schoolA, 'A2', 'present'], [$schoolB, 'B1', 'absent'], [$schoolB, 'B2', 'absent']] as [$school, $ticket, $status]) {
            $class = SchoolClass::firstOrCreate(
                ['tenant_id' => $school->id, 'name' => '9'],
                ['is_active' => true],
            );
            $student = Student::create([
                'tenant_id' => $school->id,
                'school_class_id' => $class->id,
                'name' => "Student {$ticket}",
                'status' => 'active',
            ]);
            McqRegistration::create([
                'exam_id' => $exam->id,
                'school_id' => $school->id,
                'student_id' => $student->id,
                'hall_ticket_no' => $ticket,
                'status' => 'registered',
                'attendance_status' => $status,
                'attendance_marked_at' => now(),
            ]);
        }

        $reports = app(McqReportService::class);
        $schoolRows = $reports->schoolWiseAbsentRows($exam);
        $this->assertCount(2, $schoolRows);
        $this->assertSame('Beta School', $schoolRows[0]['school_name']);
        $this->assertSame(2, $schoolRows[0]['absent']);
        $this->assertSame('Alpha School', $schoolRows[1]['school_name']);
        $this->assertSame(1, $schoolRows[1]['absent']);

        $absentees = $reports->absentReportRows($exam);
        $this->assertCount(3, $absentees);

        $scoped = $reports->schoolWiseAbsentRows($exam, $schoolA->id);
        $this->assertCount(1, $scoped);
        $this->assertSame(1, $scoped[0]['absent']);
    }

    public function test_reports_include_withdrawn_student_names_and_classes_without_blank_rows(): void
    {
        $sahodayaId = (string) Str::uuid();
        $schoolId = (string) Str::uuid();

        $school = Tenant::create([
            'id'        => $schoolId,
            'type'      => 'school',
            'name'      => 'Test School',
            'parent_id' => $sahodayaId,
        ]);

        $exam = McqExam::create([
            'tenant_id' => $sahodayaId,
            'title'     => 'STSE Exam 2',
            'status'    => 'published',
        ]);

        $class = SchoolClass::create([
            'tenant_id' => $schoolId,
            'name'      => '10',
        ]);

        $student = Student::create([
            'tenant_id'       => $schoolId,
            'school_class_id' => $class->id,
            'name'            => 'Withdrawn Student',
            'status'          => 'active',
        ]);

        $reg = McqRegistration::create([
            'exam_id'   => $exam->id,
            'student_id'=> $student->id,
            'school_id' => $schoolId,
            'status'    => 'registered',
        ]);

        // Soft-delete the student
        $student->delete();

        $reg->refresh();
        $this->assertSame('Withdrawn Student (Withdrawn)', $reg->participantName());

        $reportService = app(McqReportService::class);
        $rows = $reportService->registrationRows($exam, $schoolId);

        $this->assertCount(1, $rows);
        $this->assertSame('Withdrawn Student (Withdrawn)', $rows[0]['student_name']);
        $this->assertSame('10', $rows[0]['class_name']);
    }
}
