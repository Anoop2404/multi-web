<?php

namespace Tests\Unit\Support;

use App\Models\ClassCategory;
use App\Models\MasterClass;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Support\FestClassGroupScheme;
use App\Support\FestStudentClassResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestClassGroupSchemeClusterTest extends TestCase
{
    use RefreshDatabase;

    public function test_cluster_labels_use_membership_class_master(): void
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Cluster Sahodaya',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'fest_class_group_scheme' => 'cluster',
        ]);

        $category = ClassCategory::create([
            'sahodaya_id' => null,
            'code' => 'CAT3',
            'label' => 'CATEGORY3',
            'sort_order' => 3,
            'is_active' => true,
        ]);

        MasterClass::create([
            'sahodaya_id' => $sahodaya->id,
            'class_category_id' => $category->id,
            'name' => '8',
            'display_order' => 8,
            'is_active' => true,
        ]);
        MasterClass::create([
            'sahodaya_id' => $sahodaya->id,
            'class_category_id' => $category->id,
            'name' => '9',
            'display_order' => 9,
            'is_active' => true,
        ]);

        $labels = FestClassGroupScheme::labelsForSahodaya($sahodaya->id);

        $this->assertArrayHasKey(FestClassGroupScheme::clusterKey($category->id), $labels);
        $this->assertStringContainsString('CATEGORY3', $labels[FestClassGroupScheme::clusterKey($category->id)]);
        $this->assertStringContainsString('8', $labels[FestClassGroupScheme::clusterKey($category->id)]);
        $this->assertStringContainsString('9', $labels[FestClassGroupScheme::clusterKey($category->id)]);
    }

    public function test_cluster_student_group_uses_school_class_category(): void
    {
        $category = ClassCategory::create([
            'sahodaya_id' => null,
            'code' => 'CAT4',
            'label' => 'CATEGORY4',
            'sort_order' => 4,
            'is_active' => true,
        ]);

        $student = new Student(['name' => 'Test']);
        $student->setRelation('schoolClass', new SchoolClass([
            'name' => '11',
            'class_category_id' => $category->id,
        ]));

        $this->assertSame(
            FestClassGroupScheme::clusterKey($category->id),
            FestStudentClassResolver::clusterClassGroupForStudent($student),
        );
    }
}
