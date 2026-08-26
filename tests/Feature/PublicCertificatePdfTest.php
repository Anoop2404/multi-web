<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\TrainingRegistration;
use App\Models\User;
use App\Support\TenantStorage;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers PublicCertificateController::pdf() (route certificates.pdf) — the new
 * "View PDF"/"Download PDF" route, which is the first thing in this codebase that
 * serves a certificate's cached PDF bytes directly (print()/verify() only ever return
 * HTML). Reuses the exact marker-overwrite technique
 * FestCertificateBatchGenerationTest::test_download_zip_serves_the_cached_file_instead_of_re_rendering()
 * already established, to prove a cache hit is actually served from storage and not
 * silently re-rendered every time.
 */
class PublicCertificatePdfTest extends TestCase
{
    use RefreshDatabase;

    private function makeSahodayaAdminAndSchool(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'PDF Route Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'PR', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'PDF Route School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        return compact('sahodaya', 'admin', 'school');
    }

    /** Renders and caches one real certificate via the actual admin batch pipeline (not a hand-set file_path), matching FestCertificateBatchGenerationTest's own precedent. */
    private function makeRenderedCertificate(Tenant $sahodaya, User $admin, Tenant $school): Certificate
    {
        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'PDF Route Event', 'event_type' => 'kalolsavam']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'item_code' => 'SS1']);
        $registration = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved',
        ]);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $event->id, 'student_id' => 601,
            'participant_type' => 'student', 'participant_role' => 'performer',
        ]);
        $certificate = Certificate::create([
            'entity_type' => FestParticipant::class, 'entity_id' => $participant->id,
            'cert_type' => 'participation', 'verification_uuid' => (string) Str::uuid(), 'generated_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('sahodaya.events.certificates.batches.store', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]))->assertRedirect();

        return $certificate->refresh();
    }

    public function test_pdf_route_serves_cached_bytes_without_re_rendering(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'school' => $school] = $this->makeSahodayaAdminAndSchool();
        $certificate = $this->makeRenderedCertificate($sahodaya, $admin, $school);

        $this->assertNotNull($certificate->file_path, 'Batch render should have populated file_path.');
        TenantStorage::put($certificate->file_path, "%PDF-MARKER\n", $certificate->storage_disk);

        $response = $this->get(route('certificates.pdf', $certificate->verification_uuid));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame("%PDF-MARKER\n", $response->getContent());
    }

    public function test_download_param_forces_attachment_disposition(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'school' => $school] = $this->makeSahodayaAdminAndSchool();
        $certificate = $this->makeRenderedCertificate($sahodaya, $admin, $school);

        $inline = $this->get(route('certificates.pdf', $certificate->verification_uuid));
        $inline->assertOk();
        $this->assertStringStartsWith('inline', $inline->headers->get('Content-Disposition'));

        $download = $this->get(route('certificates.pdf', $certificate->verification_uuid).'?download=1');
        $download->assertOk();
        $this->assertStringStartsWith('attachment', $download->headers->get('Content-Disposition'));
    }

    public function test_plain_param_selects_the_plain_variant(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'school' => $school] = $this->makeSahodayaAdminAndSchool();
        $certificate = $this->makeRenderedCertificate($sahodaya, $admin, $school);

        $this->assertNotNull($certificate->plain_file_path);
        TenantStorage::put($certificate->file_path, "%PDF-WITH-BG\n", $certificate->storage_disk);
        TenantStorage::put($certificate->plain_file_path, "%PDF-PLAIN\n", $certificate->storage_disk);

        $withBg = $this->get(route('certificates.pdf', $certificate->verification_uuid));
        $this->assertSame("%PDF-WITH-BG\n", $withBg->getContent());

        $plain = $this->get(route('certificates.pdf', $certificate->verification_uuid).'?plain=1');
        $this->assertSame("%PDF-PLAIN\n", $plain->getContent());
    }

    public function test_stale_certificate_re_renders_instead_of_serving_cached_bytes(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'school' => $school] = $this->makeSahodayaAdminAndSchool();
        $certificate = $this->makeRenderedCertificate($sahodaya, $admin, $school);

        TenantStorage::put($certificate->file_path, "%PDF-MARKER\n", $certificate->storage_disk);
        $certificate->update(['is_stale' => true]);

        $response = $this->get(route('certificates.pdf', $certificate->verification_uuid));

        $response->assertOk();
        $this->assertNotSame("%PDF-MARKER\n", $response->getContent(), 'A stale certificate must re-render, not serve the stale cached file.');
    }

    public function test_404s_for_a_non_fest_certificate(): void
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Non-Fest Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);

        $certificate = Certificate::create([
            'entity_type' => TrainingRegistration::class, 'entity_id' => 999999,
            'cert_type' => 'participation', 'verification_uuid' => (string) Str::uuid(), 'generated_at' => now(),
        ]);

        $response = $this->get(route('certificates.pdf', $certificate->verification_uuid));

        $response->assertNotFound();
    }
}
