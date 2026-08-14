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

    public function test_public_index_lists_hub_once_and_hides_partition_children(): void
    {
        $response = $this->get('http://public-scoreboard.test/fest');

        $response->assertOk();
        $response->assertSee('Regional Arts Fest');
        $response->assertDontSee('North Region');
        $response->assertDontSee('South Region');
    }

    public function test_each_hub_has_dedicated_pages_with_region_navigation(): void
    {
        $response = $this->get("http://public-scoreboard.test/fest/{$this->hub->id}");

        $response->assertOk();
        $response->assertSee('Overall Championship');
        $response->assertSee('North Region');
        $response->assertSee('South Region');
        $response->assertSee("/fest/{$this->hub->id}/schedule", false);
        $response->assertSee("/fest/{$this->hub->id}/scoreboard", false);
        $response->assertSee("/fest/{$this->hub->id}/results", false);
        $response->assertSee("/fest/{$this->hub->id}/live", false);
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

    public function test_region_scope_is_isolated_and_overall_scope_is_combined(): void
    {
        $region = $this->get("http://public-scoreboard.test/fest/{$this->hub->id}/scoreboard?scope=partition:north");

        $region->assertOk();
        $region->assertSee('North Star School');
        $region->assertDontSee('South Valley School');
        $region->assertSee('60');

        $overall = $this->get("http://public-scoreboard.test/fest/{$this->hub->id}/scoreboard?scope=overall");

        $overall->assertOk();
        $overall->assertSee('North Star School');
        $overall->assertSee('South Valley School');
        $overall->assertSee('60');
        $overall->assertSee('45');
    }

    public function test_region_and_category_filters_work_together(): void
    {
        $this->markCategoryWinner($this->north, $this->northSchool, 'North HS Winner');
        $this->markCategoryWinner($this->south, $this->southSchool, 'South HS Winner');

        $response = $this->get(
            "http://public-scoreboard.test/fest/{$this->hub->id}/scoreboard?scope=partition:north&category=hs"
        );

        $response->assertOk();
        $response->assertSee('North Region');
        $response->assertSee('Classes 8, 9 &amp; 10', false);
        $response->assertSee('North Star School');
        $response->assertSee('8');
        $response->assertDontSee('South Valley School');
        $response->assertDontSee('South HS Winner');
    }

    public function test_direct_partition_page_redirects_to_canonical_hub_scope(): void
    {
        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/scoreboard");

        $response->assertRedirect();
        $this->assertStringContainsString("/fest/{$this->hub->id}/scoreboard", $response->headers->get('Location'));
        $this->assertStringContainsString('scope=partition%3Anorth', $response->headers->get('Location'));
    }

    public function test_existing_cluster_query_links_remain_compatible(): void
    {
        $response = $this->get(
            "http://public-scoreboard.test/fest/{$this->hub->id}/scoreboard?cluster=north"
        );

        $response->assertOk();
        $response->assertSee('North Star School');
        $response->assertDontSee('South Valley School');
    }

    public function test_invalid_partition_scope_returns_not_found(): void
    {
        $this->get("http://public-scoreboard.test/fest/{$this->hub->id}/scoreboard?scope=partition:unknown")
            ->assertNotFound();
    }

    public function test_unpublished_standings_do_not_leak_through_live_json(): void
    {
        $this->hub->update(['results_published' => false, 'status' => 'ongoing']);

        $response = $this->getJson("http://public-scoreboard.test/fest/{$this->hub->id}/live/data?scope=overall");

        $response->assertOk()
            ->assertJsonPath('standingsPublished', false)
            ->assertJsonCount(0, 'scoreboard');

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_unpublished_child_scope_does_not_leak_when_hub_is_published(): void
    {
        $this->north->update(['results_published' => false, 'status' => 'ongoing']);

        $scoreboard = $this->get(
            "http://public-scoreboard.test/fest/{$this->hub->id}/scoreboard?scope=partition:north"
        );
        $scoreboard->assertOk();
        $scoreboard->assertSee('Standings are not published yet');
        $scoreboard->assertDontSee('North Star School');

        $this->get("http://public-scoreboard.test/fest/{$this->hub->id}/results?scope=partition:north")
            ->assertNotFound();

        $this->getJson("http://public-scoreboard.test/fest/{$this->hub->id}/live/data?scope=partition:north")
            ->assertOk()
            ->assertJsonPath('standingsPublished', false)
            ->assertJsonCount(0, 'scoreboard');
    }

    public function test_scoreboard_page_has_event_day_cache_policy(): void
    {
        $response = $this->get("http://public-scoreboard.test/fest/{$this->hub->id}/scoreboard");

        $response->assertOk();
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
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

    private function markCategoryWinner(FestEvent $event, Tenant $school, string $title): void
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
    }
}
