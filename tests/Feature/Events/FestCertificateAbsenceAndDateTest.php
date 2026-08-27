<?php

namespace Tests\Feature\Events;

use App\Models\Certificate;
use App\Models\FestAttendance;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\FestCertificateService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers two related Sahodaya-admin certificate fixes:
 *
 * 1. A participant marked absent (fest_attendance, written by both the dedicated
 *    attendance screen and Mark Entry's own inline Present/Absent selector) no longer
 *    gets a participation certificate for that item — see
 *    FestCertificateService::eligibleParticipantsForEvent(). Someone absent for only
 *    SOME of their registered items still gets one, just without the absent item on it
 *    (participationItems()/participationGradesByItem() share the same base query).
 *
 * 2. certificate_date now defaults to the event's own end/start date instead of
 *    perpetually being today — with an admin-settable FestEvent::certificate_date
 *    override, via FestCertificateController::updateCertificateDate().
 */
class FestCertificateAbsenceAndDateTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, school: Tenant, admin: User, event: FestEvent} */
    private function fixture(array $eventOverrides = []): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Absence Date Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'AD', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Absence Date School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create(array_merge([
            'tenant_id' => $sahodaya->id, 'title' => 'Absence Date Event', 'event_type' => 'kalolsavam',
        ], $eventOverrides));

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

    public function test_participation_certificate_is_not_generated_for_a_participant_absent_from_their_only_item(): void
    {
        $f = $this->fixture();
        $item = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Solo Song', 'item_code' => 'AB1']);
        $participant = $this->registerParticipant($f['event'], $item, $f['school']->id, 601);

        FestAttendance::create([
            'event_id' => $f['event']->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'status' => 'absent',
        ]);

        $created = app(FestCertificateService::class)->generateParticipationForEvent($f['event']);

        $this->assertCount(0, $created);
        $this->assertSame(0, Certificate::where('cert_type', 'participation')->count());
    }

    public function test_participation_certificate_still_generated_when_present_for_at_least_one_of_several_items(): void
    {
        $f = $this->fixture();
        $itemPresent = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Solo Song', 'item_code' => 'AB2']);
        $itemAbsent = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Elocution', 'item_code' => 'AB3']);

        $participantPresent = $this->registerParticipant($f['event'], $itemPresent, $f['school']->id, 602);
        $participantAbsent = FestParticipant::create([
            'registration_id' => FestRegistration::create([
                'event_id' => $f['event']->id, 'item_id' => $itemAbsent->id, 'school_id' => $f['school']->id, 'status' => 'approved',
            ])->id,
            'event_id' => $f['event']->id, 'student_id' => 602, 'participant_type' => 'student', 'participant_role' => 'performer',
        ]);

        FestAttendance::create([
            'event_id' => $f['event']->id, 'item_id' => $itemAbsent->id, 'participant_id' => $participantAbsent->id, 'status' => 'absent',
        ]);

        $created = app(FestCertificateService::class)->generateParticipationForEvent($f['event']);
        $this->assertCount(1, $created);

        $certificate = $created[0];
        $context = app(FestCertificateService::class)->renderContext($certificate);

        $this->assertSame('Solo Song', $context['fieldValues']['item_title'], 'Only the item they attended should appear.');
        $this->assertStringNotContainsString('Elocution', $context['fieldValues']['item_title']);
        $this->assertSame($participantPresent->student_id, $participantAbsent->student_id, 'Sanity check: same person.');
    }

    public function test_certificate_date_defaults_to_event_end_when_no_override_is_set(): void
    {
        $f = $this->fixture(['event_end' => '2026-07-25', 'event_start' => '2026-07-23']);
        $item = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Solo Song', 'item_code' => 'CD1']);
        $participant = $this->registerParticipant($f['event'], $item, $f['school']->id, 603);

        $certificate = Certificate::create([
            'entity_type' => FestParticipant::class, 'entity_id' => $participant->id,
            'cert_type' => 'participation', 'verification_uuid' => (string) Str::uuid(), 'generated_at' => now(),
        ]);

        $context = app(FestCertificateService::class)->renderContext($certificate);
        $dateHtml = $context['fieldValues']['certificate_date'];

        $this->assertStringContainsString('>25<', $dateHtml);
        $this->assertStringContainsString('July', $dateHtml);
        $this->assertStringContainsString('>2026<', $dateHtml);
    }

    public function test_certificate_date_uses_the_admin_override_when_set(): void
    {
        $f = $this->fixture(['event_end' => '2026-07-25', 'event_start' => '2026-07-23', 'certificate_date' => '2026-08-01']);
        $item = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Solo Song', 'item_code' => 'CD2']);
        $participant = $this->registerParticipant($f['event'], $item, $f['school']->id, 604);

        $certificate = Certificate::create([
            'entity_type' => FestParticipant::class, 'entity_id' => $participant->id,
            'cert_type' => 'participation', 'verification_uuid' => (string) Str::uuid(), 'generated_at' => now(),
        ]);

        $context = app(FestCertificateService::class)->renderContext($certificate);
        $dateHtml = $context['fieldValues']['certificate_date'];

        $this->assertStringContainsString('>1<', $dateHtml);
        $this->assertStringContainsString('August', $dateHtml);
        $this->assertStringNotContainsString('July', $dateHtml);
    }

    public function test_update_certificate_date_route_sets_and_clears_the_override(): void
    {
        $f = $this->fixture();

        $this->actingAs($f['admin'])->post(route('sahodaya.events.certificates.certificate-date', [
            'tenantId' => $f['sahodaya']->id, 'event' => $f['event']->id,
        ]), ['certificate_date' => '2026-09-10'])->assertRedirect();

        $this->assertSame('2026-09-10', $f['event']->fresh()->certificate_date->format('Y-m-d'));

        $this->actingAs($f['admin'])->post(route('sahodaya.events.certificates.certificate-date', [
            'tenantId' => $f['sahodaya']->id, 'event' => $f['event']->id,
        ]), ['certificate_date' => null])->assertRedirect();

        $this->assertNull($f['event']->fresh()->certificate_date);
    }
}
