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
 * End-to-end coverage for the async render-and-cache pipeline
 * (FestCertificateController::generateAndRenderBatch() -> Bus::batch() ->
 * RenderCertificateChunkJob) that replaced synchronous, uncached rendering on every
 * single/bulk certificate request. QUEUE_CONNECTION=sync in phpunit.xml means the batch
 * (including its then() callback) runs inline within the test — no real queue worker
 * needed to exercise the real chunking/rendering/caching logic end-to-end.
 */
class FestCertificateBatchGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function makeSahodaya(): Tenant
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Batch Gen Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'BG', 'student_data_mode' => 'counts_only']);

        return $sahodaya;
    }

    private function makeSchool(string $sahodayaId): Tenant
    {
        return Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodayaId,
            'name' => 'Batch Gen School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);
    }

    /** @return list<Certificate> */
    private function makeCertificates(FestEvent $event, FestEventItem $item, string $schoolId, int $count): array
    {
        $certificates = [];

        for ($i = 0; $i < $count; $i++) {
            $registration = FestRegistration::create([
                'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $schoolId, 'status' => 'approved',
            ]);
            $participant = FestParticipant::create([
                'registration_id' => $registration->id, 'event_id' => $event->id, 'student_id' => 100 + $i,
                'participant_type' => 'student', 'participant_role' => 'performer',
            ]);

            $certificates[] = Certificate::create([
                'entity_type'        => FestParticipant::class,
                'entity_id'          => $participant->id,
                'cert_type'          => 'participation',
                'verification_uuid' => (string) Str::uuid(),
                'generated_at'      => now(),
            ]);
        }

        return $certificates;
    }

    public function test_batch_renders_and_caches_both_variants_for_every_certificate(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = $this->makeSahodaya();
        $school = $this->makeSchool($sahodaya->id);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Batch Gen Event', 'event_type' => 'kalolsavam']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'item_code' => 'SS1']);
        $certificates = $this->makeCertificates($event, $item, $school->id, 3);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.certificates.batches.store', [
            'tenantId' => $sahodaya->id,
            'event'    => $event->id,
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('certificate_batch_id');
        $batchId = session('certificate_batch_id');

        $batch = CertificateBatch::findOrFail($batchId);
        $this->assertSame(CertificateBatch::STATUS_COMPLETED, $batch->status);
        $this->assertSame(3, $batch->total_count);
        $this->assertSame(3, $batch->processed_count);
        $this->assertSame(3, $batch->succeeded_count);
        $this->assertSame(0, $batch->failed_count);
        $this->assertSame($event->id, $batch->event_id);
        $this->assertSame('generate', $batch->batch_type);

        foreach ($certificates as $certificate) {
            $certificate->refresh();

            $this->assertNotNull($certificate->file_path);
            $this->assertNotNull($certificate->plain_file_path);
            $this->assertNotNull($certificate->storage_disk);
            $this->assertNotNull($certificate->content_hash);
            $this->assertNotNull($certificate->rendered_at);
            $this->assertFalse($certificate->is_stale);

            $withBg = TenantStorage::get($certificate->file_path, $certificate->storage_disk);
            $plain = TenantStorage::get($certificate->plain_file_path, $certificate->storage_disk);
            $this->assertNotNull($withBg);
            $this->assertNotNull($plain);
            $this->assertStringStartsWith('%PDF', $withBg);
            $this->assertStringStartsWith('%PDF', $plain);
        }

        // The progress endpoint (polled by Certificates.vue) reports the same terminal
        // state a client would see if it polled after the redirect landed.
        $progress = $this->actingAs($admin)->getJson(route('sahodaya.events.certificates.batches.progress', [
            'tenantId' => $sahodaya->id,
            'event'    => $event->id,
            'batch'    => $batch->id,
        ]));
        $progress->assertOk();
        $progress->assertJson([
            'status'          => CertificateBatch::STATUS_COMPLETED,
            'total_count'     => 3,
            'succeeded_count' => 3,
            'failed_count'    => 0,
        ]);
    }

    public function test_download_zip_serves_the_cached_file_instead_of_re_rendering(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = $this->makeSahodaya();
        $school = $this->makeSchool($sahodaya->id);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Cache Reuse Event', 'event_type' => 'kalolsavam']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'item_code' => 'SS1']);
        [$certificate] = $this->makeCertificates($event, $item, $school->id, 1);

        $this->actingAs($admin)->post(route('sahodaya.events.certificates.batches.store', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]))->assertRedirect();

        $certificate->refresh();
        $cachedBytes = TenantStorage::get($certificate->file_path, $certificate->storage_disk);

        // Overwrite the cached file with a distinguishable marker so we can prove the ZIP
        // came from storage, not a fresh render — a fresh render would produce a real
        // "%PDF"-prefixed document, not this literal marker string.
        TenantStorage::put($certificate->file_path, "%PDF-MARKER\n", $certificate->storage_disk);

        $response = $this->actingAs($admin)->get(route('sahodaya.events.certificates.download-zip', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]));
        $response->assertOk();

        $zipPath = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);
        $this->assertSame(1, $zip->numFiles);
        $this->assertSame("%PDF-MARKER\n", $zip->getFromIndex(0));
        $zip->close();

        $this->assertNotSame($cachedBytes, "%PDF-MARKER\n");
    }
}
