<?php

namespace Tests\Unit\Services\Training;

use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Models\TrainingSession;
use App\Services\Training\TrainingAttendanceCheckInService;
use App\Services\Training\TrainingPublicRegistrationService;
use App\Services\Training\TrainingQrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TrainingQrRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: Tenant, 2: TrainingProgram} */
    private function seedProgram(array $overrides = []): array
    {
        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'name'      => 'Kannur Sahodaya',
            'type'      => 'sahodaya',
            'is_active' => true,
        ]);

        $school = Tenant::create([
            'id'                 => (string) Str::uuid(),
            'name'               => 'Nithyananda School',
            'type'               => 'school',
            'parent_id'          => $sahodaya->id,
            'school_prefix'      => 'NBE',
            'membership_status'  => 'approved',
            'is_active'          => true,
        ]);

        $program = TrainingProgram::create(array_merge([
            'tenant_id'               => $sahodaya->id,
            'title'                   => 'Principal Meet 2026',
            'status'                  => 'published',
            'fee_type'                => 'none',
            'qr_registration_enabled' => true,
            'registration_open'       => now()->subDay()->toDateString(),
            'registration_close'      => now()->addDays(10)->toDateString(),
        ], $overrides));

        return [$sahodaya, $school, $program];
    }

    public function test_qr_tokens_generated_on_create(): void
    {
        [, , $program] = $this->seedProgram();

        $this->assertNotEmpty($program->qr_registration_token);
        $this->assertNotEmpty($program->attendance_qr_token);
    }

    public function test_registration_closed_after_window(): void
    {
        [, , $program] = $this->seedProgram([
            'registration_close' => now()->subDay()->toDateString(),
        ]);

        $this->assertFalse(app(TrainingQrService::class)->isRegistrationOpen($program));
    }

    public function test_public_registration_links_existing_teacher(): void
    {
        [, $school, $program] = $this->seedProgram();

        $teacher = Teacher::create([
            'tenant_id' => $school->id,
            'name'      => 'Anita Rao',
            'email'     => 'anita@school.test',
            'mobile'    => '9876543210',
            'status'    => 'active',
        ]);

        $result = app(TrainingPublicRegistrationService::class)->register($program, [
            'name'       => 'Anita Rao',
            'email'      => 'anita@school.test',
            'phone'      => '9876543210',
            'school_id'  => $school->id,
            'designation'=> 'PGT',
            'consent'    => '1',
        ]);

        $this->assertFalse($result['teacher_created']);
        $this->assertSame($teacher->id, $result['teacher']->id);
        $this->assertSame('qr', $result['registration']->registration_source);
        $this->assertSame(1, TrainingRegistration::where('program_id', $program->id)->count());
    }

    public function test_public_registration_creates_unverified_teacher(): void
    {
        [, $school, $program] = $this->seedProgram();

        $type = \App\Models\TeachingType::create([
            'sahodaya_id' => null,
            'code'        => 'PPT',
            'label'       => 'Pre-Primary Teacher',
            'is_active'   => true,
            'sort_order'  => 0,
        ]);

        $result = app(TrainingPublicRegistrationService::class)->register($program, [
            'name'             => 'New Teacher',
            'email'            => 'new@school.test',
            'school_id'        => $school->id,
            'teaching_type_id' => $type->id,
            'department'       => 'Science',
            'experience'       => 3,
        ]);

        $this->assertTrue($result['teacher_created']);
        $this->assertNull($result['teacher']->verified_at);
        $this->assertSame($type->id, $result['teacher']->teaching_type_id);
        $this->assertTrue($result['registration']->teacher_created);
        $this->assertSame('Science', $result['registration']->department);
    }

    public function test_manual_school_creates_pending_request(): void
    {
        [, , $program] = $this->seedProgram();

        $result = app(TrainingPublicRegistrationService::class)->register($program, [
            'name'               => 'Guest Teacher',
            'email'              => 'guest@example.test',
            'manual_school_name' => 'Unknown Public School',
            'manual_school_code' => 'UPS1',
        ]);

        $this->assertNotNull($result['pending_school']);
        $this->assertSame('Unknown Public School', $result['pending_school']->school_name);
        $this->assertSame($result['pending_school']->id, $result['registration']->pending_school_id);
        $this->assertNull($result['registration']->school_id);
        $this->assertSame('Unknown Public School', $result['registration']->displaySchoolName());
    }

    public function test_manual_school_rejects_sahodaya_name(): void
    {
        [$sahodaya, , $program] = $this->seedProgram();

        $this->expectException(ValidationException::class);

        app(TrainingPublicRegistrationService::class)->register($program, [
            'name'               => 'Guest Teacher',
            'email'              => 'guest2@example.test',
            'manual_school_name' => $sahodaya->name,
        ]);
    }

    public function test_manual_school_rejects_central_sahodaya_variant(): void
    {
        [, , $program] = $this->seedProgram();

        $this->expectException(ValidationException::class);

        app(TrainingPublicRegistrationService::class)->register($program, [
            'name'               => 'Guest Teacher',
            'email'              => 'guest3@example.test',
            'manual_school_name' => 'Kannur Central Sahodaya',
        ]);
    }

    public function test_duplicate_registration_rejected(): void
    {
        [, $school, $program] = $this->seedProgram();

        $service = app(TrainingPublicRegistrationService::class);
        $payload = [
            'name'      => 'Dup Teacher',
            'email'     => 'dup@school.test',
            'school_id' => $school->id,
        ];

        $service->register($program, $payload);

        $this->expectException(ValidationException::class);
        $service->register($program, $payload);
    }

    public function test_school_search_returns_code_and_membership(): void
    {
        [, $school, $program] = $this->seedProgram();

        $hits = app(TrainingPublicRegistrationService::class)->searchSchools($program, 'Nithya');

        $this->assertCount(1, $hits);
        $this->assertSame($school->id, $hits[0]['id']);
        $this->assertSame('NBE', $hits[0]['school_code']);
        $this->assertSame('approved', $hits[0]['membership_status']);
        $this->assertArrayHasKey('label', $hits[0]);
    }

    public function test_list_schools_returns_member_schools(): void
    {
        [, $school, $program] = $this->seedProgram();

        $hits = app(TrainingPublicRegistrationService::class)->listSchools($program);

        $this->assertNotEmpty($hits);
        $this->assertSame($school->id, $hits[0]['id']);
    }

    public function test_fuzzy_name_matches_existing_teacher_in_same_school(): void
    {
        [, $school, $program] = $this->seedProgram();

        $teacher = Teacher::create([
            'tenant_id' => $school->id,
            'name'      => 'Anita Rao',
            'email'     => 'anita.unique@school.test',
            'mobile'    => '9000000001',
            'status'    => 'active',
        ]);

        // Different email/mobile; near-identical name within same school.
        $result = app(TrainingPublicRegistrationService::class)->register($program, [
            'name'      => 'Anita Raoo', // levenshtein 1 / high similar_text
            'email'     => 'other@school.test',
            'phone'     => '9000000099',
            'school_id' => $school->id,
            'consent'   => '1',
        ]);

        $this->assertFalse($result['teacher_created']);
        $this->assertSame($teacher->id, $result['teacher']->id);
    }

    public function test_fuzzy_name_does_not_match_across_schools(): void
    {
        [$sahodaya, $school, $program] = $this->seedProgram();

        Teacher::create([
            'tenant_id' => $school->id,
            'name'      => 'Anita Rao',
            'email'     => 'anita.a@school.test',
            'mobile'    => '9000000002',
            'status'    => 'active',
        ]);

        $otherSchool = Tenant::create([
            'id'                => (string) Str::uuid(),
            'name'              => 'Other School',
            'type'              => 'school',
            'parent_id'         => $sahodaya->id,
            'school_prefix'     => 'OTH',
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);

        $result = app(TrainingPublicRegistrationService::class)->register($program, [
            'name'      => 'Anita Raoo',
            'email'     => 'cross@school.test',
            'phone'     => '9000000088',
            'school_id' => $otherSchool->id,
            'consent'   => '1',
        ]);

        $this->assertTrue($result['teacher_created']);
        $this->assertSame($otherSchool->id, $result['teacher']->tenant_id);
    }

    public function test_attendance_check_in_requires_confirmed_for_paid_programs(): void
    {
        [, $school, $program] = $this->seedProgram([
            'fee_type' => 'flat',
            'fee_amount' => 250,
        ]);
        $session = TrainingSession::create(['program_id' => $program->id, 'title' => 'Day 1']);
        $teacher = Teacher::create(['tenant_id' => $school->id, 'name' => 'A', 'email' => 'a@t.test', 'status' => 'active']);
        TrainingRegistration::create([
            'program_id' => $program->id,
            'teacher_id' => $teacher->id,
            'school_id'  => $school->id,
            'status'     => 'registered',
        ]);

        $this->expectException(ValidationException::class);
        app(TrainingAttendanceCheckInService::class)->checkIn($program, $session, 'a@t.test');
    }

    public function test_attendance_check_in_marks_present(): void
    {
        [, $school, $program] = $this->seedProgram();
        $session = TrainingSession::create(['program_id' => $program->id, 'title' => 'Day 1']);
        $teacher = Teacher::create(['tenant_id' => $school->id, 'name' => 'B', 'email' => 'b@t.test', 'status' => 'active']);
        TrainingRegistration::create([
            'program_id' => $program->id,
            'teacher_id' => $teacher->id,
            'school_id'  => $school->id,
            'status'     => 'confirmed',
        ]);

        $attendance = app(TrainingAttendanceCheckInService::class)->checkIn($program, $session, 'b@t.test');

        $this->assertSame('present', $attendance->status);
    }

    public function test_branded_qr_poster_includes_program_and_org(): void
    {
        [$sahodaya, , $program] = $this->seedProgram([
            'title' => 'E2E Teacher Training 2026',
            'venue' => 'District Centre',
            'start_date' => now()->addDays(5)->toDateString(),
        ]);

        $qr = app(TrainingQrService::class);
        $url = $qr->registrationUrl($program);
        $branding = $qr->posterBranding(
            $sahodaya,
            $program,
            $url,
            'Registration QR',
            'Scan to register for this training',
        );

        $this->assertSame('Kannur Sahodaya', $branding['org_name']);
        $this->assertSame('E2E Teacher Training 2026', $branding['program_title']);
        $this->assertSame('District Centre', $branding['venue']);

        $png = $qr->brandedPng($url, $branding);
        $this->assertNotSame('', $png);
        $this->assertSame("\x89PNG", substr($png, 0, 4));

        $svg = $qr->brandedSvg($url, $branding);
        $this->assertStringContainsString('Kannur Sahodaya', $svg);
        $this->assertStringContainsString('E2E Teacher Training 2026', $svg);
        $this->assertStringContainsString('REGISTRATION QR', $svg);
        $this->assertStringContainsString('<svg', $svg);
    }
}
