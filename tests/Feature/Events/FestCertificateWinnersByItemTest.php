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
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the "Winners by item" grouped section and the "published winners" ZIP
 * filter on the Sahodaya Certificates page — both must only surface an item's
 * winners once that item's results are actually published (its own
 * results_published_at, OR the whole event's results_published flag), since a
 * winner Certificate row can already exist before either flag is set
 * (FestCertificateService::generateForEvent() doesn't itself gate on publish state).
 */
class FestCertificateWinnersByItemTest extends TestCase
{
    use RefreshDatabase;

    private function makeSahodaya(): Tenant
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Winners By Item Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'WI', 'student_data_mode' => 'counts_only']);

        return $sahodaya;
    }

    private function makeSchool(string $sahodayaId): Tenant
    {
        return Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodayaId,
            'name' => 'Winners By Item School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);
    }

    private function makeWinner(FestEvent $event, FestEventItem $item, string $schoolId, int $position, int $studentId): Certificate
    {
        $registration = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $schoolId, 'status' => 'approved',
        ]);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $event->id, 'student_id' => $studentId,
            'participant_type' => 'student', 'participant_role' => 'performer',
        ]);
        FestMark::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id,
            'position' => $position,
        ]);

        return Certificate::create([
            'entity_type'        => FestParticipant::class,
            'entity_id'          => $participant->id,
            'cert_type'          => 'winner',
            'verification_uuid'  => (string) Str::uuid(),
            'generated_at'       => now(),
        ]);
    }

    public function test_winners_by_item_excludes_items_whose_results_are_not_published(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $sahodaya = $this->makeSahodaya();
        $school = $this->makeSchool($sahodaya->id);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Winners Event', 'event_type' => 'kalolsavam',
            'results_published' => false,
        ]);
        $publishedItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Published Item', 'item_code' => 'PI1',
            'results_published_at' => now(),
        ]);
        $unpublishedItem = FestEventItem::create(['event_id' => $event->id, 'title' => 'Unpublished Item', 'item_code' => 'UI1']);

        $this->makeWinner($event, $publishedItem, $school->id, 1, 101);
        $this->makeWinner($event, $unpublishedItem, $school->id, 1, 102);

        $response = $this->actingAs($admin)->get(route('sahodaya.events.certificates.index', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('winnersByItem', 2)
        );
    }

    public function test_event_wide_publish_flag_makes_every_items_winners_visible(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $sahodaya = $this->makeSahodaya();
        $school = $this->makeSchool($sahodaya->id);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        // Whole-event flag true, neither item has its own results_published_at set —
        // FestEvent::results_published cascades to every item (see
        // FestItemResultsService::isItemVisible()).
        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Cascaded Publish Event', 'event_type' => 'kalolsavam',
            'results_published' => true,
        ]);
        $itemA = FestEventItem::create(['event_id' => $event->id, 'title' => 'Item A', 'item_code' => 'IA1']);
        $itemB = FestEventItem::create(['event_id' => $event->id, 'title' => 'Item B', 'item_code' => 'IB1']);

        $this->makeWinner($event, $itemA, $school->id, 1, 201);
        $this->makeWinner($event, $itemB, $school->id, 1, 202);

        $response = $this->actingAs($admin)->get(route('sahodaya.events.certificates.index', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('winnersByItem', 2));
    }

    public function test_download_zip_published_only_filters_to_published_winner_certificates(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $sahodaya = $this->makeSahodaya();
        $school = $this->makeSchool($sahodaya->id);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Published Zip Event', 'event_type' => 'kalolsavam',
            'results_published' => false,
        ]);
        $publishedItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Published Item', 'item_code' => 'PI2',
            'results_published_at' => now(),
        ]);
        $unpublishedItem = FestEventItem::create(['event_id' => $event->id, 'title' => 'Unpublished Item', 'item_code' => 'UI2']);

        $publishedWinner = $this->makeWinner($event, $publishedItem, $school->id, 1, 301);
        $this->makeWinner($event, $unpublishedItem, $school->id, 1, 302);

        $response = $this->actingAs($admin)->get(route('sahodaya.events.certificates.download-zip', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]).'?published_only=1');

        $response->assertOk();

        $zipPath = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);
        $this->assertSame(1, $zip->numFiles);
        $this->assertStringContainsString($publishedWinner->verification_uuid, $zip->getNameIndex(0));
        $zip->close();
    }
}
