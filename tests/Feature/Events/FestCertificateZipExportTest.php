<?php

namespace Tests\Feature\Events;

use App\Models\Certificate;
use App\Models\CertificateBatch;
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
 * Covers the queued ZIP export pipeline (FestCertificateController::queueZipExport() ->
 * BuildCertificateZipJob -> downloadZipResult()) that replaced downloadZip()'s
 * synchronous, whole-request build for the "bulk" download triggers — an event with
 * hundreds to thousands of certificates could exceed the web server/proxy's own request
 * timeout well before PHP's own set_time_limit(600) in downloadZip() ever kicked in.
 * QUEUE_CONNECTION=sync in phpunit.xml means the dispatched job runs inline within the
 * test, same as FestCertificateBatchGenerationTest's coverage of the render pipeline.
 */
class FestCertificateZipExportTest extends TestCase
{
    use RefreshDatabase;

    private function makeSahodaya(): Tenant
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Zip Export Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'ZE', 'student_data_mode' => 'counts_only']);

        return $sahodaya;
    }

    private function makeSchool(string $sahodayaId): Tenant
    {
        return Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodayaId,
            'name' => 'Zip Export School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);
    }

    /** @return list<Certificate> */
    private function makeCertificates(FestEvent $event, FestEventItem $item, string $schoolId, int $count, string $certType = 'participation'): array
    {
        $certificates = [];

        for ($i = 0; $i < $count; $i++) {
            $registration = FestRegistration::create([
                'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $schoolId, 'status' => 'approved',
            ]);
            $participant = FestParticipant::create([
                'registration_id' => $registration->id, 'event_id' => $event->id, 'student_id' => 900 + $i,
                'participant_type' => 'student', 'participant_role' => 'performer',
            ]);

            $certificates[] = Certificate::create([
                'entity_type' => FestParticipant::class,
                'entity_id' => $participant->id,
                'cert_type' => $certType,
                'verification_uuid' => (string) Str::uuid(),
                'generated_at' => now(),
            ]);
        }

        return $certificates;
    }

    public function test_queue_zip_export_builds_a_downloadable_zip_of_every_certificate(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $sahodaya = $this->makeSahodaya();
        $school = $this->makeSchool($sahodaya->id);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Zip Export Event', 'event_type' => 'kalolsavam']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'item_code' => 'SS1']);
        $certificates = $this->makeCertificates($event, $item, $school->id, 3);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.certificates.download-zip.queue', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('certificate_batch_id');
        $batch = CertificateBatch::findOrFail(session('certificate_batch_id'));

        $this->assertSame('zip_export', $batch->batch_type);
        $this->assertSame(CertificateBatch::STATUS_COMPLETED, $batch->status);
        $this->assertSame(3, $batch->total_count);
        $this->assertSame(3, $batch->processed_count);
        $this->assertSame(3, $batch->succeeded_count);
        $this->assertSame(0, $batch->failed_count);
        $this->assertNotNull($batch->file_path);
        $this->assertNotNull($batch->storage_disk);
        $this->assertNotNull($batch->result_filename);

        $download = $this->actingAs($admin)->get(route('sahodaya.events.certificates.batches.download', [
            'tenantId' => $sahodaya->id, 'event' => $event->id, 'batch' => $batch->id,
        ]));
        $download->assertOk();

        // downloadZipResult() serves via TenantStorage::downloadPrivate(), which streams
        // rather than returning a file response with a filesystem path — assert on the
        // stored bytes directly instead of the response object.
        $names = $this->zipEntryNames($batch);
        $this->assertCount(3, $names);
        foreach ($certificates as $certificate) {
            $this->assertTrue(
                collect($names)->contains(fn ($n) => str_contains($n, $certificate->verification_uuid)),
                "Expected the ZIP to contain certificate {$certificate->verification_uuid}."
            );
        }

        // The progress endpoint (polled by Certificates.vue) reports the same terminal
        // state a client would see if it polled after the redirect landed.
        $progress = $this->actingAs($admin)->getJson(route('sahodaya.events.certificates.batches.progress', [
            'tenantId' => $sahodaya->id, 'event' => $event->id, 'batch' => $batch->id,
        ]));
        $progress->assertOk();
        $progress->assertJson([
            'status' => CertificateBatch::STATUS_COMPLETED,
            'batch_type' => 'zip_export',
            'total_count' => 3,
            'succeeded_count' => 3,
        ]);
    }

    public function test_queue_zip_export_published_only_scopes_to_visible_winners(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $sahodaya = $this->makeSahodaya();
        $school = $this->makeSchool($sahodaya->id);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Published Only Zip Event', 'event_type' => 'kalolsavam',
            'results_published' => false,
        ]);
        $publishedItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Published Item', 'item_code' => 'PZ1',
            'results_published_at' => now(),
        ]);
        $unpublishedItem = FestEventItem::create(['event_id' => $event->id, 'title' => 'Unpublished Item', 'item_code' => 'UZ1']);

        [$publishedWinner] = $this->makeCertificates($event, $publishedItem, $school->id, 1, 'winner');
        $this->makeCertificates($event, $unpublishedItem, $school->id, 1, 'winner');

        $response = $this->actingAs($admin)->post(route('sahodaya.events.certificates.download-zip.queue', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), ['published_only' => '1']);

        $response->assertRedirect();
        $batch = CertificateBatch::findOrFail(session('certificate_batch_id'));
        $this->assertSame(1, $batch->total_count);
        $this->assertSame(CertificateBatch::STATUS_COMPLETED, $batch->status);

        $names = $this->zipEntryNames($batch);
        $this->assertCount(1, $names);
        $this->assertStringContainsString($publishedWinner->verification_uuid, $names[0]);
    }

    /** @return list<string> */
    private function zipEntryNames(CertificateBatch $batch): array
    {
        $bytes = TenantStorage::get($batch->file_path, $batch->storage_disk);
        $this->assertNotNull($bytes, 'Expected the export ZIP to exist in storage.');

        $tmpPath = tempnam(sys_get_temp_dir(), 'zip-export-test-');
        file_put_contents($tmpPath, $bytes);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tmpPath) === true);

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();
        @unlink($tmpPath);

        return $names;
    }

    public function test_queue_zip_export_with_no_matching_certificates_returns_404_and_no_batch_row(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $sahodaya = $this->makeSahodaya();
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Empty Zip Event', 'event_type' => 'kalolsavam']);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.certificates.download-zip.queue', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]));

        $response->assertNotFound();
        $this->assertSame(0, CertificateBatch::count());
    }

    public function test_download_zip_result_rejects_a_batch_belonging_to_another_tenant(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $sahodayaA = $this->makeSahodaya();
        $sahodayaB = $this->makeSahodaya();
        $schoolA = $this->makeSchool($sahodayaA->id);
        $adminB = User::factory()->create(['tenant_id' => $sahodayaB->id, 'email_verified_at' => now()]);
        $adminB->assignRole('sahodaya_admin');

        $eventA = FestEvent::create(['tenant_id' => $sahodayaA->id, 'title' => 'Tenant A Event', 'event_type' => 'kalolsavam']);
        $eventB = FestEvent::create(['tenant_id' => $sahodayaB->id, 'title' => 'Tenant B Event', 'event_type' => 'kalolsavam']);
        $itemA = FestEventItem::create(['event_id' => $eventA->id, 'title' => 'Solo Song', 'item_code' => 'SA1']);
        $this->makeCertificates($eventA, $itemA, $schoolA->id, 1);

        $adminA = User::factory()->create(['tenant_id' => $sahodayaA->id, 'email_verified_at' => now()]);
        $adminA->assignRole('sahodaya_admin');
        $this->actingAs($adminA)->post(route('sahodaya.events.certificates.download-zip.queue', [
            'tenantId' => $sahodayaA->id, 'event' => $eventA->id,
        ]))->assertRedirect();
        $batchA = CertificateBatch::firstOrFail();

        // Tenant B's own admin, on tenant B's own event, must not be able to reach tenant
        // A's export by guessing/reusing its batch id.
        $response = $this->actingAs($adminB)->get(route('sahodaya.events.certificates.batches.download', [
            'tenantId' => $sahodayaB->id, 'event' => $eventB->id, 'batch' => $batchA->id,
        ]));
        $response->assertForbidden();
    }

    public function test_download_zip_result_is_not_ready_while_still_processing(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $sahodaya = $this->makeSahodaya();
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Still Processing Event', 'event_type' => 'kalolsavam']);
        $batch = CertificateBatch::create([
            'tenant_id' => $sahodaya->id, 'event_id' => $event->id, 'batch_type' => 'zip_export',
            'total_count' => 5, 'status' => CertificateBatch::STATUS_PROCESSING,
        ]);

        $response = $this->actingAs($admin)->get(route('sahodaya.events.certificates.batches.download', [
            'tenantId' => $sahodaya->id, 'event' => $event->id, 'batch' => $batch->id,
        ]));
        $response->assertNotFound();
    }
}
