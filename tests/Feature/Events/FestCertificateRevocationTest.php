<?php

namespace Tests\Feature\Events;

use App\Models\Certificate;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Services\Events\FestCertificateService;
use App\Support\TenantStorage;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers a real, confirmed production bug: generateForEvent()/generateParticipationForEvent()
 * only ever ADDED certificates for whoever currently qualifies — nothing re-checked an
 * already-issued certificate against a later mark correction. Confirmed via a production
 * tinker session on a real event: an 11-member team newly at position 3 (after a
 * correction) had NO winner certificate, while two teams sitting at position 4 (evidently
 * 3rd before that correction, freeing up the slot) still held one. Re-running "Generate"
 * only fixed the first half (added the missing one) — it never revoked the two that had
 * become factually wrong "winner" certificates for a rank they no longer held.
 */
class FestCertificateRevocationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, school: Tenant, event: FestEvent} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Revocation Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'RV', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Revocation School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Revocation Event', 'event_type' => 'kalolsavam']);

        return compact('sahodaya', 'school', 'event');
    }

    private function makeParticipant(FestEvent $event, FestEventItem $item, string $schoolId, int $studentId): FestParticipant
    {
        $registration = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $schoolId, 'status' => 'approved',
        ]);

        return FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $event->id, 'student_id' => $studentId,
            'participant_type' => 'student', 'participant_role' => 'performer',
        ]);
    }

    public function test_regenerating_revokes_a_winner_certificate_whose_mark_no_longer_qualifies(): void
    {
        $f = $this->fixture();
        $item = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Choral Recitation', 'item_code' => 'RV1']);
        $participant = $this->makeParticipant($f['event'], $item, $f['school']->id, 1001);

        FestMark::create(['event_id' => $f['event']->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 3]);
        $created = app(FestCertificateService::class)->generateForEvent($f['event']);
        $this->assertCount(1, $created);
        $certificateId = $created[0]->id;

        // Simulate the cached PDF a real "Render & cache" run would have already produced
        // — the revocation must clean this up too, not just delete the row.
        $certificate = Certificate::find($certificateId);
        $certificate->update(['file_path' => 'certificates/fake/1.pdf', 'storage_disk' => TenantStorage::uploadDisk()]);
        TenantStorage::put('certificates/fake/1.pdf', '%PDF-fake');
        $this->assertTrue(TenantStorage::exists('certificates/fake/1.pdf'));

        // The mark gets corrected: this team is actually 4th, not 3rd.
        FestMark::where('participant_id', $participant->id)->update(['position' => 4]);

        app(FestCertificateService::class)->generateForEvent($f['event']);

        $this->assertNull(Certificate::find($certificateId), 'The no-longer-qualifying certificate must be deleted.');
        $this->assertFalse(TenantStorage::exists('certificates/fake/1.pdf'), 'Its cached PDF must be cleaned up, not orphaned.');
    }

    public function test_regenerating_creates_a_certificate_for_a_newly_qualifying_team_without_touching_others(): void
    {
        $f = $this->fixture();
        $item = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Choral Recitation', 'item_code' => 'RV2']);
        $first = $this->makeParticipant($f['event'], $item, $f['school']->id, 1002);
        $second = $this->makeParticipant($f['event'], $item, $f['school']->id, 1003);

        FestMark::create(['event_id' => $f['event']->id, 'item_id' => $item->id, 'participant_id' => $first->id, 'position' => 1]);
        FestMark::create(['event_id' => $f['event']->id, 'item_id' => $item->id, 'participant_id' => $second->id, 'position' => 2]);
        app(FestCertificateService::class)->generateForEvent($f['event']);
        $this->assertSame(2, Certificate::where('cert_type', 'winner')->count());
        $firstCertId = Certificate::where('entity_id', $first->id)->where('cert_type', 'winner')->value('id');

        // A third team is now correctly ranked 3rd (a tie got resolved, say).
        $third = $this->makeParticipant($f['event'], $item, $f['school']->id, 1004);
        FestMark::create(['event_id' => $f['event']->id, 'item_id' => $item->id, 'participant_id' => $third->id, 'position' => 3]);

        app(FestCertificateService::class)->generateForEvent($f['event']);

        $this->assertSame(3, Certificate::where('cert_type', 'winner')->count());
        $this->assertTrue(Certificate::where('entity_id', $third->id)->where('cert_type', 'winner')->exists());
        // The still-qualifying 1st place certificate must be left exactly as it was —
        // same row, not deleted and recreated.
        $this->assertSame($firstCertId, Certificate::where('entity_id', $first->id)->where('cert_type', 'winner')->value('id'));
    }

    public function test_regenerating_a_single_item_does_not_touch_another_items_stale_certificate(): void
    {
        $f = $this->fixture();
        $itemA = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Solo Song', 'item_code' => 'RV3']);
        $itemB = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Elocution', 'item_code' => 'RV4']);

        $participantA = $this->makeParticipant($f['event'], $itemA, $f['school']->id, 1005);
        $participantB = $this->makeParticipant($f['event'], $itemB, $f['school']->id, 1006);

        FestMark::create(['event_id' => $f['event']->id, 'item_id' => $itemA->id, 'participant_id' => $participantA->id, 'position' => 3]);
        FestMark::create(['event_id' => $f['event']->id, 'item_id' => $itemB->id, 'participant_id' => $participantB->id, 'position' => 3]);
        app(FestCertificateService::class)->generateForEvent($f['event']);
        $this->assertSame(2, Certificate::where('cert_type', 'winner')->count());

        // Item A's mark is corrected to no longer qualify.
        FestMark::where('participant_id', $participantA->id)->update(['position' => 5]);

        // Regenerating ONLY item B must not revoke item A's now-stale certificate.
        app(FestCertificateService::class)->generateForEvent($f['event'], $itemB->id);

        $this->assertTrue(Certificate::where('entity_id', $participantA->id)->where('cert_type', 'winner')->exists(), 'Item A\'s certificate must survive a regenerate scoped to item B only.');
        $this->assertTrue(Certificate::where('entity_id', $participantB->id)->where('cert_type', 'winner')->exists());
    }

    public function test_regenerating_participation_revokes_a_certificate_for_someone_no_longer_eligible(): void
    {
        $f = $this->fixture();
        $item = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Solo Song', 'item_code' => 'RV5']);
        $participant = $this->makeParticipant($f['event'], $item, $f['school']->id, 1007);

        $created = app(FestCertificateService::class)->generateParticipationForEvent($f['event']);
        $this->assertCount(1, $created);
        $certificateId = $created[0]->id;

        // Their only registration is rejected after the certificate was already issued.
        $participant->registration->update(['status' => 'rejected']);

        app(FestCertificateService::class)->generateParticipationForEvent($f['event']);

        $this->assertNull(Certificate::find($certificateId), 'A participation certificate for someone with zero remaining eligible items must be revoked.');
    }

    public function test_regenerating_participation_handles_anchor_drift_without_duplicating(): void
    {
        $f = $this->fixture();
        $itemA = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Solo Song', 'item_code' => 'RV6']);
        $itemB = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Elocution', 'item_code' => 'RV7']);

        // Same person, two items — the lower-id row (itemA's) becomes the anchor.
        $rowA = $this->makeParticipant($f['event'], $itemA, $f['school']->id, 1008);
        $rowB = $this->makeParticipant($f['event'], $itemB, $f['school']->id, 1008);
        $this->assertLessThan($rowB->id, $rowA->id);

        $created = app(FestCertificateService::class)->generateParticipationForEvent($f['event']);
        $this->assertCount(1, $created);
        $oldCertificateId = $created[0]->id;
        $this->assertSame($rowA->id, Certificate::find($oldCertificateId)->entity_id);

        // Row A's registration is rejected — the person is still eligible overall via row B.
        $rowA->registration->update(['status' => 'rejected']);

        app(FestCertificateService::class)->generateParticipationForEvent($f['event']);

        $this->assertNull(Certificate::find($oldCertificateId), 'The stale anchor\'s certificate must be revoked, not left as a duplicate.');
        $this->assertSame(1, Certificate::where('cert_type', 'participation')->count(), 'Exactly one certificate for this person, at the new anchor.');
        $this->assertTrue(Certificate::where('entity_id', $rowB->id)->where('cert_type', 'participation')->exists());
    }
}
