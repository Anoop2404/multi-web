<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\Certificate;
use App\Models\CertificateBatch;
use App\Models\SahodayaProfile;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\TrainingAttendance;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Models\TrainingSession;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end coverage for the async bulk-send pipeline
 * (TrainingProgramController::bulkSendCertificatesEmail() -> dispatchSendEmailBatch() ->
 * Bus::batch() -> SendTrainingCertificateEmailChunkJob) that replaced a plain synchronous
 * foreach over every confirmed registration — the thing that timed out at ~420 teachers in
 * production. QUEUE_CONNECTION=sync in phpunit.xml means the batch runs inline within the
 * test, exercising the real chunk/send/cache logic without a queue worker.
 *
 * The Mail::shouldReceive(...)->times(N) assertions are the regression guard for a
 * double-send bug found while building this: sendCertificateEmailToRegistration() ->
 * issue() -> notifyCertificateAvailable() -> emailCertificatePdf() used to recurse back
 * into sendCertificateEmailToRegistration() and send a first email before the outer call
 * sent its own second one, for any registration with no pre-existing Certificate row.
 */
class TrainingCertificateBulkSendBatchTest extends TestCase
{
    use RefreshDatabase;

    private function makeSahodaya(): Tenant
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Bulk Send Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'BS', 'student_data_mode' => 'counts_only']);

        return $sahodaya;
    }

    private function makeSchool(string $sahodayaId): Tenant
    {
        return Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodayaId,
            'name' => 'Bulk Send School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);
    }

    /** @return array{0: TrainingProgram, 1: TrainingSession, 2: Tenant} */
    private function makeProgram(Tenant $sahodaya, Tenant $school): array
    {
        $program = TrainingProgram::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Bulk Send Program',
            'venue' => 'Test Venue', 'status' => 'completed', 'fee_type' => 'none',
        ]);
        $session = TrainingSession::create(['program_id' => $program->id, 'title' => 'Day 1']);

        return [$program, $session, $school];
    }

    private function makeEligibleRegistration(TrainingProgram $program, TrainingSession $session, Tenant $school, string $email): TrainingRegistration
    {
        $teacher = Teacher::create([
            'tenant_id' => $school->id, 'name' => 'Teacher '.$email, 'email' => $email, 'status' => 'active',
        ]);
        $registration = TrainingRegistration::create([
            'program_id' => $program->id, 'teacher_id' => $teacher->id, 'school_id' => $school->id, 'status' => 'confirmed',
        ]);
        TrainingAttendance::create([
            'session_id' => $session->id, 'registration_id' => $registration->id, 'status' => 'present',
        ]);

        return $registration;
    }

    public function test_bulk_send_dispatches_a_batch_and_emails_each_eligible_registration_exactly_once(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = $this->makeSahodaya();
        $school = $this->makeSchool($sahodaya->id);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        [$program, $session, $school] = $this->makeProgram($sahodaya, $school);
        $registrations = [
            $this->makeEligibleRegistration($program, $session, $school, 'teacher1@example.com'),
            $this->makeEligibleRegistration($program, $session, $school, 'teacher2@example.com'),
            $this->makeEligibleRegistration($program, $session, $school, 'teacher3@example.com'),
        ];

        Mail::shouldReceive('send')->times(3);

        $response = $this->actingAs($admin)->post(route('sahodaya.training.certificates.bulk-send-email', [
            'tenantId' => $sahodaya->id,
            'program'  => $program->id,
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('certificate_batch_id');
        $batchId = session('certificate_batch_id');

        $batch = CertificateBatch::findOrFail($batchId);
        $this->assertSame(CertificateBatch::STATUS_COMPLETED, $batch->status);
        $this->assertSame('send_email', $batch->batch_type);
        $this->assertSame($program->id, $batch->training_program_id);
        $this->assertSame(3, $batch->total_count);
        $this->assertSame(3, $batch->processed_count);
        $this->assertSame(3, $batch->succeeded_count);
        $this->assertSame(0, $batch->failed_count);

        foreach ($registrations as $registration) {
            $certificate = Certificate::where('entity_type', TrainingRegistration::class)
                ->where('entity_id', $registration->id)
                ->first();

            $this->assertNotNull($certificate, 'A certificate should have been issued for '.$registration->id);
            $this->assertNotNull($certificate->email_sent_at);
            $this->assertNotNull($certificate->file_path);
            $this->assertNotNull($certificate->content_hash);
            $this->assertNotNull($certificate->rendered_at);
            $this->assertFalse($certificate->is_stale);
        }

        $progress = $this->actingAs($admin)->getJson(route('sahodaya.training.certificates.batches.progress', [
            'tenantId' => $sahodaya->id,
            'program'  => $program->id,
            'batch'    => $batch->id,
        ]));
        $progress->assertOk();
        $progress->assertJson([
            'status'          => CertificateBatch::STATUS_COMPLETED,
            'batch_type'      => 'send_email',
            'total_count'     => 3,
            'succeeded_count' => 3,
            'failed_count'    => 0,
        ]);
    }

    public function test_bulk_send_respects_registration_ids_selection(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = $this->makeSahodaya();
        $school = $this->makeSchool($sahodaya->id);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        [$program, $session, $school] = $this->makeProgram($sahodaya, $school);
        $selected = $this->makeEligibleRegistration($program, $session, $school, 'selected@example.com');
        $notSelected = $this->makeEligibleRegistration($program, $session, $school, 'not-selected@example.com');

        Mail::shouldReceive('send')->once();

        $response = $this->actingAs($admin)->post(route('sahodaya.training.certificates.bulk-send-email', [
            'tenantId' => $sahodaya->id,
            'program'  => $program->id,
        ]), [
            'registration_ids' => [$selected->id],
        ]);

        $response->assertRedirect();
        $batch = CertificateBatch::findOrFail(session('certificate_batch_id'));
        $this->assertSame(1, $batch->total_count);
        $this->assertSame([$selected->id], $batch->registration_ids_json);

        $selectedCert = Certificate::where('entity_type', TrainingRegistration::class)->where('entity_id', $selected->id)->first();
        $this->assertNotNull($selectedCert);
        $this->assertNotNull($selectedCert->email_sent_at);

        $notSelectedCert = Certificate::where('entity_type', TrainingRegistration::class)->where('entity_id', $notSelected->id)->first();
        $this->assertNull($notSelectedCert, 'A registration excluded from registration_ids should not have been touched.');
    }
}
