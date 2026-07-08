<?php

namespace Tests\Unit\Services\Mcq;

use App\Models\McqExam;
use App\Models\Registration;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Services\Mcq\McqRegistrationGateService;
use App\Support\AcademicYear;
use Database\Seeders\SahodayaMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class McqRegistrationGateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_allows_school_without_annual_registration(): void
    {
        $this->seed(SahodayaMasterDataSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Test Sahodaya',
            'domain'    => 'test-sahodaya.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'         => $sahodaya->id,
            'prefix'            => 'TS',
            'student_data_mode' => 'counts_only',
        ]);

        $school = Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Test School',
            'parent_id'         => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);

        $exam = new McqExam(['tenant_id' => $sahodaya->id, 'status' => 'published']);

        $reason = app(McqRegistrationGateService::class)->blockReason($exam, $school);

        $this->assertNull($reason);
    }

    public function test_blocks_rejected_school(): void
    {
        $this->seed(SahodayaMasterDataSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Test Sahodaya',
            'domain'    => 'test-sahodaya-2.test',
            'is_active' => true,
        ]);

        $school = Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Test School',
            'parent_id'         => $sahodaya->id,
            'membership_status' => 'rejected',
            'is_active'         => true,
        ]);

        $exam = new McqExam(['tenant_id' => $sahodaya->id, 'status' => 'published']);

        $reason = app(McqRegistrationGateService::class)->blockReason($exam, $school);

        $this->assertSame('Your school application was rejected.', $reason);
    }

    public function test_allows_school_with_completed_annual_registration(): void
    {
        $this->seed(SahodayaMasterDataSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Test Sahodaya',
            'domain'    => 'test-sahodaya-3.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'         => $sahodaya->id,
            'prefix'            => 'TS',
            'student_data_mode' => 'counts_only',
        ]);

        $school = Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Test School',
            'parent_id'         => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);

        Registration::create([
            'school_id'           => $school->id,
            'academic_year'       => AcademicYear::forSchool($school),
            'registration_status' => 'completed',
        ]);

        $exam = new McqExam(['tenant_id' => $sahodaya->id, 'status' => 'published']);

        $reason = app(McqRegistrationGateService::class)->blockReason($exam, $school->fresh());

        $this->assertNull($reason);
    }
}
