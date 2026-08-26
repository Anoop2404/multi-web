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
 * Covers the new "Participation (Grouped by Item)"/"Grouped by School" tabs —
 * FestCertificateController::groupCertificatesByItem()/groupCertificatesBySchool()
 * generalized from the merit-only winnersByItem()/winnersBySchool() to accept a
 * $certType param. Guards against the generalized methods silently regressing to
 * winner-only (the ($c['cert_type'] ?? null) === $certType filter must actually vary
 * per call site) and confirms is_rendered/is_stale — needed for gating the new
 * View/Download PDF links — actually reach the grouped output, not just the flat list.
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

    public function test_participation_by_item_and_by_school_group_independently_of_winners(): void
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
            ->has('participationByItem', 2)
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
            ->has('participationByItem', 1)
            ->where('participationByItem.0.winners', function ($winners) {
                $winners = collect($winners);

                return $winners->contains(fn ($w) => $w['is_rendered'] === true)
                    && $winners->contains(fn ($w) => $w['is_rendered'] === false);
            })
        );
    }
}
