<?php

namespace Tests\Feature\Public;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestResult;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestPublicScoreboardTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $sahodaya;

    private FestEvent $hub;

    private FestEvent $north;

    private FestEvent $south;

    private Tenant $northSchool;

    private Tenant $southSchool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Public Scoreboard Sahodaya',
            'domain' => 'public-scoreboard.test',
            'is_active' => true,
        ]);

        $this->northSchool = $this->school('North Star School');
        $this->southSchool = $this->school('South Valley School');

        $this->hub = FestEvent::create([
            'tenant_id' => $this->sahodaya->id,
            'title' => 'Regional Arts Fest',
            'event_type' => 'kalotsav',
            'conduct_mode' => 'partitioned',
            'combine_regions_at_finale' => true,
            'aggregation_config' => [
                'include_roles' => ['region'],
                'method' => 'sum_points',
                'overall_label' => 'Overall Championship',
            ],
            'status' => 'completed',
            'results_published' => true,
            'schedule_published' => true,
        ]);

        $this->north = $this->partition('north', 'North Region');
        $this->south = $this->partition('south', 'South Region');

        FestResult::create([
            'event_id' => $this->north->id,
            'school_id' => $this->northSchool->id,
            'total_points' => 60,
            'rank' => 1,
            'published_at' => now(),
        ]);

        FestResult::create([
            'event_id' => $this->south->id,
            'school_id' => $this->southSchool->id,
            'total_points' => 45,
            'rank' => 1,
            'published_at' => now(),
        ]);
    }

    public function test_public_index_lists_operational_children_and_hides_administrative_hub(): void
    {
        $response = $this->get('http://public-scoreboard.test/fest');

        $response->assertOk();
        $response->assertSee('Regional Arts Fest — North Region');
        $response->assertSee('Regional Arts Fest — South Region');
        $response->assertSee("/fest/{$this->north->id}", false);
        $response->assertSee("/fest/{$this->south->id}", false);
        $response->assertDontSee("href=\"http://public-scoreboard.test/fest/{$this->hub->id}\"", false);
    }

    public function test_public_index_orders_events_by_display_order_then_date(): void
    {
        FestEvent::create([
            'tenant_id' => $this->sahodaya->id,
            'title' => 'Late Event',
            'event_type' => 'kalotsav',
            'conduct_mode' => 'standard',
            'status' => 'published',
            'event_start' => '2026-09-25',
            'sort_order' => 10,
        ]);

        FestEvent::create([
            'tenant_id' => $this->sahodaya->id,
            'title' => 'Early Event',
            'event_type' => 'kalotsav',
            'conduct_mode' => 'standard',
            'status' => 'published',
            'event_start' => '2026-09-05',
            'sort_order' => 10,
        ]);

        $this->get('http://public-scoreboard.test/fest')
            ->assertOk()
            ->assertSeeInOrder(['Early Event', 'Late Event']);
    }

    public function test_each_operational_event_has_dedicated_pages_without_region_navigation(): void
    {
        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}");

        $response->assertOk();
        $response->assertSee('Regional Arts Fest — North Region');
        $response->assertDontSee('South Region');
        $response->assertSee("/fest/{$this->north->id}/schedule", false);
        $response->assertSee("/fest/{$this->north->id}/scoreboard", false);
        $response->assertSee("/fest/{$this->north->id}/results", false);
        $response->assertSee("/fest/{$this->north->id}/live", false);
        $response->assertDontSee('aria-label="Event scoreboard scope"', false);
    }

    public function test_standard_event_has_its_own_hub_schedule_scoreboard_results_and_live_pages(): void
    {
        $event = FestEvent::create([
            'tenant_id' => $this->sahodaya->id,
            'title' => 'Standard Science Fest',
            'event_type' => 'science_fest',
            'conduct_mode' => 'standard',
            'status' => 'completed',
            'schedule_published' => true,
            'results_published' => true,
        ]);

        FestResult::create([
            'event_id' => $event->id,
            'school_id' => $this->northSchool->id,
            'total_points' => 25,
            'rank' => 1,
            'published_at' => now(),
        ]);

        $this->get("http://public-scoreboard.test/fest/{$event->id}")
            ->assertOk()
            ->assertSee('Standard Science Fest');
        $this->get("http://public-scoreboard.test/fest/{$event->id}/schedule")->assertOk();
        $this->get("http://public-scoreboard.test/fest/{$event->id}/scoreboard")
            ->assertOk()
            ->assertSee('North Star School')
            ->assertSee('25');
        $this->get("http://public-scoreboard.test/fest/{$event->id}/results")->assertOk();
        $this->get("http://public-scoreboard.test/fest/{$event->id}/live")->assertOk();
    }

    public function test_region_event_scoreboards_are_isolated_and_hub_is_not_public(): void
    {
        $region = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/scoreboard");

        $region->assertOk();
        $region->assertSee('North Star School');
        $region->assertDontSee('South Valley School');
        $region->assertSee('60');

        $this->get("http://public-scoreboard.test/fest/{$this->hub->id}/scoreboard")
            ->assertNotFound();
    }

    public function test_region_and_category_filters_work_together(): void
    {
        $this->markCategoryWinner($this->north, $this->northSchool, 'North HS Winner');
        $this->markCategoryWinner($this->south, $this->southSchool, 'South HS Winner');

        $response = $this->get(
            "http://public-scoreboard.test/fest/{$this->north->id}/scoreboard?category=hs"
        );

        $response->assertOk();
        $response->assertSee('North Region');
        $response->assertSee('Classes 8, 9 &amp; 10', false);
        $response->assertSee('North Star School');
        $response->assertSee('8');
        $response->assertDontSee('South Valley School');
        $response->assertDontSee('South HS Winner');
    }

    public function test_item_results_cannot_cross_the_operational_event_boundary(): void
    {
        $northItem = $this->markCategoryWinner($this->north, $this->northSchool, 'North Poetry');
        $southItem = $this->markCategoryWinner($this->south, $this->southSchool, 'South Poetry');

        $this->get("http://public-scoreboard.test/fest/{$this->north->id}")
            ->assertOk()
            ->assertSee('North Poetry')
            ->assertDontSee('South Poetry');

        $this->get("http://public-scoreboard.test/fest/{$this->north->id}/items/{$northItem->id}/results")
            ->assertOk()
            ->assertSee('North Poetry')
            ->assertSee('NORTH STAR SCHOOL')
            ->assertDontSee('South Valley School');

        $this->get("http://public-scoreboard.test/fest/{$this->north->id}/items/{$southItem->id}/results")
            ->assertNotFound();
    }

    public function test_direct_partition_page_is_the_canonical_standalone_event(): void
    {
        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/scoreboard");

        $response->assertOk();
        $response->assertSee('Regional Arts Fest — North Region');
        $response->assertSee('North Star School');
        $response->assertDontSee('South Valley School');
    }

    public function test_legacy_scope_query_cannot_switch_a_standalone_event(): void
    {
        $response = $this->get(
            "http://public-scoreboard.test/fest/{$this->north->id}/scoreboard?scope=partition:south&cluster=south"
        );

        $response->assertOk();
        $response->assertSee('North Star School');
        $response->assertDontSee('South Valley School');
    }

    public function test_published_child_results_are_independent_of_root_publication(): void
    {
        $this->hub->update(['results_published' => false, 'status' => 'ongoing']);

        $this->get("http://public-scoreboard.test/fest/{$this->north->id}/scoreboard")
            ->assertOk()
            ->assertSee('North Star School');

        $this->get("http://public-scoreboard.test/fest/{$this->north->id}/results")
            ->assertOk();
    }

    public function test_unpublished_standings_do_not_leak_through_live_json(): void
    {
        $this->north->update(['results_published' => false, 'status' => 'ongoing']);

        $response = $this->getJson("http://public-scoreboard.test/fest/{$this->north->id}/live/data");

        $response->assertOk()
            ->assertJsonPath('standingsPublished', false)
            ->assertJsonCount(0, 'scoreboard');

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_unpublished_child_does_not_leak_when_hub_is_published(): void
    {
        $this->north->update(['results_published' => false, 'status' => 'ongoing']);

        $scoreboard = $this->get(
            "http://public-scoreboard.test/fest/{$this->north->id}/scoreboard"
        );
        $scoreboard->assertOk();
        $scoreboard->assertSee('Official Standings Not Published Yet');
        $scoreboard->assertDontSee('North Star School');

        $this->get("http://public-scoreboard.test/fest/{$this->north->id}/results")
            ->assertNotFound();

        $this->getJson("http://public-scoreboard.test/fest/{$this->north->id}/live/data")
            ->assertOk()
            ->assertJsonPath('standingsPublished', false)
            ->assertJsonCount(0, 'scoreboard');
    }

    public function test_scoreboard_page_has_event_day_cache_policy(): void
    {
        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/scoreboard");

        $response->assertOk();
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
    }

    public function test_catalogue_has_search_and_status_discovery_without_phase_navigation(): void
    {
        $response = $this->get('http://public-scoreboard.test/fest');

        $response->assertOk()
            ->assertSee('Search event, venue, phase or region')
            ->assertSee('Live &amp; Open', false)
            ->assertSee('Completed')
            ->assertSee('data-event-card', false)
            ->assertDontSee('Event scoreboard scope');
    }

    public function test_event_page_exposes_item_finder_and_recent_result_entry_points(): void
    {
        $this->markCategoryWinner($this->north, $this->northSchool, 'North Poetry');

        $this->get("http://public-scoreboard.test/fest/{$this->north->id}")
            ->assertOk()
            ->assertSee('Event item finder')
            ->assertSee('Search schedules and results')
            ->assertSee('Search item name or head')
            ->assertSee('Latest results')
            ->assertSee('Topper Highlights');
    }

    public function test_results_offer_dedicated_topper_modules_and_item_filters(): void
    {
        $this->markCategoryWinner($this->north, $this->northSchool, 'North Poetry');

        $this->get("http://public-scoreboard.test/fest/{$this->north->id}/results?tab=toppers")
            ->assertOk()
            ->assertSee('School Overall Toppers')
            ->assertSee('School Category-wise Toppers')
            ->assertSee('Student Category-wise Toppers');

        $this->get("http://public-scoreboard.test/fest/{$this->north->id}/results?tab=item")
            ->assertOk()
            ->assertSee('Search event item')
            ->assertSee('All categories')
            ->assertSee('All stages')
            ->assertSee('data-result-item', false);
    }

    public function test_scoreboard_refreshes_a_partial_without_reloading_the_page(): void
    {
        $page = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/scoreboard");

        $page->assertOk()
            ->assertSee('Updates in the background every 30 seconds')
            ->assertDontSee('window.location.reload', false);

        $data = $this->getJson("http://public-scoreboard.test/fest/{$this->north->id}/scoreboard/data");
        $data->assertOk()
            ->assertJsonPath('standingsPublished', true)
            ->assertJsonStructure(['contentHtml', 'refreshedAt']);
        $this->assertStringContainsString('North Star School', $data->json('contentHtml'));
        $this->assertStringContainsString('no-store', $data->headers->get('Cache-Control'));
    }

    private function school(string $name): Tenant
    {
        return Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => $name,
            'parent_id' => $this->sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);
    }

    private function partition(string $key, string $label): FestEvent
    {
        return FestEvent::create([
            'tenant_id' => $this->sahodaya->id,
            'title' => $label,
            'event_type' => 'kalotsav',
            'parent_event_id' => $this->hub->id,
            'partition_key' => $key,
            'cluster_label' => $label,
            'partition_role' => 'region',
            'status' => 'completed',
            'schedule_published' => true,
            'results_published' => true,
        ]);
    }

    private function markCategoryWinner(FestEvent $event, Tenant $school, string $title): FestEventItem
    {
        $item = FestEventItem::create([
            'event_id' => $event->id,
            'title' => $title,
            'category' => 'literary',
            'class_group' => 'hs',
            'participant_type' => 'individual',
            'is_enabled' => true,
        ]);

        $registration = FestRegistration::create([
            'event_id' => $event->id,
            'item_id' => $item->id,
            'school_id' => $school->id,
            'status' => 'approved',
        ]);

        $participant = FestParticipant::create([
            'registration_id' => $registration->id,
            'event_id' => $event->id,
            'participant_type' => 'student',
        ]);

        FestMark::create([
            'event_id' => $event->id,
            'item_id' => $item->id,
            'participant_id' => $participant->id,
            'grade' => 'A',
            'position' => 1,
            'score' => 80,
        ]);

        return $item;
    }
}
