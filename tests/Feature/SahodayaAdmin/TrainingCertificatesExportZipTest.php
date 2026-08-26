<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\Certificate;
use App\Models\SahodayaProfile;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\TrainingAttendance;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\Training\TrainingCertificateService;
use App\Support\TenantStorage;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * TrainingProgramController::exportCertificatesZip() renders via raw DomPDF, a different
 * renderer than PdfGenerator (which the cached-PDF pipeline uses) — it must read the cache
 * when available but never write to it, to avoid leaking DomPDF output into what
 * downloadPdfResponse()/emails serve. Covers both sides of that read-only contract.
 */
class TrainingCertificatesExportZipTest extends TestCase
{
    use RefreshDatabase;

    private function makeSahodayaAdminAndRegistration(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Zip Export Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'ZE', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Zip Export School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $program = TrainingProgram::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Zip Export Program',
            'venue' => 'Test Venue', 'status' => 'completed', 'fee_type' => 'none',
        ]);
        $session = TrainingSession::create(['program_id' => $program->id, 'title' => 'Day 1']);
        $teacher = Teacher::create(['tenant_id' => $school->id, 'name' => 'Zip Teacher', 'status' => 'active']);
        $registration = TrainingRegistration::create([
            'program_id' => $program->id, 'teacher_id' => $teacher->id, 'school_id' => $school->id, 'status' => 'confirmed',
        ]);
        TrainingAttendance::create([
            'session_id' => $session->id, 'registration_id' => $registration->id, 'status' => 'present',
        ]);

        return compact('sahodaya', 'admin', 'program', 'registration');
    }

    private function exportZip(User $admin, Tenant $sahodaya, TrainingProgram $program): \ZipArchive
    {
        $response = $this->actingAs($admin)->get(route('sahodaya.training.certificates.export', [
            'tenantId' => $sahodaya->id,
            'program'  => $program->id,
        ]));
        $response->assertOk();

        $zipPath = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);
        $this->assertSame(1, $zip->numFiles);

        return $zip;
    }

    public function test_export_zip_serves_cached_bytes_instead_of_re_rendering(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'program' => $program, 'registration' => $registration] = $this->makeSahodayaAdminAndRegistration();

        $service = app(TrainingCertificateService::class);
        $certificate = $service->issue($registration->fresh(['program', 'teacher', 'school']), notify: false);
        $service->cachedOrFreshPdf($registration->fresh(['program', 'teacher', 'school']), $certificate, $sahodaya);
        $this->assertNotNull($certificate->file_path, 'Precondition: the certificate should already be cached.');

        TenantStorage::put($certificate->file_path, "%PDF-MARKER\n", $certificate->storage_disk);

        $zip = $this->exportZip($admin, $sahodaya, $program);
        $this->assertSame("%PDF-MARKER\n", $zip->getFromIndex(0));
        $zip->close();
    }

    public function test_export_zip_falls_back_to_dompdf_when_nothing_is_cached_yet(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'program' => $program, 'registration' => $registration] = $this->makeSahodayaAdminAndRegistration();

        $this->assertNull(
            Certificate::where('entity_type', TrainingRegistration::class)->where('entity_id', $registration->id)->first(),
            'Precondition: no certificate, and therefore no cache, should exist yet.'
        );

        $zip = $this->exportZip($admin, $sahodaya, $program);
        $bytes = $zip->getFromIndex(0);
        $zip->close();

        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertNotSame("%PDF-MARKER\n", $bytes);
    }
}
