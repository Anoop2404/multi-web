<?php

namespace Tests\Feature\SchoolAdmin;

use App\Models\Certificate;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantStorage;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * FestEventPortalController::downloadCertificatesZip() (school-side "Download all
 * certificates") used to zip raw .html with no QR code and site-relative image URLs
 * that only resolve on-site — the same class of bug the Sahodaya-admin bulk ZIP was
 * fixed for earlier (see FestCertificateExportTest), left unfixed on the school side
 * with zero test coverage at all. Now produces real PDFs, preferring an already-cached
 * file from RenderCertificateChunkJob and falling back to a live render only when one
 * isn't cached yet.
 */
class FestEventPortalCertificatesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, school: Tenant, schoolAdmin: User} */
    private function makeSahodayaAndSchool(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Portal Cert Sahodaya',
            'domain' => 'portal-cert-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'PC', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Portal Cert School', 'domain' => 'portal-cert-school-'.Str::random(8).'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);

        $schoolAdmin = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $schoolAdmin->assignRole('school_admin');

        return compact('sahodaya', 'school', 'schoolAdmin');
    }

    private function makeCertificate(FestEvent $event, FestEventItem $item, string $schoolId): Certificate
    {
        $registration = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $schoolId, 'status' => 'approved',
        ]);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $event->id, 'student_id' => 1,
            'participant_type' => 'student', 'participant_role' => 'performer',
        ]);

        return Certificate::create([
            'entity_type'        => FestParticipant::class,
            'entity_id'          => $participant->id,
            'cert_type'          => 'participation',
            'verification_uuid' => (string) Str::uuid(),
            'generated_at'      => now(),
        ]);
    }

    public function test_download_all_produces_real_pdfs_with_no_html_entries(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'schoolAdmin' => $schoolAdmin] = $this->makeSahodayaAndSchool();

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Portal Zip Event', 'event_type' => 'kalolsavam']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'item_code' => 'SS1']);

        $this->makeCertificate($event, $item, $school->id);
        $this->makeCertificate($event, $item, $school->id);

        $response = $this->actingAs($schoolAdmin)->get(route('school.fest.certificates.download-all', [
            'tenantId' => $school->id,
            'event'    => $event->id,
        ]));

        $response->assertOk();

        $zipPath = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);
        $this->assertSame(2, $zip->numFiles);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $this->assertStringEndsWith('.pdf', $name);
            $bytes = $zip->getFromIndex($i);
            $this->assertStringStartsWith('%PDF', $bytes);
        }

        $zip->close();
    }

    public function test_download_all_serves_an_already_cached_file_instead_of_re_rendering(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'schoolAdmin' => $schoolAdmin] = $this->makeSahodayaAndSchool();

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Portal Cache Event', 'event_type' => 'kalolsavam']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'item_code' => 'SS1']);
        $certificate = $this->makeCertificate($event, $item, $school->id);

        $cachedPath = 'certificates/'.$sahodaya->id.'/'.$event->id.'/participation/'.$certificate->id.'-marker.pdf';
        TenantStorage::put($cachedPath, "%PDF-MARKER\n", TenantStorage::uploadDisk());
        $certificate->update([
            'file_path'    => $cachedPath,
            'storage_disk' => TenantStorage::uploadDisk(),
            'is_stale'     => false,
        ]);

        $response = $this->actingAs($schoolAdmin)->get(route('school.fest.certificates.download-all', [
            'tenantId' => $school->id,
            'event'    => $event->id,
        ]));
        $response->assertOk();

        $zipPath = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);
        $this->assertSame(1, $zip->numFiles);
        $this->assertSame("%PDF-MARKER\n", $zip->getFromIndex(0));
        $zip->close();
    }
}
