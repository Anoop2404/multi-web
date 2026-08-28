<?php

namespace Tests\Feature\Events;

use App\Models\Certificate;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\FestMarkSaveService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers wiring CertificateStalenessMarker into the mark-entry and attendance-save
 * paths. Before this, the class existed (with three purpose-built entry points) but had
 * zero call sites anywhere — a certificate rendered before a grade was entered (or
 * before an attendance change) kept serving its stale cached PDF indefinitely, since
 * cachedOrFreshPdf() only re-renders when is_stale is true and nothing ever set it. This
 * is exactly what produced a real, reported symptom: a public results page correctly
 * showed "Picture Talk — Grade A", but the participant's own already-cached certificate
 * PDF showed no grade for that item at all.
 */
class FestCertificateStalenessMarkingTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, school: Tenant, admin: User, event: FestEvent} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Staleness Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'SM', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Staleness School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Staleness Event', 'event_type' => 'kalolsavam']);

        return compact('sahodaya', 'school', 'admin', 'event');
    }

    private function registerParticipant(FestEvent $event, FestEventItem $item, string $schoolId, int $studentId): FestParticipant
    {
        $registration = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $schoolId, 'status' => 'approved',
        ]);

        return FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $event->id, 'student_id' => $studentId,
            'participant_type' => 'student', 'participant_role' => 'performer',
        ]);
    }

    public function test_saving_a_mark_flags_the_participants_own_certificate_stale(): void
    {
        $f = $this->fixture();
        $item = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Solo Song', 'item_code' => 'SM1']);
        $participant = $this->registerParticipant($f['event'], $item, $f['school']->id, 801);

        $certificate = Certificate::create([
            'entity_type' => FestParticipant::class, 'entity_id' => $participant->id,
            'cert_type' => 'winner', 'verification_uuid' => (string) Str::uuid(),
            'generated_at' => now(), 'is_stale' => false,
        ]);

        app(FestMarkSaveService::class)->save($f['event'], [
            'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 1, 'grade' => 'A',
        ], $f['admin']->id);

        $this->assertTrue($certificate->fresh()->is_stale);
    }

    public function test_saving_a_mark_flags_the_persons_participation_aggregate_certificate_stale(): void
    {
        $f = $this->fixture();
        $itemA = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Picture Talk', 'item_code' => 'SM2']);
        $itemB = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Conversation', 'item_code' => 'SM3']);

        $participantA = $this->registerParticipant($f['event'], $itemA, $f['school']->id, 802);
        $participantB = FestParticipant::create([
            'registration_id' => FestRegistration::create([
                'event_id' => $f['event']->id, 'item_id' => $itemB->id, 'school_id' => $f['school']->id, 'status' => 'approved',
            ])->id,
            'event_id' => $f['event']->id, 'student_id' => 802, 'participant_type' => 'student', 'participant_role' => 'performer',
        ]);

        // The aggregate participation certificate is anchored to whichever of this
        // person's FestParticipant rows sorts first by id (generateParticipationForEvent())
        // — here, participantA — even though the mark about to be saved is for itemB.
        $aggregateCertificate = Certificate::create([
            'entity_type' => FestParticipant::class, 'entity_id' => $participantA->id,
            'cert_type' => 'participation', 'verification_uuid' => (string) Str::uuid(),
            'generated_at' => now(), 'is_stale' => false,
        ]);

        app(FestMarkSaveService::class)->save($f['event'], [
            'item_id' => $itemB->id, 'participant_id' => $participantB->id, 'grade' => 'A',
        ], $f['admin']->id);

        $this->assertTrue(
            $aggregateCertificate->fresh()->is_stale,
            'A grade change on ANY of a person\'s items must flag their aggregate participation certificate, not just a cert anchored to that exact item\'s own participant row.'
        );
    }

    public function test_marking_attendance_flags_the_participants_certificate_stale(): void
    {
        $f = $this->fixture();
        $item = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Solo Song', 'item_code' => 'SM4']);
        $participant = $this->registerParticipant($f['event'], $item, $f['school']->id, 803);

        $certificate = Certificate::create([
            'entity_type' => FestParticipant::class, 'entity_id' => $participant->id,
            'cert_type' => 'participation', 'verification_uuid' => (string) Str::uuid(),
            'generated_at' => now(), 'is_stale' => false,
        ]);

        $this->actingAs($f['admin'])->post(route('sahodaya.events.attendance.store', [
            'tenantId' => $f['sahodaya']->id, 'event' => $f['event']->id,
        ]), [
            'item_id' => $item->id, 'participant_id' => $participant->id, 'status' => 'absent',
        ])->assertRedirect();

        $this->assertTrue($certificate->fresh()->is_stale);
    }
}
