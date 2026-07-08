<?php

namespace Tests\Unit\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Services\Mcq\McqGradeService;
use App\Services\Mcq\McqMarkSaveService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class McqGradeServiceTest extends TestCase
{
    public function test_default_grade_for_percentage(): void
    {
        $exam = new McqExam(['tenant_id' => 'test-tenant']);
        $service = app(McqGradeService::class);

        $this->assertSame('A+', $service->gradeForPercentage($exam, 96));
        $this->assertSame('B', $service->gradeForPercentage($exam, 80));
        $this->assertSame('F', $service->gradeForPercentage($exam, 20));
    }
}
