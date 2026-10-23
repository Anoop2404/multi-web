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

    public function test_event_dates_formats_a_multi_day_span_with_ordinal_suffixes(): void
    {
        $f = $this->fixture(['event_start' => '2026-09-22', 'event_end' => '2026-09-26']);
        $item = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Solo Song', 'item_code' => 'CD3']);
        $participant = $this->registerParticipant($f['event'], $item, $f['school']->id, 605);

        $certificate = Certificate::create([
            'entity_type' => FestParticipant::class, 'entity_id' => $participant->id,
            'cert_type' => 'participation', 'verification_uuid' => (string) Str::uuid(), 'generated_at' => now(),
        ]);

        $context = app(FestCertificateService::class)->renderContext($certificate);

        $this->assertSame('22nd - 26th September 2026', $context['fieldValues']['event_dates']);
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
    public function test_item_conduct_overrides_only_merit_details_and_marks_existing_merits_stale(): void
    {
        $f = $this->fixture(['event_start' => '2026-10-24', 'event_end' => '2026-10-25', 'venue' => 'Main Venue']);
        $item = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Solo Song', 'item_code' => 'PH1']);
        $participant = $this->registerParticipant($f['event'], $item, $f['school']->id, 900);
        $certificates = [];
        foreach (['winner', 'participation'] as $type) {
            $certificates[$type] = Certificate::create([
                'entity_type' => FestParticipant::class, 'entity_id' => $participant->id,
                'cert_type' => $type, 'verification_uuid' => (string) Str::uuid(), 'generated_at' => now(), 'is_stale' => false,
            ]);
        }
        $url = route('sahodaya.events.certificates.phases', ['tenantId' => $f['sahodaya']->id, 'event' => $f['event']->id]);
        $this->actingAs($f['admin'])->post($url, [
            'item_ids' => [$item->id], 'name' => 'Phase 1', 'venue' => 'Phase School, Kozhikode', 'date_start' => '2026-10-17',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $service = app(FestCertificateService::class);
        $merit = $service->renderContext($certificates['winner'])['fieldValues'];
        $participation = $service->renderContext($certificates['participation'])['fieldValues'];
        $this->assertSame('Phase School, Kozhikode', $merit['conduct_location']);
        $this->assertSame('Phase School, Kozhikode', $merit['venue']);
        $this->assertSame('17th October 2026', $merit['event_dates']);
        $this->assertSame($f['school']->name, $merit['school_name']);
        $this->assertSame('Main Venue', $participation['venue']);
        $this->assertSame('24th - 25th October 2026', $participation['event_dates']);
        $this->assertSame($participation['certificate_date'], $merit['certificate_date']);
        $this->assertTrue($certificates['winner']->fresh()->is_stale);
        $this->assertFalse($certificates['participation']->fresh()->is_stale);
        $phase = \App\Models\FestCertificatePhase::where('event_id', $f['event']->id)->firstOrFail();
        $this->actingAs($f['admin'])->post($url, [
            'phase_id' => $phase->id, 'name' => 'Phase 1', 'venue' => 'Phase School, Kozhikode',
            'date_start' => '2026-10-17', 'item_ids' => [],
        ])->assertSessionHasNoErrors();
        $this->assertNull($item->fresh()->certificate_phase_id);
        $this->assertSame('Main Venue', $service->renderContext($certificates['winner'])['fieldValues']['venue']);
    }

    public function test_item_conduct_rejects_foreign_items_and_invalid_date_ranges(): void
    {
        $f = $this->fixture();
        $item = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Solo Song', 'item_code' => 'PH2']);
        $otherEvent = FestEvent::create(['tenant_id' => $f['sahodaya']->id, 'title' => 'Other Event', 'event_type' => 'kalolsavam']);
        $other = FestEventItem::create(['event_id' => $otherEvent->id, 'title' => 'Other Song', 'item_code' => 'PH3']);
        $url = route('sahodaya.events.certificates.phases', ['tenantId' => $f['sahodaya']->id, 'event' => $f['event']->id]);
        $this->actingAs($f['admin'])->post($url, ['name' => 'Phase', 'date_start' => '2026-10-17', 'item_ids' => [$item->id, $other->id], 'venue' => 'Wrong'])->assertStatus(422);
        $this->assertNull($item->fresh()->certificate_phase_id);
        $this->actingAs($f['admin'])->post($url, ['name' => 'Phase', 'venue' => 'Venue', 'item_ids' => [$item->id], 'date_start' => '2026-10-20', 'date_end' => '2026-10-17'])->assertSessionHasErrors('date_end');
    }
    public function test_certificate_phase_updates_all_assigned_items_without_changing_event_phases(): void
    {
        $f = $this->fixture(['venue' => 'Main Venue']);
        $items = collect(['A', 'B'])->map(fn ($code) => FestEventItem::create([
            'event_id' => $f['event']->id, 'title' => 'Item '.$code, 'item_code' => 'CP'.$code,
        ]));
        $url = route('sahodaya.events.certificates.phases', ['tenantId' => $f['sahodaya']->id, 'event' => $f['event']->id]);
        $data = ['name' => 'Phase 1', 'venue' => 'Early Venue', 'date_start' => '2026-10-17', 'item_ids' => $items->pluck('id')->all()];
        $this->actingAs($f['admin'])->post($url, $data)->assertSessionHasNoErrors();
        $phase = \App\Models\FestCertificatePhase::where('event_id', $f['event']->id)->firstOrFail();
        foreach ($items as $item) {
            $this->assertSame($phase->id, $item->fresh()->certificate_phase_id);
            $this->assertNull($item->fresh()->phase_id);
        }
        $this->actingAs($f['admin'])->post($url, array_merge($data, [
            'phase_id' => $phase->id, 'venue' => 'Revised Venue', 'date_start' => '2026-10-18',
        ]))->assertSessionHasNoErrors();
        foreach ($items as $item) {
            $this->assertSame('Revised Venue', $item->fresh()->certificatePhase->conductDetails()['venue']);
            $this->assertSame('2026-10-18', $item->fresh()->certificatePhase->conductDetails()['date_start']);
        }
        $this->assertSame('Main Venue', $f['event']->fresh()->venue);
        $otherEvent = FestEvent::create(['tenant_id' => $f['sahodaya']->id, 'title' => 'Other', 'event_type' => 'kalolsavam']);
        $otherPhase = \App\Models\FestCertificatePhase::create(['event_id' => $otherEvent->id, 'name' => 'Other', 'venue' => 'Other', 'date_start' => '2026-10-19']);
        $this->actingAs($f['admin'])->post($url, array_merge($data, ['phase_id' => $otherPhase->id]))->assertNotFound();
    }

    public function test_removing_certificate_phase_restores_defaults_and_preserves_items_and_certificates(): void
    {
        $f = $this->fixture(['venue' => 'Default Venue']);
        $phase = \App\Models\FestCertificatePhase::create(['event_id' => $f['event']->id, 'name' => 'Remove me', 'venue' => 'Phase Venue', 'date_start' => '2026-10-17']);
        $item = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Song', 'item_code' => 'DEL', 'certificate_phase_id' => $phase->id]);
        $participant = $this->registerParticipant($f['event'], $item, $f['school']->id, 901);
        $certificates = [];
        foreach (['winner', 'participation'] as $type) {
            $certificates[$type] = Certificate::create(['entity_type' => FestParticipant::class, 'entity_id' => $participant->id,
                'cert_type' => $type, 'verification_uuid' => (string) Str::uuid(), 'generated_at' => now(), 'is_stale' => false]);
        }
        $url = route('sahodaya.events.certificates.phases.destroy', ['tenantId' => $f['sahodaya']->id, 'event' => $f['event']->id, 'phaseId' => $phase->id]);
        $this->actingAs($f['admin'])->delete($url)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($phase->fresh());
        $this->assertNull($item->fresh()->certificate_phase_id);
        $this->assertTrue($certificates['winner']->fresh()->is_stale);
        $this->assertFalse($certificates['participation']->fresh()->is_stale);
        $this->assertSame('Default Venue', app(FestCertificateService::class)->renderContext($certificates['winner'])['fieldValues']['venue']);

        $otherEvent = FestEvent::create(['tenant_id' => $f['sahodaya']->id, 'title' => 'Other', 'event_type' => 'kalolsavam']);
        $otherPhase = \App\Models\FestCertificatePhase::create(['event_id' => $otherEvent->id, 'name' => 'Keep', 'venue' => 'Other', 'date_start' => '2026-10-19']);
        $foreignUrl = route('sahodaya.events.certificates.phases.destroy', ['tenantId' => $f['sahodaya']->id, 'event' => $f['event']->id, 'phaseId' => $otherPhase->id]);
        $this->actingAs($f['admin'])->delete($foreignUrl)->assertNotFound();
        $this->assertNotNull($otherPhase->fresh());
        $empty = \App\Models\FestCertificatePhase::create(['event_id' => $f['event']->id, 'name' => 'Empty', 'venue' => 'Other', 'date_start' => '2026-10-19']);
        $this->actingAs($f['admin'])->delete(route('sahodaya.events.certificates.phases.destroy', ['tenantId' => $f['sahodaya']->id, 'event' => $f['event']->id, 'phaseId' => $empty->id]))->assertRedirect();
        $this->assertNull($empty->fresh());
    }

    public function test_moving_item_between_certificate_phases_changes_render_values_and_hash(): void
    {
        $f = $this->fixture(['event_start' => '2026-10-24', 'event_end' => '2026-10-25', 'venue' => 'Default Venue']);
        $item = FestEventItem::create(['event_id' => $f['event']->id, 'title' => 'Anchoring', 'item_code' => 'CP-MOVE']);
        $participant = $this->registerParticipant($f['event'], $item, $f['school']->id, 901);
        $certificate = Certificate::create(['entity_type' => FestParticipant::class, 'entity_id' => $participant->id, 'cert_type' => 'winner', 'verification_uuid' => (string) Str::uuid(), 'generated_at' => now()]);
        $service = app(FestCertificateService::class);
        $before = $service->renderContext($certificate);
        $url = route('sahodaya.events.certificates.phases', ['tenantId' => $f['sahodaya']->id, 'event' => $f['event']->id]);
        foreach (['First Venue', 'Second Venue'] as $venue) {
            $this->actingAs($f['admin'])->post($url, ['name' => $venue, 'venue' => $venue, 'date_start' => '2026-10-17', 'date_end' => '2026-10-18', 'item_ids' => [$item->id]])->assertSessionHasNoErrors();
            $context = $service->renderContext($certificate);
            $this->assertSame($venue, $context['fieldValues']['venue']);
            $this->assertSame('17th - 18th October 2026', $context['fieldValues']['event_dates']);
            $this->assertSame($f['school']->name, $context['fieldValues']['school_name']);
            $this->assertNotSame($service->contentHash($before), $service->contentHash($context));
        }
    }

}
