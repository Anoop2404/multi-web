<?php

namespace Tests\Unit\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Mcq\McqSchoolFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class McqSchoolFeeDiscountTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_uses_discounted_payable_per_student(): void
    {
        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Sahodaya',
            'is_active' => true,
        ]);

        $school = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'school',
            'name'      => 'School',
            'parent_id' => $sahodaya->id,
            'is_active' => true,
        ]);

        $exam = McqExam::create([
            'tenant_id'              => $sahodaya->id,
            'title'                  => 'Talent Search L1',
            'status'                 => 'published',
            'fee_type'               => 'flat',
            'fee_amount'             => 150,
            'school_discount_amount' => 30,
        ]);

        $classA = SchoolClass::create(['tenant_id' => $school->id, 'name' => 'Class 8', 'is_active' => true]);
        $classB = SchoolClass::create(['tenant_id' => $school->id, 'name' => 'Class 9', 'is_active' => true]);

        foreach ([$classA, $classB] as $index => $class) {
            $student = Student::create([
                'tenant_id'       => $school->id,
                'school_class_id' => $class->id,
                'name'            => 'Student '.($index + 1),
                'status'          => 'active',
            ]);

            McqRegistration::create([
                'exam_id'    => $exam->id,
                'student_id' => $student->id,
                'school_id'  => $school->id,
                'status'     => 'registered',
            ]);
        }

        $service = app(McqSchoolFeeService::class);
        $fee = $service->syncForSchool($exam, $school);
        $breakdown = $service->breakdownForSchool($exam, $school);

        $this->assertSame(2, $fee->student_count);
        $this->assertEquals(240.0, (float) $fee->total_due);
        $this->assertEquals(150.0, $breakdown['student_fee']);
        $this->assertEquals(30.0, $breakdown['school_discount']);
        $this->assertEquals(120.0, $breakdown['payable_per_student']);
        $this->assertEquals(300.0, $breakdown['student_fee_total']);
        $this->assertEquals(60.0, $breakdown['discount_total']);
        $this->assertEquals(240.0, $breakdown['payable_total']);
        $this->assertCount(2, $breakdown['by_class']);
        $this->assertEquals(120.0, $breakdown['by_class'][0]['payable_total']);
    }
}
