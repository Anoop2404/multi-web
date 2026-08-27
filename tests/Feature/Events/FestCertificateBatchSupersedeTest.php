<?php

namespace Tests\Feature\Events;

use App\Models\Certificate;
use App\Models\CertificateBatch;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
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
 * Covers FestCertificateController::deleteSupersededBatches() — re-running "the same
 * kind" of render/export operation replaces its own entry in Recent render & export
 * runs (FestCertificateController::index()'s recentBatchesForEvent()) rather than piling
 * up alongside every earlier run of it, which is what the admin-facing list looked like
 * before this: three "Render · Whole event" rows and three "ZIP export · Participation
 * certificates — whole event" rows (one of them a stale failure) for the same event.
 */
class FestCertificateBatchSupersedeTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, school: Tenant, admin: User, event: FestEvent} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Supersede Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'SU', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Supersede School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Supersede Event', 'event_type' => 'kalolsavam']);

        return compact('sahodaya', 'school', 'admin', 'event');
    }

    private function makeCertificate(FestEvent $event, FestEventItem $item, string $schoolId, int $studentId): Certificate
    {
        $registration = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $schoolId, 'status' => 'approved',
        ]);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $event->id, 'student_id' => $studentId,
            'participant_type' => 'student', 'participant_role' => 'performer',
        ]);

        return Certificate::create([
            'entity_type' => FestParticipant::class, 'entity_id' => $participant->id,
            'cert_type' => 'participation', 'verification_uuid' => (string) Str::uuid(), 'generated_at' => now(),
        ]);
    }

    public function test_re_running_the_same_render_scope_replaces_the_prior_completed_batch(): void
    {
        $f = $this->fixture();
        $item = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Solo Song', 'item_code' => 'SP1']);
        $this->makeCertificate($f['event'], $item, $f['school']->id, 701);

        $this->actingAs($f['admin'])->post(route('sahodaya.events.certificates.batches.store', [
            'tenantId' => $f['sahodaya']->id, 'event' => $f['event']->id,
        ]))->assertRedirect();
        $firstBatchId = session('certificate_batch_id');

        $this->actingAs($f['admin'])->post(route('sahodaya.events.certificates.batches.store', [
            'tenantId' => $f['sahodaya']->id, 'event' => $f['event']->id,
        ]))->assertRedirect();
        $secondBatchId = session('certificate_batch_id');

        $this->assertNotSame($firstBatchId, $secondBatchId);
        $this->assertModelMissing(CertificateBatch::find($firstBatchId) ?? new CertificateBatch(['id' => $firstBatchId]));
        $this->assertSame(1, CertificateBatch::where('event_id', $f['event']->id)->where('batch_type', 'generate')->count());
    }

    public function test_re_running_a_different_render_scope_does_not_remove_the_prior_batch(): void
    {
        $f = $this->fixture();
        $itemA = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Solo Song', 'item_code' => 'SP2']);
        $itemB = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Elocution', 'item_code' => 'SP3']);
        $this->makeCertificate($f['event'], $itemA, $f['school']->id, 702);
        $this->makeCertificate($f['event'], $itemB, $f['school']->id, 703);

        $this->actingAs($f['admin'])->post(route('sahodaya.events.certificates.batches.store', [
            'tenantId' => $f['sahodaya']->id, 'event' => $f['event']->id,
        ]), ['item_id' => $itemA->id])->assertRedirect();

        $this->actingAs($f['admin'])->post(route('sahodaya.events.certificates.batches.store', [
            'tenantId' => $f['sahodaya']->id, 'event' => $f['event']->id,
        ]), ['item_id' => $itemB->id])->assertRedirect();

        $this->assertSame(2, CertificateBatch::where('event_id', $f['event']->id)->where('batch_type', 'generate')->count());
    }

    public function test_re_running_the_same_zip_export_scope_deletes_the_prior_storage_file(): void
    {
        $f = $this->fixture();
        $item = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Solo Song', 'item_code' => 'SP4']);
        $this->makeCertificate($f['event'], $item, $f['school']->id, 704);

        $this->actingAs($f['admin'])->post(route('sahodaya.events.certificates.download-zip.queue', [
            'tenantId' => $f['sahodaya']->id, 'event' => $f['event']->id,
        ]))->assertRedirect();
        $firstBatch = CertificateBatch::findOrFail(session('certificate_batch_id'));
        $this->assertNotNull($firstBatch->file_path);
        $this->assertTrue(TenantStorage::exists($firstBatch->file_path, $firstBatch->storage_disk));
        $firstFilePath = $firstBatch->file_path;
        $firstDisk = $firstBatch->storage_disk;

        $this->actingAs($f['admin'])->post(route('sahodaya.events.certificates.download-zip.queue', [
            'tenantId' => $f['sahodaya']->id, 'event' => $f['event']->id,
        ]))->assertRedirect();

        $this->assertModelMissing(CertificateBatch::find($firstBatch->id) ?? new CertificateBatch(['id' => $firstBatch->id]));
        $this->assertFalse(TenantStorage::exists($firstFilePath, $firstDisk), 'The superseded export\'s file should be cleaned up, not orphaned.');
        $this->assertSame(1, CertificateBatch::where('event_id', $f['event']->id)->where('batch_type', 'zip_export')->count());
    }

    public function test_published_only_and_all_certificates_zip_exports_are_not_conflated(): void
    {
        $f = $this->fixture();
        $f['event']->update(['results_published' => true]);

        $item = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Solo Song', 'item_code' => 'SP5']);
        $registration = FestRegistration::create([
            'event_id' => $f['event']->id, 'item_id' => $item->id, 'school_id' => $f['school']->id, 'status' => 'approved',
        ]);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $f['event']->id, 'student_id' => 705,
            'participant_type' => 'student', 'participant_role' => 'performer',
        ]);
        FestMark::create([
            'event_id' => $f['event']->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 1,
        ]);
        Certificate::create([
            'entity_type' => FestParticipant::class, 'entity_id' => $participant->id,
            'cert_type' => 'winner', 'verification_uuid' => (string) Str::uuid(), 'generated_at' => now(),
        ]);
        $this->makeCertificate($f['event'], $item, $f['school']->id, 706);

        // "All certificates (ZIP)" — no filters at all.
        $this->actingAs($f['admin'])->post(route('sahodaya.events.certificates.download-zip.queue', [
            'tenantId' => $f['sahodaya']->id, 'event' => $f['event']->id,
        ]))->assertRedirect();

        // "Merit winners only (ZIP)" — published_only=1, same otherwise-empty scope.
        $this->actingAs($f['admin'])->post(route('sahodaya.events.certificates.download-zip.queue', [
            'tenantId' => $f['sahodaya']->id, 'event' => $f['event']->id,
        ]), ['published_only' => '1'])->assertRedirect();

        $this->assertSame(2, CertificateBatch::where('event_id', $f['event']->id)->where('batch_type', 'zip_export')->count());
    }
}
