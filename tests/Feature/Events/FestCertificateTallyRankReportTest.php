<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
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
 * The certificate tally page's "Rank tally report" download: merit places per item
 * (rank 1 / 2 / 3, winner certificates) and by category — never the participation
 * certificate / participation entry columns the on-screen tally also shows.
 */
class FestCertificateTallyRankReportTest extends TestCase
{
    use RefreshDatabase;

    private int $nextStudentId = 1;

    /** @return array{admin: User, event: FestEvent} */
    private function makeEventWithRankedItem(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Rank Tally Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'RT', 'student_data_mode' => 'counts_only']);
        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id, 'name' => 'Rank Tally School',
            'domain' => Str::uuid().'.test', 'membership_status' => 'approved', 'is_active' => true,
        ]);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Rank Tally Fest', 'event_type' => 'kalolsavam']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Digital Painting', 'item_code' => 'DP1', 'class_group' => 'category_1']);

        // Four entries: 1st, 2nd, 3rd and one unplaced.
        foreach ([1, 2, 3, null] as $position) {
            $registration = FestRegistration::create([
                'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved', 'submitted_at' => now(),
            ]);
            $participant = FestParticipant::create([
                'registration_id' => $registration->id, 'event_id' => $event->id, 'student_id' => $this->nextStudentId++,
                'participant_type' => 'student', 'participant_role' => 'performer',
            ]);
            if ($position !== null) {
                FestMark::create([
                    'event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id,
                    'grade' => 'A', 'position' => $position, 'score' => 90,
                ]);
            }
        }

        return ['admin' => $admin, 'event' => $event];
    }

    public function test_excel_rank_tally_lists_ranks_and_winner_certificates_without_participation(): void
    {
        ['admin' => $admin, 'event' => $event] = $this->makeEventWithRankedItem();

        $response = $this->actingAs($admin)->get(route('sahodaya.events.certificates.tally.rank-report', [
            'tenantId' => $event->tenant_id, 'event' => $event->id, 'format' => 'xls',
        ]));

        $response->assertOk();
        $xml = $response->streamedContent();

        $this->assertStringContainsString('rank-tally', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Worksheet ss:Name="Rank tally"', $xml);
        $this->assertStringContainsString('Worksheet ss:Name="By category"', $xml);
        $this->assertStringContainsString('Digital Painting', $xml);
        $this->assertStringContainsString('Winner certificates', $xml);
        $this->assertStringNotContainsStringIgnoringCase('participation', $xml);
    }

    public function test_pdf_rank_tally_downloads(): void
    {
        config(['services.pdf_converter.url' => null]);
        ['admin' => $admin, 'event' => $event] = $this->makeEventWithRankedItem();

        $response = $this->actingAs($admin)->get(route('sahodaya.events.certificates.tally.rank-report', [
            'tenantId' => $event->tenant_id, 'event' => $event->id, 'format' => 'pdf',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('rank-tally', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_rank_tally_view_shows_ranks_by_item_and_category(): void
    {
        ['event' => $event] = $this->makeEventWithRankedItem();
        $tally = app(\App\Services\Events\FestCertificateService::class)->certificateTally($event);

        $html = view('fest.reports.rank-tally', [
            'event' => $event,
            'orgName' => 'Rank Tally Sahodaya',
            'rows' => collect($tally['rows'])->map(fn ($row) => $row + ['category_label' => 'Category 1'])->values(),
            'summary' => collect($tally['summary'])->map(fn ($row) => ['category' => 'Category 1'] + $row)->values(),
            'totals' => $tally['totals'],
        ])->render();

        $this->assertStringContainsString('Digital Painting', $html);
        $this->assertStringContainsString('Summary — rank 1 / 2 / 3 by category', $html);
        $this->assertSame(1, $tally['rows'][0]['rank_1']);
        $this->assertSame(3, $tally['rows'][0]['winner_certs']);
        $this->assertStringNotContainsStringIgnoringCase('participation', $html);
    }
}
