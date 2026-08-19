<?php

namespace Tests\Unit\Support;

use App\Models\SchoolClass;
use App\Models\SchoolYearStudentCount;
use App\Models\SchoolYearSubmission;
use App\Models\Tenant;
use App\Support\SchoolClassCategoryResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SchoolClassCategoryResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_fee_tier_for_school_with_class_12_returns_senior_secondary(): void
    {
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'St. Joseph School',
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        SchoolClass::create([
            'tenant_id' => $school->id,
            'name' => 'Class 12',
            'is_active' => true,
        ]);

        $tier = SchoolClassCategoryResolver::feeTierFor($school);
        $this->assertEquals('senior_secondary', $tier);
    }

    public function test_fee_tier_for_school_with_roman_numeral_xii_returns_senior_secondary(): void
    {
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Bhavans Vidya Mandir',
            'membership_status' => 'approved',
            'application_payload' => ['highest_class' => 'Class XII'],
            'is_active' => true,
        ]);

        $tier = SchoolClassCategoryResolver::feeTierFor($school);
        $this->assertEquals('senior_secondary', $tier);
    }

    public function test_fee_tier_for_school_with_student_counts_in_class_12_returns_senior_secondary(): void
    {
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Marygiri English Medium School',
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $submission = SchoolYearSubmission::create([
            'school_id' => $school->id,
            'academic_year' => '2026-27',
            'status' => 'approved',
        ]);

        $schoolClass = SchoolClass::create([
            'tenant_id' => $school->id,
            'name' => '12th Grade',
            'is_active' => true,
        ]);

        SchoolYearStudentCount::create([
            'school_year_submission_id' => $submission->id,
            'school_class_id' => $schoolClass->id,
            'male_count' => 25,
            'female_count' => 25,
            'total_count' => 50,
        ]);

        $tier = SchoolClassCategoryResolver::feeTierFor($school);
        $this->assertEquals('senior_secondary', $tier);
    }

    public function test_fee_tier_for_secondary_high_school_returns_secondary(): void
    {
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'High School Calicut',
            'membership_status' => 'approved',
            'application_payload' => ['highest_class' => 'Class X'],
            'is_active' => true,
        ]);

        SchoolClass::create([
            'tenant_id' => $school->id,
            'name' => 'Class 10',
            'is_active' => true,
        ]);

        $tier = SchoolClassCategoryResolver::feeTierFor($school);
        $this->assertEquals('secondary', $tier);
    }

    public function test_fee_tier_for_primary_school_returns_other(): void
    {
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Little Flower Primary School',
            'membership_status' => 'approved',
            'application_payload' => ['highest_class' => 'Class 5'],
            'is_active' => true,
        ]);

        $tier = SchoolClassCategoryResolver::feeTierFor($school);
        $this->assertEquals('other', $tier);
    }
}
