<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestGroup;
use App\Models\FestMark;
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
 * The certificate tally page's "Medal tally" download: gold / silver / bronze medals
 * actually handed out per item — one per person, so every member of a placed team gets
 * one (a rank count would count that team once), standbys never do — portrait, with
 * no participation columns.
 */
class FestCertificateTallyMedalReportTest extends TestCase
{
    use RefreshDatabase;

    private int $nextStudentId = 1;

    /** @return array{admin: User, event: FestEvent} */
    private function makeEventWithResults(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Medal Tally Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'MT', 'student_data_mode' => 'counts_only']);
        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id, 'name' => 'Medal Tally School',
            'domain' => Str::uuid().'.test', 'membership_status' => 'approved', 'is_active' => true,
        ]);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Medal Tally Fest', 'event_type' => 'kalolsavam']);

        // Individual item: 1st, 2nd, 3rd and one unplaced entry.
        $solo = FestEventItem::create(['event_id' => $event->id, 'title' => 'Digital Painting', 'item_code' => 'DP1', 'class_group' => 'category_1', 'participant_type' => 'individual']);
        foreach ([1, 2, 3, null] as $position) {
            $registration = $this->registration($event, $solo, $school);
            $this->member($event, $solo, $registration, null, $position);
        }

        // Team item: team A (3 performers + a standby who also has a mark) wins 1st,
        // team B (2 performers) 2nd.
        $group = FestEventItem::create(['event_id' => $event->id, 'title' => 'Group Song', 'item_code' => 'GS1', 'class_group' => 'category_1', 'participant_type' => 'team']);
        foreach ([[1, 3, true], [2, 2, false]] as [$position, $performers, $withStandby]) {
            $registration = $this->registration($event, $group, $school);
            $team = FestGroup::create(['registration_id' => $registration->id, 'event_id' => $event->id, 'team_name' => 'Team '.$position]);
            foreach (range(1, $performers) as $i) {
                $this->member($event, $group, $registration, $team, $position);
            }
            if ($withStandby) {
                $this->member($event, $group, $registration, $team, $position, 'standby');
            }
        }

        return ['admin' => $admin, 'event' => $event];
    }

    private function registration(FestEvent $event, FestEventItem $item, Tenant $school): FestRegistration
    {
        return FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved', 'submitted_at' => now(),
        ]);
    }

    private function member(FestEvent $event, FestEventItem $item, FestRegistration $registration, ?FestGroup $team, ?int $position, string $role = 'performer'): void
    {
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'group_id' => $team?->id, 'event_id' => $event->id,
            'student_id' => $this->nextStudentId++, 'participant_type' => 'student', 'participant_role' => $role,
        ]);
        if ($position !== null) {
            FestMark::create([
                'event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id,
                'grade' => 'A', 'position' => $position, 'score' => 90,
            ]);
        }
    }

    public function test_medals_count_every_placed_team_member_but_never_a_standby(): void
    {
        ['event' => $event] = $this->makeEventWithResults();
        $tally = app(FestCertificateService::class)->certificateTally($event);
        $rows = collect($tally['rows'])->keyBy('title');

        $this->assertSame([1, 1, 1], [$rows['Digital Painting']['medals_1'], $rows['Digital Painting']['medals_2'], $rows['Digital Painting']['medals_3']]);

        // Team item: the winning team is ONE rank-1 entry but THREE gold medals — its
        // standby, though marked, gets none.
        $this->assertSame(1, $rows['Group Song']['rank_1']);
        $this->assertSame(3, $rows['Group Song']['medals_1']);
        $this->assertSame(2, $rows['Group Song']['medals_2']);
        $this->assertSame(0, $rows['Group Song']['medals_3']);

        $this->assertSame(4, $tally['totals']['medals_1']);
        $this->assertSame(3, $tally['totals']['medals_2']);
        $this->assertSame(1, $tally['totals']['medals_3']);
        $this->assertSame(8, $tally['totals']['medals_total']);
        $this->assertSame(8, $tally['summary'][0]['medals_total']);
    }

    public function test_excel_medal_tally_has_medal_columns_and_no_participation(): void
    {
        ['admin' => $admin, 'event' => $event] = $this->makeEventWithResults();

        $response = $this->actingAs($admin)->get(route('sahodaya.events.certificates.tally.medal-report', [
            'tenantId' => $event->tenant_id, 'event' => $event->id, 'format' => 'xls',
        ]));

        $response->assertOk();
        $xml = $response->streamedContent();

        $this->assertStringContainsString('medal-tally', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Worksheet ss:Name="Medal tally"', $xml);
        $this->assertStringContainsString('Worksheet ss:Name="By category"', $xml);
        $this->assertStringContainsString('Group Song (Team)', $xml);
        foreach (['Gold', 'Silver', 'Bronze', 'Total medals'] as $header) {
            $this->assertStringContainsString($header, $xml);
        }
        $this->assertStringNotContainsStringIgnoringCase('participation', $xml);
        $this->assertStringNotContainsString('Rank 1', $xml);
    }

    public function test_pdf_is_a_simple_portrait_medal_list(): void
    {
        config(['services.pdf_converter.url' => null]);
        ['admin' => $admin, 'event' => $event] = $this->makeEventWithResults();

        $response = $this->actingAs($admin)->get(route('sahodaya.events.certificates.tally.medal-report', [
            'tenantId' => $event->tenant_id, 'event' => $event->id, 'format' => 'pdf',
        ]));
        $response->assertOk();
        $this->assertStringContainsString('medal-tally', (string) $response->headers->get('Content-Disposition'));

        $tally = app(FestCertificateService::class)->certificateTally($event);
        $html = view('fest.reports.item-medal-tally', [
            'event' => $event,
            'orgName' => 'Medal Tally Sahodaya',
            'rows' => collect($tally['rows'])->values(),
            'totals' => $tally['totals'],
        ])->render();

        $this->assertStringContainsString('size: A4 portrait', $html);
        $this->assertStringContainsString('Group Song', $html);
        $this->assertStringContainsString(e($tally['rows'][0]['category_label']), $html);
        $this->assertStringContainsString('Total (2 items)', $html);
        $this->assertStringNotContainsString('Rank 1', $html);
        $this->assertStringNotContainsStringIgnoringCase('participation', $html);
    }

    /**
     * File names carry the fest and the phase/region leg: the Sahodaya's name (same on
     * every file) is dropped, and a leg's own name gets its own segment.
     */
    public function test_download_file_name_carries_the_fest_and_leg_names(): void
    {
        ['admin' => $admin, 'event' => $root] = $this->makeEventWithResults();
        $root->update(['title' => 'Medal Tally Sahodaya Kalotsavam 2026-27', 'event_start' => '2026-09-19']);
        $leg = FestEvent::create([
            'tenant_id' => $root->tenant_id, 'parent_event_id' => $root->id, 'event_type' => 'kalolsavam',
            'title' => 'Medal Tally Sahodaya Kalotsavam 2026-27 — Sargadhara — Tirur Region', 'event_start' => '2026-09-19',
        ]);

        $legFile = (string) $this->actingAs($admin)->get(route('sahodaya.events.certificates.tally.medal-report', [
            'tenantId' => $root->tenant_id, 'event' => $leg->id, 'format' => 'xls',
        ]))->assertOk()->headers->get('Content-Disposition');
        $this->assertStringContainsString('medal-tally_kalotsavam-2026-27_sargadhara-tirur-region_2026-09-19.xls', $legFile);

        $rootFile = (string) $this->actingAs($admin)->get(route('sahodaya.events.certificates.tally.medal-report', [
            'tenantId' => $root->tenant_id, 'event' => $root->id, 'format' => 'xls',
        ]))->assertOk()->headers->get('Content-Disposition');
        $this->assertStringContainsString('medal-tally_kalotsavam-2026-27_2026-09-19.xls', $rootFile);
    }
}
