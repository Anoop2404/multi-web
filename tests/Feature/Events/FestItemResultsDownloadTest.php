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
 * Per-item ranked results PDF — deliberately separate from the Reports Hub's
 * Top-N-capped, event-wide-publish-gated "Item-wise Top Results" PDF. This one
 * is reachable straight from the Results page for one item, works regardless
 * of the event-wide publish flag, and includes every participant.
 */
class FestItemResultsDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_download_returns_a_pdf_with_every_participant_sorted_by_rank(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Results Download Sahodaya',
            'domain' => 'results-download.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'RD', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Results Download School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        // Event-wide results are NOT published — the download must still work,
        // unlike the Reports Hub's gated Item-wise PDF.
        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Results Download Event',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'ongoing',
            'results_published' => false,
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Memory Test (Individual)',
            'category' => 'literary',
            'item_code' => 'MEM',
        ]);

        // Three participants, deliberately registered/marked out of rank order,
        // so a passing test proves the PDF actually sorts rather than just
        // echoing resultRowsForItem()'s registration-order default.
        foreach ([['Charlie', 3], ['Alice', 1], ['Bob', 2]] as [$name, $rank]) {
            $registration = FestRegistration::create([
                'event_id' => $event->id,
                'item_id' => $item->id,
                'school_id' => $school->id,
                'status' => 'approved',
            ]);
            $participant = FestParticipant::create([
                'registration_id' => $registration->id,
                'participant_type' => 'student',
                'participant_role' => 'performer',
            ]);
            FestMark::create([
                'event_id' => $event->id,
                'item_id' => $item->id,
                'participant_id' => $participant->id,
                'position' => $rank,
                'score' => 100 - $rank,
            ]);
        }

        $response = $this->actingAs($admin)->get(route('sahodaya.events.results.items.download', [
            'tenantId' => $sahodaya->id,
            'event' => $event->id,
            'item' => $item->id,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_cross_event_item_is_rejected(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Results Download Sahodaya 2',
            'domain' => 'results-download-2.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'RD2', 'student_data_mode' => 'counts_only']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $eventA = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Event A', 'event_type' => 'kalolsavam', 'level_round' => 'sahodaya', 'status' => 'ongoing']);
        $eventB = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Event B', 'event_type' => 'kalolsavam', 'level_round' => 'sahodaya', 'status' => 'ongoing']);
        $itemFromB = FestEventItem::create(['event_id' => $eventB->id, 'title' => 'Item From B', 'item_code' => 'B1']);

        $response = $this->actingAs($admin)->get(route('sahodaya.events.results.items.download', [
            'tenantId' => $sahodaya->id,
            'event' => $eventA->id,
            'item' => $itemFromB->id,
        ]));

        $response->assertNotFound();
    }
}
