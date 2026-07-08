<?php

namespace Tests\Unit\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Services\Mcq\McqMarkSaveService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class McqMarkSaveServiceTest extends TestCase
{
    public function test_rejects_marks_for_absent_student(): void
    {
        $exam = new McqExam(['tenant_id' => 'test-tenant', 'delivery_mode' => 'offline', 'total_questions' => 20]);
        $registration = new McqRegistration(['attendance_status' => 'absent']);

        $this->expectException(ValidationException::class);

        app(McqMarkSaveService::class)->save($exam, $registration, [
            'correct_count' => 10,
            'wrong_count' => 5,
            'unanswered_count' => 5,
            'score' => 10,
        ], 1);
    }
}
