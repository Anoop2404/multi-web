<?php

namespace Tests\Feature;

use App\Models\SahodayaProfile;
use App\Models\SahodayaRegistrationWindow;
use App\Models\SchoolClass;
use App\Models\SchoolLockOverride;
use App\Models\Student;
use App\Models\StudentEditChangeRequest;
use App\Models\Tenant;
use App\Services\Membership\RegistrationStatusService;
use App\Services\Membership\SchoolYearSubmissionReviewService;
use App\Services\Students\StudentEditChangeService;
use App\Services\Students\StudentEditLockService;
use App\Support\AcademicYear;
use Database\Seeders\SahodayaMasterDataSeeder;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentLockAndSubmissionReviewTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, school: Tenant, schoolClass: SchoolClass} */
    private function clusterWithFullRecords(): array
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
            'tenant_id'                   => $sahodaya->id,
            'prefix'                      => 'TST',
            'student_data_mode'           => 'full_records',
            'membership_fee_type'         => 'fixed',
            'fixed_membership_fee_amount' => 5000,
            'student_edit_lock_enabled'   => true,
        ]);

        $school = Tenant::create([
            'id'            => (string) Str::uuid(),
            'type'          => 'school',
            'name'          => 'Test School',
            'parent_id'     => $sahodaya->id,
            'school_prefix' => 'TS',
            'is_active'     => true,
        ]);

        $schoolClass = SchoolClass::where('tenant_id', $school->id)->first()
            ?? SchoolClass::create([
                'tenant_id'         => $school->id,
                'name'              => '10',
                'class_category_id' => 1,
                'is_active'         => true,
            ]);

        return compact('sahodaya', 'school', 'schoolClass');
    }

    public function test_create_change_request_when_locked(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'schoolClass' => $schoolClass] = $this->clusterWithFullRecords();

        SahodayaProfile::where('tenant_id', $sahodaya->id)->update(['student_edit_lock_enabled' => false]);

        SchoolLockOverride::create([
            'sahodaya_id'        => $sahodaya->id,
            'school_id'          => $school->id,
            'override_type'      => 'lock_all',
            'created_by_user_id' => null,
        ]);

        $httpRequest = Request::create('/test', 'POST', [
            'school_class_id' => $schoolClass->id,
            'name'            => 'New Student',
            'gender'          => 'male',
            'dob'             => '2012-05-01',
            'reason'          => 'Late admission',
        ], [], [
            'photo' => UploadedFile::fake()->image('photo.jpg'),
        ]);

        $request = app(StudentEditChangeService::class)->submitCreate($httpRequest, $school);

        $this->assertSame('create', $request->change_type);
        $this->assertSame('pending', $request->status);
        $this->assertSame('pending_school', $request->school_approval_status);
        $this->assertNull($request->student_id);
    }

    public function test_emergency_lock_rejects_change_requests(): void
    {
        ['school' => $school, 'schoolClass' => $schoolClass] = $this->clusterWithFullRecords();

        $httpRequest = Request::create('/test', 'POST', [
            'school_class_id' => $schoolClass->id,
            'name'            => 'Blocked Student',
            'gender'          => 'male',
            'dob'             => '2012-05-01',
            'reason'          => 'Late admission',
        ], [], [
            'photo' => UploadedFile::fake()->image('photo.jpg'),
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(StudentEditChangeService::class)->submitCreate($httpRequest, $school);
    }

    public function test_approve_create_change_request_creates_student(): void
    {
        ['school' => $school, 'schoolClass' => $schoolClass] = $this->clusterWithFullRecords();

        $changeRequest = StudentEditChangeRequest::create([
            'school_id'              => $school->id,
            'student_id'             => null,
            'change_type'            => 'create',
            'status'                 => 'pending',
            'school_approval_status' => 'school_approved',
            'changes_json'           => [
                'school_class_id' => $schoolClass->id,
                'name'            => 'Approved Student',
                'gender'          => 'female',
                'dob'             => '2011-03-15',
            ],
            'photo_path'   => 'students/'.$school->id.'/test.jpg',
            'reason'       => 'Late admission',
        ]);

        $student = app(StudentEditChangeService::class)->approve($changeRequest);

        $this->assertSame('Approved Student', $student->name);
        $this->assertDatabaseHas('students', ['id' => $student->id, 'tenant_id' => $school->id]);
        $this->assertSame('approved', $changeRequest->fresh()->status);
    }

    public function test_resolve_window_state_emergency_lock(): void
    {
        ['school' => $school] = $this->clusterWithFullRecords();

        $state = app(StudentEditLockService::class)->resolveWindowState($school);

        $this->assertFalse($state['can_add']);
        $this->assertFalse($state['can_edit']);
        $this->assertSame('emergency_lock', $state['source']);
    }

    public function test_school_override_unlocks_add(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->clusterWithFullRecords();

        SahodayaProfile::where('tenant_id', $sahodaya->id)->update(['student_edit_lock_enabled' => false]);

        SchoolLockOverride::create([
            'sahodaya_id'        => $sahodaya->id,
            'school_id'          => $school->id,
            'override_type'      => 'unlock_add',
            'expires_at'         => now()->addDay(),
            'created_by_user_id' => null,
        ]);

        $state = app(StudentEditLockService::class)->resolveWindowState($school);

        $this->assertTrue($state['can_add']);
        $this->assertFalse($state['can_edit']);
        $this->assertSame('school_override', $state['source']);
    }

    public function test_student_records_open_when_global_window_open(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->clusterWithFullRecords();

        SahodayaProfile::where('tenant_id', $sahodaya->id)->update(['student_edit_lock_enabled' => false]);

        SahodayaRegistrationWindow::create([
            'sahodaya_id'    => $sahodaya->id,
            'academic_year'  => AcademicYear::forSahodaya($sahodaya->id),
            'add_open'       => now()->subDay(),
            'add_close'      => now()->addMonth(),
            'edit_open'      => now()->subDay(),
            'edit_close'     => now()->addMonth(),
        ]);

        $state = app(StudentEditLockService::class)->resolveWindowState($school);

        $this->assertTrue($state['can_add']);
        $this->assertTrue($state['can_edit']);
        $this->assertSame('global_window', $state['source']);
    }

    public function test_student_records_locked_by_default_without_window(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->clusterWithFullRecords();

        SahodayaProfile::where('tenant_id', $sahodaya->id)->update(['student_edit_lock_enabled' => false]);

        $state = app(StudentEditLockService::class)->resolveWindowState($school);

        $this->assertFalse($state['can_add']);
        $this->assertFalse($state['can_edit']);
        $this->assertSame('global_window', $state['source']);
    }

    public function test_full_records_submission_requires_students_and_sahodaya_approval(): void
    {
        ['school' => $school] = $this->clusterWithFullRecords();

        $registration = app(RegistrationStatusService::class)->beginAnnualRegistration($school);
        $submission = $registration->submission;

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(SchoolYearSubmissionReviewService::class)->submitTrack($submission, $school, 'full_records');
    }

    public function test_full_records_submission_flow(): void
    {
        ['school' => $school, 'schoolClass' => $schoolClass] = $this->clusterWithFullRecords();

        Student::create([
            'tenant_id'       => $school->id,
            'school_class_id' => $schoolClass->id,
            'name'            => 'Existing',
            'gender'          => 'male',
            'dob'             => '2010-01-01',
            'status'          => 'active',
            'reg_no'          => 'TS001',
            'admission_number'=> 'TS001',
        ]);

        $registration = app(RegistrationStatusService::class)->beginAnnualRegistration($school);
        $submission = $registration->submission;

        app(SchoolYearSubmissionReviewService::class)->submitTrack($submission, $school, 'full_records');
        $this->assertSame('submitted', $submission->fresh()->full_records_status);

        app(SchoolYearSubmissionReviewService::class)->approveTrack($submission->fresh(), 'full_records');
        $this->assertSame('approved', $submission->fresh()->full_records_status);
    }
}
