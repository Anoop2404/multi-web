<?php

namespace Tests\Unit\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqMark;
use App\Models\McqRegistration;
use App\Services\Mcq\McqCertificateService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class McqCertificateServiceTest extends TestCase
{
    public function test_rejects_certificate_for_absent_student(): void
    {
        $exam = new McqExam(['results_published' => true]);
        $registration = new McqRegistration([
            'status' => 'submitted',
            'attendance_status' => 'absent',
        ]);
        $registration->setRelation('exam', $exam);
        $registration->setRelation('mark', new McqMark(['score' => 10]));

        $this->expectException(ValidationException::class);

        app(McqCertificateService::class)->assertEligible($registration);
    }
}
