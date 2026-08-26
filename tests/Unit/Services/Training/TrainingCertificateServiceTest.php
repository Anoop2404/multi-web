<?php

namespace Tests\Unit\Services\Training;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\TrainingAttendance;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Models\TrainingSession;
use App\Services\Training\TrainingCertificateService;
use App\Support\TenantStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TrainingCertificateServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: Tenant} */
    private function seedTenants(): array
    {
        $sahodayaId = (string) Str::uuid();
        $schoolId = (string) Str::uuid();

        $sahodaya = Tenant::create([
            'id'        => $sahodayaId,
            'name'      => 'Malappuram Central Sahodaya',
            'type'      => 'sahodaya',
            'is_active' => true,
        ]);

        $school = Tenant::create([
            'id'        => $schoolId,
            'name'      => 'Test School',
            'type'      => 'school',
            'parent_id' => $sahodayaId,
            'is_active' => true,
        ]);

        return [$sahodaya, $school];
    }

    /** @return array{0: Tenant, 1: TrainingRegistration} a confirmed registration that already satisfies attendance, with relations pre-loaded. */
    private function makeEligibleRegistration(string $email = 'teacher@example.com'): array
    {
        [$sahodaya, $school] = $this->seedTenants();
        $teacher = Teacher::create([
            'tenant_id' => $school->id,
            'name'      => 'Cache Teacher',
            'email'     => $email,
            'status'    => 'active',
        ]);

        $program = TrainingProgram::create([
            'tenant_id' => $sahodaya->id,
            'title'     => 'Synergy 26',
            'venue'     => 'Test Venue',
            'status'    => 'completed',
            'fee_type'  => 'none',
        ]);
        $session = TrainingSession::create(['program_id' => $program->id, 'title' => 'Day 1']);
        $registration = TrainingRegistration::create([
            'program_id' => $program->id,
            'teacher_id' => $teacher->id,
            'school_id'  => $school->id,
            'status'     => 'confirmed',
        ]);
        TrainingAttendance::create([
            'session_id'      => $session->id,
            'registration_id' => $registration->id,
            'status'          => 'present',
        ]);

        return [$sahodaya, $registration->fresh(['program', 'teacher', 'school'])];
    }

    public function test_cached_or_fresh_pdf_populates_caching_columns_on_first_render(): void
    {
        [$sahodaya, $registration] = $this->makeEligibleRegistration();
        $service = app(TrainingCertificateService::class);
        $certificate = $service->issue($registration, notify: false);

        $this->assertNull($certificate->file_path);

        $pdf = $service->cachedOrFreshPdf($registration, $certificate, $sahodaya);

        $this->assertNotEmpty($pdf);
        $this->assertNotNull($certificate->file_path);
        $this->assertNotNull($certificate->storage_disk);
        $this->assertNotNull($certificate->content_hash);
        $this->assertNotNull($certificate->rendered_at);
        $this->assertFalse($certificate->is_stale);
    }

    public function test_cached_or_fresh_pdf_serves_cached_bytes_without_re_rendering(): void
    {
        [$sahodaya, $registration] = $this->makeEligibleRegistration();
        $service = app(TrainingCertificateService::class);
        $certificate = $service->issue($registration, notify: false);

        $service->cachedOrFreshPdf($registration, $certificate, $sahodaya);
        $this->assertNotNull($certificate->file_path, 'First call should have cached a file.');

        TenantStorage::put($certificate->file_path, "%PDF-MARKER\n", $certificate->storage_disk);

        $pdf = $service->cachedOrFreshPdf($registration, $certificate, $sahodaya);

        $this->assertSame("%PDF-MARKER\n", $pdf);
    }

    public function test_stale_certificate_re_renders_instead_of_serving_cached_bytes(): void
    {
        [$sahodaya, $registration] = $this->makeEligibleRegistration();
        $service = app(TrainingCertificateService::class);
        $certificate = $service->issue($registration, notify: false);

        $service->cachedOrFreshPdf($registration, $certificate, $sahodaya);
        TenantStorage::put($certificate->file_path, "%PDF-MARKER\n", $certificate->storage_disk);
        $certificate->update(['is_stale' => true]);

        $pdf = $service->cachedOrFreshPdf($registration, $certificate, $sahodaya);

        $this->assertNotSame("%PDF-MARKER\n", $pdf, 'A stale certificate must re-render, not serve the stale cached file.');
    }

    /**
     * Regression guard: sendCertificateEmailToRegistration() -> issue() ->
     * notifyCertificateAvailable() -> emailCertificatePdf() used to recurse back into
     * sendCertificateEmailToRegistration() and send a first email before the outer call
     * sent its own second one — every first-ever send (bulk or single) double-sent.
     */
    public function test_send_certificate_email_does_not_double_send_for_a_newly_issued_certificate(): void
    {
        [$sahodaya, $registration] = $this->makeEligibleRegistration('newteacher@example.com');
        $service = app(TrainingCertificateService::class);

        $this->assertNull(
            Certificate::where('entity_type', TrainingRegistration::class)->where('entity_id', $registration->id)->first(),
            'Precondition: no certificate should exist yet — this is exactly when the recursion used to fire.'
        );

        Mail::shouldReceive('send')->once();

        $sent = $service->sendCertificateEmailToRegistration($registration, $sahodaya);

        $this->assertTrue($sent);
    }

    public function test_requires_at_least_one_present_day(): void
    {
        [$sahodaya, $school] = $this->seedTenants();
        $teacher = Teacher::create(['tenant_id' => $school->id, 'name' => 'Teacher A', 'status' => 'active']);

        $program = TrainingProgram::create([
            'tenant_id' => $sahodaya->id,
            'title'     => 'Synergy 26',
            'status'    => 'ongoing',
            'fee_type'  => 'none',
        ]);
        $session = TrainingSession::create(['program_id' => $program->id, 'title' => 'Day 1']);
        $registration = TrainingRegistration::create([
            'program_id' => $program->id,
            'teacher_id' => $teacher->id,
            'school_id'  => $school->id,
            'status'     => 'confirmed',
        ]);

        $service = app(TrainingCertificateService::class);

        try {
            $service->assertEligible($registration);
            $this->fail('Expected ValidationException');
        } catch (ValidationException) {
            // expected
        }

        TrainingAttendance::create([
            'session_id'      => $session->id,
            'registration_id' => $registration->id,
            'status'          => 'present',
        ]);

        $service->assertEligible($registration->fresh());
    }

    public function test_respects_min_attendance_percent(): void
    {
        [$sahodaya, $school] = $this->seedTenants();
        $teacher = Teacher::create(['tenant_id' => $school->id, 'name' => 'Teacher B', 'status' => 'active']);

        $program = TrainingProgram::create([
            'tenant_id'              => $sahodaya->id,
            'title'                  => 'Synergy 26',
            'status'                 => 'ongoing',
            'fee_type'               => 'none',
            'min_attendance_percent' => 100,
        ]);
        $day1 = TrainingSession::create(['program_id' => $program->id, 'title' => 'Day 1']);
        $day2 = TrainingSession::create(['program_id' => $program->id, 'title' => 'Day 2']);
        $registration = TrainingRegistration::create([
            'program_id' => $program->id,
            'teacher_id' => $teacher->id,
            'school_id'  => $school->id,
            'status'     => 'confirmed',
        ]);

        TrainingAttendance::create([
            'session_id'      => $day1->id,
            'registration_id' => $registration->id,
            'status'          => 'present',
        ]);

        $service = app(TrainingCertificateService::class);

        try {
            $service->assertEligible($registration->fresh()->load('program.sessions'));
            $this->fail('Expected ValidationException for 100% attendance requirement');
        } catch (ValidationException) {
            // expected — 1 of 2 days
        }

        TrainingAttendance::create([
            'session_id'      => $day2->id,
            'registration_id' => $registration->id,
            'status'          => 'present',
        ]);

        $service->assertEligible($registration->fresh()->load('program.sessions'));
    }

    public function test_conducted_on_reflects_only_present_days(): void
    {
        [$sahodaya, $school] = $this->seedTenants();
        $teacher = Teacher::create([
            'tenant_id'   => $school->id,
            'name'        => 'Jane Teacher',
            'gender'      => 'female',
            'designation' => 'PGT',
            'status'      => 'active',
        ]);

        $program = TrainingProgram::create([
            'tenant_id' => $sahodaya->id,
            'title'     => "SYNERGY'26 – Teacher Training Programme",
            'venue'     => 'St. Alphonsa Public School, Oorakam',
            'status'    => 'completed',
            'fee_type'  => 'none',
        ]);
        $day1 = TrainingSession::create([
            'program_id'   => $program->id,
            'title'        => 'Day 1',
            'scheduled_at' => '2026-07-11 09:00:00',
        ]);
        $day2 = TrainingSession::create([
            'program_id'   => $program->id,
            'title'        => 'Day 2',
            'scheduled_at' => '2026-07-12 09:00:00',
        ]);
        $registration = TrainingRegistration::create([
            'program_id' => $program->id,
            'teacher_id' => $teacher->id,
            'school_id'  => $school->id,
            'status'     => 'confirmed',
        ]);

        TrainingAttendance::create([
            'session_id'      => $day1->id,
            'registration_id' => $registration->id,
            'status'          => 'present',
        ]);
        TrainingAttendance::create([
            'session_id'      => $day2->id,
            'registration_id' => $registration->id,
            'status'          => 'absent',
        ]);

        CertificateTemplate::create([
            'tenant_id'        => $sahodaya->id,
            'event_type'       => 'training',
            'certificate_type' => 'participation',
            'title'            => 'Certificate of Participation',
            'body'             => CertificateTemplate::defaultTrainingBody(),
            'is_active'        => true,
        ]);

        $service = app(TrainingCertificateService::class);
        $registration->load(['program', 'teacher', 'school']);
        $fields = $service->resolveFieldValues($registration, $sahodaya);

        $this->assertSame('11 July 2026', $fields['conducted_on']);
        $this->assertSame('1', $fields['days_attended']);
        $this->assertSame('2', $fields['total_days']);
        $this->assertArrayHasKey('training_hours', $fields);
        $this->assertSame('St. Alphonsa Public School, Oorakam', $fields['venue']);
        $this->assertSame('Jane Teacher', $fields['recipient_name']);
        $this->assertSame('Mrs.', $fields['salutation']);
        $this->assertSame('Mrs. Jane Teacher', $fields['recipient_with_title']);
        $this->assertSame('PGT', $fields['designation']);
    }

    public function test_salutation_follows_teacher_gender(): void
    {
        $this->assertSame('Mr.', TrainingCertificateService::salutationForGender('male'));
        $this->assertSame('Mrs.', TrainingCertificateService::salutationForGender('female'));
        $this->assertSame('Mr./Ms.', TrainingCertificateService::salutationForGender(null));
        $this->assertSame(
            ['salutation' => 'Mr.', 'recipient_name' => 'John Doe', 'recipient_with_title' => 'Mr. John Doe'],
            TrainingCertificateService::recipientNameFields('Mr. John Doe', 'male'),
        );
    }

    public function test_issue_uses_program_certificate_type(): void
    {
        [$sahodaya, $school] = $this->seedTenants();
        $teacher = Teacher::create(['tenant_id' => $school->id, 'name' => 'Teacher C', 'status' => 'active']);

        $program = TrainingProgram::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Completion Program',
            'status' => 'ongoing',
            'fee_type' => 'none',
            'certificate_type' => 'completion',
        ]);
        $session = TrainingSession::create([
            'program_id' => $program->id,
            'title' => 'Day 1',
            'duration_minutes' => 120,
        ]);
        $registration = TrainingRegistration::create([
            'program_id' => $program->id,
            'teacher_id' => $teacher->id,
            'school_id' => $school->id,
            'status' => 'confirmed',
        ]);
        TrainingAttendance::create([
            'session_id' => $session->id,
            'registration_id' => $registration->id,
            'status' => 'present',
        ]);

        CertificateTemplate::create([
            'tenant_id' => $sahodaya->id,
            'event_type' => 'training',
            'certificate_type' => 'completion',
            'title' => 'Certificate of Completion',
            'is_active' => true,
        ]);

        $service = app(TrainingCertificateService::class);
        $certificate = $service->issue($registration->fresh(['program', 'teacher', 'school']));

        $this->assertSame('completion', $certificate->cert_type);
        $fields = $service->resolveFieldValues($registration->fresh(['program.sessions', 'teacher', 'school']), $sahodaya);
        $this->assertSame('2', $fields['training_hours']);
    }

    public function test_resolve_template_prefers_program_certificate_template_id(): void
    {
        [$sahodaya, $school] = $this->seedTenants();
        $teacher = Teacher::create(['tenant_id' => $school->id, 'name' => 'Teacher D', 'status' => 'active']);

        $typeMatched = CertificateTemplate::create([
            'tenant_id' => $sahodaya->id,
            'event_type' => 'training',
            'certificate_type' => 'participation',
            'title' => 'Type Matched',
            'is_active' => true,
        ]);

        $chosen = CertificateTemplate::create([
            'tenant_id' => $sahodaya->id,
            'event_type' => 'training',
            'certificate_type' => 'appreciation',
            'title' => 'Explicitly Chosen',
            'background_path' => 'certificates/demo-bg.png',
            'layout_json' => CertificateTemplate::defaultBackgroundLayout(),
            'is_active' => true,
        ]);

        $program = TrainingProgram::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Chosen Template Program',
            'status' => 'ongoing',
            'fee_type' => 'none',
            'certificate_type' => 'participation',
            'certificate_template_id' => $chosen->id,
        ]);

        $registration = TrainingRegistration::create([
            'program_id' => $program->id,
            'teacher_id' => $teacher->id,
            'school_id' => $school->id,
            'status' => 'confirmed',
        ]);

        $service = app(TrainingCertificateService::class);
        $resolved = $service->resolveTemplate($registration->fresh(['program']));

        $this->assertNotNull($resolved);
        $this->assertSame($chosen->id, $resolved->id);
        $this->assertNotSame($typeMatched->id, $resolved->id);

        $ctx = $service->sampleRenderContext($program->fresh(), $sahodaya);
        $this->assertSame($chosen->id, $ctx['template']?->id);
        $this->assertArrayHasKey('backgroundUrl', $ctx);
        $this->assertArrayHasKey('overlayLayout', $ctx);
        $this->assertArrayHasKey('recipient_name', $ctx['overlayLayout']);
    }
}
