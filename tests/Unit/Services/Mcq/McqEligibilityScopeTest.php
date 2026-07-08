<?php

namespace Tests\Unit\Services\Mcq;

use App\Models\ClassCategory;
use App\Models\MasterClass;
use App\Models\McqExam;
use App\Models\SchoolClass;
use App\Services\Mcq\McqEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class McqEligibilityScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_classes_scope_returns_null(): void
    {
        $exam = new McqExam(['tenant_id' => (string) Str::uuid(), 'eligibility_config' => []]);

        $this->assertNull(
            app(McqEligibilityService::class)->eligibleSchoolClassIds($exam, (string) Str::uuid())
        );
    }

    public function test_category_scope_returns_only_matching_school_classes(): void
    {
        $sahodayaId = (string) Str::uuid();
        $schoolId = (string) Str::uuid();

        $primary = ClassCategory::create([
            'sahodaya_id' => $sahodayaId,
            'code'        => 'PRIM',
            'label'       => 'Primary',
            'is_active'   => true,
        ]);

        $senior = ClassCategory::create([
            'sahodaya_id' => $sahodayaId,
            'code'        => 'SEN',
            'label'       => 'Senior',
            'is_active'   => true,
        ]);

        $class3 = SchoolClass::create([
            'tenant_id'         => $schoolId,
            'class_category_id' => $primary->id,
            'name'              => 'Class 3',
            'is_active'         => true,
        ]);

        SchoolClass::create([
            'tenant_id'         => $schoolId,
            'class_category_id' => $senior->id,
            'name'              => 'Class 10',
            'is_active'         => true,
        ]);

        $exam = new McqExam([
            'tenant_id'         => $sahodayaId,
            'eligibility_config' => [
                'assignment_type'    => 'category',
                'class_category_ids' => [$primary->id],
            ],
        ]);

        $ids = app(McqEligibilityService::class)->eligibleSchoolClassIds($exam, $schoolId);

        $this->assertSame([$class3->id], $ids);
    }

    public function test_class_scope_matches_by_master_class_number(): void
    {
        $sahodayaId = (string) Str::uuid();
        $schoolId = (string) Str::uuid();

        $category = ClassCategory::create([
            'sahodaya_id' => $sahodayaId,
            'code'        => 'UP',
            'label'       => 'Upper Primary',
            'is_active'   => true,
        ]);

        $master = MasterClass::create([
            'class_category_id' => $category->id,
            'name'              => 'Class 8',
        ]);

        $class8 = SchoolClass::create([
            'tenant_id' => $schoolId,
            'name'      => 'Class 8',
            'is_active' => true,
        ]);

        SchoolClass::create([
            'tenant_id' => $schoolId,
            'name'      => 'Class 9',
            'is_active' => true,
        ]);

        $exam = new McqExam([
            'tenant_id'         => $sahodayaId,
            'eligibility_config' => [
                'assignment_type'  => 'class',
                'master_class_ids' => [$master->id],
            ],
        ]);

        $ids = app(McqEligibilityService::class)->eligibleSchoolClassIds($exam, $schoolId);

        $this->assertSame([$class8->id], $ids);
    }
}
