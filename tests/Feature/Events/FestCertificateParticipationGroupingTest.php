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
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the "Participation (by School)" tab — FestCertificateController::
 * groupCertificatesBySchool() shared with the merit winnersBySchool() via a $certType
 * param. Guards against it silently regressing to winner-only (the
 * ($c['cert_type'] ?? null) === $certType filter must actually vary per call site),
 * confirms is_rendered/is_stale reach the grouped output, and that a participation row
 * lists every item on the student's one certificate — there is no per-item
 * participation grouping, since the certificate is per student, handed out per school.
 */
class FestCertificateParticipationGroupingTest extends TestCase
{
    use RefreshDatabase;

    private function makeSahodayaAdminEventAndSchool(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Participation Grouping Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'PG', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Participation Grouping School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Participation Grouping Event', 'event_type' => 'kalolsavam',
        ]);

        return compact('sahodaya', 'admin', 'event', 'school');
    }

    private function makeCertificate(FestEvent $event, FestEventItem $item, string $schoolId, int $studentId, string $certType, ?string $filePath = null): Certificate
    {
        $registration = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $schoolId, 'status' => 'approved',
        ]);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $event->id, 'student_id' => $studentId,
            'participant_type' => 'student', 'participant_role' => 'performer',
        ]);

        return Certificate::create([
            'entity_type'        => FestParticipant::class,
            'entity_id'          => $participant->id,
            'cert_type'          => $certType,
            'verification_uuid'  => (string) Str::uuid(),
            'generated_at'       => now(),
            'file_path'          => $filePath,
        ]);
    }

    public function test_participation_groups_by_school_independently_of_winners(): void
    {
        ['admin' => $admin, 'event' => $event, 'school' => $school] = $this->makeSahodayaAdminEventAndSchool();

        $itemA = FestEventItem::create(['event_id' => $event->id, 'title' => 'Item A', 'item_code' => 'IA1']);
        $itemB = FestEventItem::create(['event_id' => $event->id, 'title' => 'Item B', 'item_code' => 'IB1']);

        $this->makeCertificate($event, $itemA, $school->id, 401, 'participation');
        $this->makeCertificate($event, $itemA, $school->id, 402, 'participation');
        $this->makeCertificate($event, $itemB, $school->id, 403, 'participation');
        // A winner certificate for the same item must not leak into the participation grouping.
        $this->makeCertificate($event, $itemA, $school->id, 404, 'winner');

        $response = $this->actingAs($admin)->get(route('sahodaya.events.certificates.index', [
            'tenantId' => $event->tenant_id, 'event' => $event->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->missing('participationByItem')
            ->has('participationBySchool', 1)
            ->where('participationBySchool.0.winners', fn ($winners) => count($winners) === 3)
            ->has('winnersByItem', 1)
        );
    }

    public function test_grouped_participation_entries_carry_is_rendered_and_is_stale(): void
    {
        ['admin' => $admin, 'event' => $event, 'school' => $school] = $this->makeSahodayaAdminEventAndSchool();
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Rendered Item', 'item_code' => 'RI1']);

        $this->makeCertificate($event, $item, $school->id, 501, 'participation', filePath: 'certificates/x/501.pdf');
        $this->makeCertificate($event, $item, $school->id, 502, 'participation', filePath: null);

        $response = $this->actingAs($admin)->get(route('sahodaya.events.certificates.index', [
            'tenantId' => $event->tenant_id, 'event' => $event->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('participationBySchool', 1)
            ->where('participationBySchool.0.winners', function ($winners) {
                $winners = collect($winners);

                return $winners->contains(fn ($w) => $w['is_rendered'] === true)
                    && $winners->contains(fn ($w) => $w['is_rendered'] === false);
            })
        );
    }

    public function test_participation_row_lists_every_item_on_the_students_one_certificate(): void
    {
        ['admin' => $admin, 'event' => $event, 'school' => $school] = $this->makeSahodayaAdminEventAndSchool();
        $itemA = FestEventItem::create(['event_id' => $event->id, 'title' => 'Recitation', 'item_code' => 'RC1', 'display_order' => 1]);
        $itemB = FestEventItem::create(['event_id' => $event->id, 'title' => 'Group Song', 'item_code' => 'GS1', 'display_order' => 2]);

        // One certificate, anchored to the student's Recitation row...
        $this->makeCertificate($event, $itemA, $school->id, 601, 'participation');
        // ...but they also entered Group Song, which the certificate prints too.
        $registration = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $itemB->id, 'school_id' => $school->id, 'status' => 'approved',
        ]);
        FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $event->id, 'student_id' => 601,
            'participant_type' => 'student', 'participant_role' => 'performer',
        ]);

        $response = $this->actingAs($admin)->get(route('sahodaya.events.certificates.index', [
            'tenantId' => $event->tenant_id, 'event' => $event->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('participationBySchool', 1)
            ->where('participationBySchool.0.winners', function ($winners) {
                $winners = collect($winners);

                return $winners->count() === 1
                    && collect($winners->first()['items'])->pluck('title')->all() === ['Recitation', 'Group Song'];
            })
        );
    }

    /**
     * School ids are tenant UUIDs; print-all/ZIP/render used to (int)-cast them, so a
     * letter-first id became 0 (filter dropped — the whole event printed) and a digit-
     * first one became its leading number (no school, or on MySQL every school whose id
     * starts with that digit).
     */
    public function test_print_all_scopes_to_the_one_school_for_letter_and_digit_first_uuids(): void
    {
        ['admin' => $admin, 'event' => $event, 'sahodaya' => $sahodaya] = $this->makeSahodayaAdminEventAndSchool();
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'item_code' => 'SS1']);

        $schools = collect(['ab240f41-6cbd-41e7-8be4-d6bf465df0d3', '6b240f41-6cbd-41e7-8be4-d6bf465df0d3', '6c000000-0000-4000-8000-000000000000'])
            ->map(fn (string $id) => Tenant::create([
                'id' => $id, 'type' => 'school', 'parent_id' => $sahodaya->id, 'name' => 'School '.$id,
                'domain' => Str::uuid().'.test', 'membership_status' => 'approved', 'is_active' => true,
            ]));

        foreach ($schools as $index => $school) {
            $this->makeCertificate($event, $item, $school->id, 700 + $index, 'participation');
        }

        foreach ($schools->take(2) as $index => $school) {
            $response = $this->actingAs($admin)->get(route('sahodaya.events.certificates.print-all', [
                'tenantId' => $event->tenant_id, 'event' => $event->id,
                'school_id' => $school->id, 'cert_type' => 'participation', 'plain' => 1,
            ]));

            $response->assertOk();
            // One printed page per certificate: each sheet sits on a named page of its
            // template's size, clipped 1mm short of it (a sheet exactly one page tall
            // spilled into a blank page after every certificate).
            $html = $response->getContent();
            $this->assertStringContainsString('@page cert-2970x2100 { size: 297mm 210mm; margin: 0; }', $html);
            $this->assertStringContainsString('.cert-sheet.cert-2970x2100 { height: 209mm; }', $html);
            $this->assertSame(1, substr_count($html, 'class="cert-sheet cert-2970x2100"'));
            $printed = $response->viewData('certificates');
            $this->assertCount(1, $printed, "Only {$school->id}'s certificate should print.");
            $this->assertSame(700 + $index, FestParticipant::find($printed->first()['certificate']->entity_id)->student_id);
        }
    }
}
