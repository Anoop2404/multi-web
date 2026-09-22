<?php

namespace Tests\Feature\Public;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestResult;
use App\Models\SchoolClass;
use App\Models\Student;
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
        $this->get("http://public-scoreboard.test/fest/{$event->id}/live")
            ->assertRedirect("http://public-scoreboard.test/fest/{$event->id}/scoreboard");
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

    /**
     * tv_show_overall_standings (Sahodaya admin > Event Settings > Locks & Gates) also
     * gates the Scoreboard page's "All Categories" tab, matching what it already does
     * for the TV screen's Overall Standings slide — category tabs still always show,
     * and a bare /scoreboard request (no ?category=) falls back to the first category
     * instead of the fest-wide combined view.
     */
    public function test_scoreboard_hides_all_categories_tab_and_defaults_to_first_category_when_toggled_off(): void
    {
        $this->markCategoryWinner($this->north, $this->northSchool, 'North HS Winner');
        $this->north->update(['tv_show_overall_standings' => false]);

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/scoreboard");

        $response->assertOk();
        $response->assertDontSee('All Categories');
        $response->assertSee('Classes 8, 9 &amp; 10', false);
        $response->assertSee('North Star School');
    }

    public function test_scoreboard_shows_all_categories_tab_by_default(): void
    {
        $this->markCategoryWinner($this->north, $this->northSchool, 'North HS Winner');

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/scoreboard");

        $response->assertOk();
        $response->assertSee('All Categories');
    }

    /**
     * Client-side category navigation and in-place refresh read these data attributes
     * from #scoreboard-live-root. Browsing stays on the selected category; automatic
     * category rotation is reserved for the dedicated TV page.
     */
    public function test_scoreboard_root_carries_category_navigation_data_attributes(): void
    {
        $this->markCategoryWinner($this->north, $this->northSchool, 'North HS Winner');

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/scoreboard");
        $html = $response->getContent();

        $response->assertOk();
        $this->assertMatchesRegularExpression('/data-categories="\[&quot;&quot;,&quot;hs&quot;\]"/', $html);
        // json_encode() escapes the em dash as — by default (no JSON_UNESCAPED_UNICODE) —
        // that's the literal text in the rendered attribute, not an actual em dash character.
        $this->assertStringContainsString('data-category-labels="{&quot;hs&quot;:&quot;Category 3 \u2014 Classes 8, 9 &amp; 10&quot;}"', $html);
        $this->assertStringContainsString('data-base-label="Regional Arts Fest — North Region"', $html);
        $this->assertStringContainsString('data-initial-category=""', $html);
    }

    public function test_scoreboard_root_omits_all_categories_from_rotation_data_when_toggled_off(): void
    {
        $this->markCategoryWinner($this->north, $this->northSchool, 'North HS Winner');
        $this->north->update(['tv_show_overall_standings' => false]);

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/scoreboard");
        $html = $response->getContent();

        $response->assertOk();
        $this->assertMatchesRegularExpression('/data-categories="\[&quot;hs&quot;\]"/', $html);
        $this->assertStringContainsString('data-initial-category="hs"', $html);
    }

    /**
     * Regression test for a real production gap: PublicFestScoreboardService::
     * scoreboard()'s category branch (used by the scoreboard's category filter, and
     * by the Category-wise/Toppers tabs on the results page) summed every FestMark in
     * the category with no regard for whether the contributing item had ever
     * published its own results — so once the WHOLE EVENT's results_published flag
     * flipped true, a school could get public credit for an item nobody had
     * individually published, even while that same event's Item-wise tab correctly
     * showed it as unpublished. provisionalScoreboard() (used only pre-event-publish)
     * already had the correct gate; the official/published path didn't.
     */
    public function test_category_scoreboard_excludes_a_school_whose_only_win_is_an_unpublished_item(): void
    {
        // $this->north already has results_published => true (the event-level flag) —
        // the exact condition under which the buggy path used to run unguarded.
        $this->markCategoryWinner($this->north, $this->northSchool, 'North HS Winner');

        $unpublishedItem = FestEventItem::create([
            'event_id' => $this->north->id, 'title' => 'Never Published HS Item', 'category' => 'literary',
            'class_group' => 'hs', 'participant_type' => 'individual', 'is_enabled' => true,
            'results_published_at' => null,
        ]);
        $this->markItemWinner($this->north, $unpublishedItem, $this->southSchool);

        $response = $this->get(
            "http://public-scoreboard.test/fest/{$this->north->id}/scoreboard?category=hs"
        );

        $response->assertOk();
        $response->assertSee('North Star School');
        $response->assertDontSee('South Valley School');

        $resultsPage = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/results?tab=category");
        $resultsPage->assertOk();
        $resultsPage->assertDontSee('South Valley School');

        $toppersPage = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/results?tab=toppers");
        $toppersPage->assertOk();
        $toppersPage->assertDontSee('South Valley School');
    }

    public function test_scoreboard_category_filter_also_scopes_the_latest_item_winners_widget(): void
    {
        // Leading Schools was already category-scoped server-side (PublicFestScoreboardService::
        // scoreboard() takes $category), but Latest Item Winners' own query in
        // scoreboardDynamicData() had no category filter at all — selecting a category tab
        // narrowed one panel and left the other showing every category's winners, which read
        // as broken/inconsistent on the same page.
        $this->markCategoryWinner($this->north, $this->northSchool, 'HS Category Winner');

        $lpItem = FestEventItem::create([
            'event_id' => $this->north->id, 'title' => 'LP Category Winner', 'category' => 'literary',
            'class_group' => 'lp', 'participant_type' => 'individual', 'is_enabled' => true,
        ]);
        $registration = FestRegistration::create([
            'event_id' => $this->north->id, 'item_id' => $lpItem->id,
            'school_id' => $this->northSchool->id, 'status' => 'approved',
        ]);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $this->north->id, 'participant_type' => 'student',
        ]);
        FestMark::create([
            'event_id' => $this->north->id, 'item_id' => $lpItem->id, 'participant_id' => $participant->id,
            'grade' => 'A', 'position' => 1, 'score' => 80,
        ]);

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/scoreboard?category=hs");

        $response->assertOk();
        // The winner-item-card's title is CSS uppercase (`class="... uppercase"`), not
        // server-side uppercased like some other headings in this app — raw HTML keeps
        // the mixed case as stored.
        $response->assertSee('HS Category Winner');
        $response->assertDontSee('LP Category Winner');
    }

    public function test_scoreboard_leading_schools_has_an_eye_link_to_the_school_detail_page(): void
    {
        $this->markCategoryWinner($this->north, $this->northSchool, 'North Poetry');

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/scoreboard");

        $response->assertOk();
        // Explicit string URL, not the route() helper — route() resolves against
        // APP_URL/localhost outside an active tenancy-initialized request, not this
        // test's tenant domain (same reasoning as every other URL in this file).
        $response->assertSee(
            'href="http://public-scoreboard.test/fest/'.$this->north->id.'/results/schools/'.$this->northSchool->id.'"',
            false
        );
    }

    public function test_school_wise_results_show_points_before_medal_counts(): void
    {
        $this->markCategoryWinner($this->north, $this->northSchool, 'North Poetry');

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/results?tab=school");

        $response->assertOk();
        // Points is the school's official standing metric — medal counts are only
        // informational (see the code comment on $medalTally) — so points must render
        // as the first data column after Rank/School, ahead of the medal icons.
        $response->assertSeeInOrder(['>School<', '>Points<'], false);
    }

    public function test_school_tab_lists_a_winner_roster_with_points_per_school(): void
    {
        $this->markCategoryWinner($this->north, $this->northSchool, 'North Poetry');

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/results?tab=school");

        $response->assertOk();
        $response->assertSee('School-wise Results');
        $response->assertSee('North Poetry');
        // markCategoryWinner() stores grade=A with score=80, and pointsForMark()
        // re-derives the effective grade from score first — 80% clears the platform
        // default table's A band (>=70%, its top tier, no A+) — so with no FestPointRule
        // configured this resolves through DEFAULT_POINTS to A's 8 points.
        $response->assertSeeInOrder(['North Poetry', '8'], false);
    }

    public function test_ranking_table_has_an_eye_link_to_the_school_detail_page(): void
    {
        $this->markCategoryWinner($this->north, $this->northSchool, 'North Poetry');

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/results?tab=school");

        $response->assertOk();
        $response->assertSee("/fest/{$this->north->id}/results/schools/{$this->northSchool->id}", false);
    }

    public function test_school_detail_page_shows_the_full_roster_with_larger_photos(): void
    {
        $schoolClass = SchoolClass::create(['tenant_id' => $this->northSchool->id, 'name' => '8']);
        $student = Student::create(['tenant_id' => $this->northSchool->id, 'school_class_id' => $schoolClass->id, 'name' => 'Anjali Menon']);
        $item = FestEventItem::create([
            'event_id' => $this->north->id, 'title' => 'North Poetry', 'category' => 'literary',
            'class_group' => 'hs', 'participant_type' => 'individual', 'is_enabled' => true,
            'results_published_at' => now(),
        ]);
        $registration = FestRegistration::create(['event_id' => $this->north->id, 'item_id' => $item->id, 'school_id' => $this->northSchool->id, 'status' => 'approved']);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $this->north->id,
            'participant_type' => 'student', 'student_id' => $student->id,
        ]);
        FestMark::create(['event_id' => $this->north->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'grade' => 'A', 'position' => 1, 'score' => 80]);

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/results/schools/{$this->northSchool->id}");

        $response->assertOk();
        // page-hero's <h1> renders the title server-side uppercased, like the rest of
        // this page's headings.
        $response->assertSee('NORTH STAR SCHOOL');
        $response->assertSee('North Poetry');
        $response->assertSee('Anjali Menon');
        $response->assertSee('← Back to all schools', false);
        $response->assertSee('id="school-roster-search"', false);
        $response->assertSee('id="school-roster-load"', false);
    }

    /**
     * schoolResultsRoster() already excluded unpublished/hidden items but never checked
     * aggregation_config.excluded_overall_categories at all -- an admin-excluded category
     * still leaked onto a school's own roster page (and into its point total there), even
     * though the main scoreboard's combined total already correctly left it out.
     */
    public function test_school_detail_page_roster_and_total_exclude_an_admin_excluded_category(): void
    {
        $this->hub->update(['aggregation_config' => array_merge(
            $this->hub->aggregation_config ?? [],
            ['excluded_overall_categories' => ['hs']],
        )]);

        // 'hs' -- excluded -- must not appear on the roster or count toward the total.
        $this->markCategoryWinner($this->north, $this->northSchool, 'Excluded HS Item');

        // 'lp' -- not excluded -- must still show and count normally.
        $lpItem = FestEventItem::create([
            'event_id' => $this->north->id, 'title' => 'Included LP Item', 'category' => 'literary',
            'class_group' => 'lp', 'participant_type' => 'individual', 'is_enabled' => true,
            'results_published_at' => now(),
        ]);
        $registration = FestRegistration::create([
            'event_id' => $this->north->id, 'item_id' => $lpItem->id,
            'school_id' => $this->northSchool->id, 'status' => 'approved',
        ]);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $this->north->id, 'participant_type' => 'student',
        ]);
        FestMark::create([
            'event_id' => $this->north->id, 'item_id' => $lpItem->id, 'participant_id' => $participant->id,
            'grade' => 'A', 'position' => 1, 'score' => 80,
        ]);

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/results/schools/{$this->northSchool->id}");

        $response->assertOk();
        $response->assertSee('Included LP Item');
        $response->assertDontSee('Excluded HS Item');
    }

    public function test_school_detail_page_404s_for_a_school_with_no_results(): void
    {
        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/results/schools/{$this->southSchool->id}");

        $response->assertNotFound();
    }

    public function test_school_detail_page_scopes_the_roster_and_total_to_a_selected_category(): void
    {
        // hs item: grade A, position 1, score 80 — same as markCategoryWinner(). Score
        // re-derivation keeps this at grade A (the platform default table has no A+ tier
        // at all — A is 70%+) and resolves to DEFAULT_POINTS['A']['1'] = 8 points.
        $this->markCategoryWinner($this->north, $this->northSchool, 'North Poetry');

        // lp item: grade B, position 1, no score (so grade stays literally 'B', no
        // re-derivation) — DEFAULT_POINTS['B']['1'] for an individual item is 5.
        $lpItem = FestEventItem::create([
            'event_id' => $this->north->id, 'title' => 'North LP Item', 'category' => 'literary',
            'class_group' => 'lp', 'participant_type' => 'individual', 'is_enabled' => true,
            'results_published_at' => now(),
        ]);
        $registration = FestRegistration::create([
            'event_id' => $this->north->id, 'item_id' => $lpItem->id,
            'school_id' => $this->northSchool->id, 'status' => 'approved',
        ]);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $this->north->id, 'participant_type' => 'student',
        ]);
        FestMark::create([
            'event_id' => $this->north->id, 'item_id' => $lpItem->id, 'participant_id' => $participant->id,
            'grade' => 'B', 'position' => 1,
        ]);

        $filtered = $this->get(
            "http://public-scoreboard.test/fest/{$this->north->id}/results/schools/{$this->northSchool->id}?category=hs"
        );
        $filtered->assertOk();
        $filtered->assertSee('North Poetry');
        $filtered->assertDontSee('North LP Item');
        // Live-computed (category-filtered path bypasses the FestResult snapshot
        // entirely), so this is the hs item's own points, not setUp()'s seeded total.
        $filtered->assertSee('text-2xl font-mono font-extrabold text-amber-400">8 <small', false);
        $filtered->assertSee('Showing', false);
        $filtered->assertSee('View full roster (all categories)', false);

        $unfiltered = $this->get(
            "http://public-scoreboard.test/fest/{$this->north->id}/results/schools/{$this->northSchool->id}"
        );
        $unfiltered->assertOk();
        $unfiltered->assertSee('North Poetry');
        $unfiltered->assertSee('North LP Item');
        $unfiltered->assertDontSee('View full roster (all categories)', false);
    }

    /**
     * schoolResultsRoster()'s $category filter did a plain exact match on class_group,
     * never expanding a merge TARGET (aggregation_config.championship_category_map) back
     * to its source categories via FestCategoryMerge::sourceKeysFor() — the same
     * expansion PublicFestScoreboardService::scoreboard()'s category branch already
     * does. A merged-away source category's items were missing from the target
     * category's own roster page, even though the scoreboard total already combined
     * them under the target.
     */
    public function test_school_detail_page_category_filter_includes_a_merged_source_category(): void
    {
        $this->hub->update(['aggregation_config' => array_merge(
            $this->hub->aggregation_config ?? [],
            ['championship_category_map' => ['lp' => 'hs']],
        )]);

        // 'hs' item, tagged directly as the merge target.
        $this->markCategoryWinner($this->north, $this->northSchool, 'North Poetry');

        // 'lp' item, merged INTO 'hs' — must show up under ?category=hs too.
        $lpItem = FestEventItem::create([
            'event_id' => $this->north->id, 'title' => 'Merged LP Item', 'category' => 'literary',
            'class_group' => 'lp', 'participant_type' => 'individual', 'is_enabled' => true,
            'results_published_at' => now(),
        ]);
        $registration = FestRegistration::create([
            'event_id' => $this->north->id, 'item_id' => $lpItem->id,
            'school_id' => $this->northSchool->id, 'status' => 'approved',
        ]);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $this->north->id, 'participant_type' => 'student',
        ]);
        FestMark::create([
            'event_id' => $this->north->id, 'item_id' => $lpItem->id, 'participant_id' => $participant->id,
            'grade' => 'A', 'position' => 1, 'score' => 80,
        ]);

        $response = $this->get(
            "http://public-scoreboard.test/fest/{$this->north->id}/results/schools/{$this->northSchool->id}?category=hs"
        );

        $response->assertOk();
        $response->assertSee('North Poetry');
        $response->assertSee('Merged LP Item');
    }

    public function test_school_detail_page_404s_for_an_unrecognized_category(): void
    {
        $this->markCategoryWinner($this->north, $this->northSchool, 'North Poetry');

        $response = $this->get(
            "http://public-scoreboard.test/fest/{$this->north->id}/results/schools/{$this->northSchool->id}?category=does-not-exist"
        );

        $response->assertNotFound();
    }

    public function test_scoreboard_eye_icon_carries_the_selected_category_to_the_school_detail_page(): void
    {
        $this->markCategoryWinner($this->north, $this->northSchool, 'North Poetry');

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/scoreboard?category=hs");

        $response->assertOk();
        $response->assertSee(
            'href="http://public-scoreboard.test/fest/'.$this->north->id.'/results/schools/'.$this->northSchool->id.'?category=hs"',
            false
        );
    }

    public function test_school_results_roster_shows_category_and_type_ordered_by_category(): void
    {
        // Position 1 but in a category that sorts AFTER the other item's category —
        // category must win as the primary sort key even though this item ranks better.
        $laterCategoryItem = FestEventItem::create([
            'event_id' => $this->north->id, 'title' => 'Zzz Late Item', 'category' => 'literary',
            'class_group' => 'zzz_category', 'participant_type' => 'group', 'is_enabled' => true,
            'results_published_at' => now(),
        ]);
        $reg1 = FestRegistration::create(['event_id' => $this->north->id, 'item_id' => $laterCategoryItem->id, 'school_id' => $this->northSchool->id, 'status' => 'approved']);
        $p1 = FestParticipant::create(['registration_id' => $reg1->id, 'event_id' => $this->north->id, 'participant_type' => 'student']);
        FestMark::create(['event_id' => $this->north->id, 'item_id' => $laterCategoryItem->id, 'participant_id' => $p1->id, 'grade' => 'A', 'position' => 1, 'score' => 80]);

        // Position 3 but in a category that sorts BEFORE — should still appear first.
        $earlierCategoryItem = FestEventItem::create([
            'event_id' => $this->north->id, 'title' => 'Aaa Early Item', 'category' => 'literary',
            'class_group' => 'aaa_category', 'participant_type' => 'individual', 'is_enabled' => true,
            'results_published_at' => now(),
        ]);
        $reg2 = FestRegistration::create(['event_id' => $this->north->id, 'item_id' => $earlierCategoryItem->id, 'school_id' => $this->northSchool->id, 'status' => 'approved']);
        $p2 = FestParticipant::create(['registration_id' => $reg2->id, 'event_id' => $this->north->id, 'participant_type' => 'student']);
        FestMark::create(['event_id' => $this->north->id, 'item_id' => $earlierCategoryItem->id, 'participant_id' => $p2->id, 'grade' => 'A', 'position' => 3, 'score' => 60]);

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/results?tab=school");

        $response->assertOk();
        $response->assertSee('Aaa Category');
        $response->assertSee('Zzz Category');
        $response->assertSee('Group');
        $response->assertSee('Individual');
        // Category is the primary sort key, so the "Aaa" category's item must render
        // before the "Zzz" category's item despite its worse (3rd place) position.
        $response->assertSeeInOrder(['Aaa Early Item', 'Zzz Late Item']);
    }

    public function test_school_results_roster_shows_grade_points_breakdown_when_applicable(): void
    {
        // pointsBreakdown() only reveals a grade/rank split when the Kalolsavam Manual's
        // grade_points + place_points actually sum to the mark's real total — guaranteed
        // for a confed_kalotsav-preset event, unlike the default table my other fixtures
        // use (see FestGradePointService::pointsBreakdown()'s docblock).
        $confedEvent = FestEvent::create([
            'tenant_id' => $this->sahodaya->id, 'title' => 'Confed Preset Fest', 'event_type' => 'kalotsav',
            'scoring_preset' => 'confed_kalotsav', 'status' => 'completed',
            'results_published' => true, 'schedule_published' => true,
        ]);
        $item = FestEventItem::create([
            'event_id' => $confedEvent->id, 'title' => 'Confed Poetry', 'category' => 'literary',
            'class_group' => 'hs', 'participant_type' => 'individual', 'is_enabled' => true,
            'results_published_at' => now(),
        ]);
        $registration = FestRegistration::create(['event_id' => $confedEvent->id, 'item_id' => $item->id, 'school_id' => $this->northSchool->id, 'status' => 'approved']);
        $participant = FestParticipant::create(['registration_id' => $registration->id, 'event_id' => $confedEvent->id, 'participant_type' => 'student']);
        FestMark::create(['event_id' => $confedEvent->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'grade' => 'A', 'position' => 1, 'score' => 90]);

        // $confedEvent->results_published is set directly above (bypassing the normal
        // "official publish" admin action) — seed the FestResult snapshot the school-wise
        // board reads from once published directly, matching setUp()'s own north/south
        // fixtures, rather than EventContext::recalculateSchoolPoints() (which depends on
        // tenancy context normally provided by the admin HTTP request/middleware that
        // triggers it for real, not available when called directly in a test like this).
        FestResult::create(['event_id' => $confedEvent->id, 'school_id' => $this->northSchool->id, 'total_points' => 10, 'rank' => 1]);

        $response = $this->get("http://public-scoreboard.test/fest/{$confedEvent->id}/results?tab=school");

        $response->assertOk();
        // config/fest_confed_kalotsav_scoring.php: grade_points.individual.A = 5,
        // place_points.individual.1 = 5, individual_points.A.1 = 10 (5 + 5).
        $response->assertSee('Grade A · 5 pts', false);
        $response->assertSee('10', false);
    }

    public function test_school_results_roster_shows_points_for_a_grade_only_mark_with_no_position(): void
    {
        // Many items only grade every entrant (A/B/C) without ranking each one — the
        // school roster must still show a points value for those, not just for the
        // top-3 who also got a numeric position. Regression test for a bug where
        // publicWinnerRow() only fills 'points'/'grade_points' when position is set
        // (correct for the Individual/winners-only tab), and schoolResultsRoster()'s
        // `publicWinnerRow(...) + [...]` merge let that null clobber its own correctly
        // computed value — PHP's `+` keeps the left side on key collisions.
        $item = FestEventItem::create([
            'event_id' => $this->north->id, 'title' => 'North Group Song', 'category' => 'music',
            'class_group' => 'hs', 'participant_type' => 'individual', 'is_enabled' => true,
            'results_published_at' => now(),
        ]);
        $registration = FestRegistration::create([
            'event_id' => $this->north->id, 'item_id' => $item->id,
            'school_id' => $this->northSchool->id, 'status' => 'approved',
        ]);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $this->north->id, 'participant_type' => 'student',
        ]);
        FestMark::create([
            'event_id' => $this->north->id, 'item_id' => $item->id, 'participant_id' => $participant->id,
            'grade' => 'A', 'position' => null,
        ]);

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/results?tab=school");

        $response->assertOk();
        $response->assertSee('North Group Song');
        // No FestPointRule configured, no scoring preset — falls back to
        // FestGradePointService::pointsForMark()'s $defaultGradeOnly['A'] for an
        // individual item (3), not the blank/missing value the bug produced.
        $response->assertSeeInOrder(['North Group Song', 'Grade A', '3', 'PTS'], false);
    }

    public function test_school_ranking_row_links_to_its_roster_card(): void
    {
        $this->markCategoryWinner($this->north, $this->northSchool, 'North Poetry');

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/results?tab=school");

        $response->assertOk();
        $response->assertSee('data-jump-to-school="'.$this->northSchool->id.'"', false);
        $response->assertSee('data-jump-to-school', false);
        $response->assertSee('jumpToSchool', false);
    }

    public function test_school_results_roster_shows_participant_name_and_photo(): void
    {
        $item = FestEventItem::create([
            'event_id' => $this->north->id, 'title' => 'North Poetry', 'category' => 'literary',
            'class_group' => 'hs', 'participant_type' => 'individual', 'is_enabled' => true,
            'results_published_at' => now(),
        ]);
        $registration = FestRegistration::create(['event_id' => $this->north->id, 'item_id' => $item->id, 'school_id' => $this->northSchool->id, 'status' => 'approved']);
        $schoolClass = SchoolClass::create(['tenant_id' => $this->northSchool->id, 'name' => '8']);
        $student = Student::create(['tenant_id' => $this->northSchool->id, 'school_class_id' => $schoolClass->id, 'name' => 'Anjali Menon']);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $this->north->id,
            'participant_type' => 'student', 'student_id' => $student->id,
        ]);
        FestMark::create(['event_id' => $this->north->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'grade' => 'A', 'position' => 1, 'score' => 80]);

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/results?tab=school");

        $response->assertOk();
        // Rendered raw text is mixed-case — the roster's uppercase display is CSS
        // text-transform on this element, not server-side casing.
        $response->assertSee('Anjali Menon');
    }

    public function test_school_results_roster_includes_non_winning_items_too(): void
    {
        $winningItem = $this->markCategoryWinner($this->north, $this->northSchool, 'North Poetry');

        // A second item where the same school entered but did NOT place top-3 — the
        // roster is meant to be the school's full report, not just its medal wins.
        $nonWinningItem = FestEventItem::create([
            'event_id' => $this->north->id, 'title' => 'North Elocution', 'category' => 'literary',
            'class_group' => 'hs', 'participant_type' => 'individual', 'is_enabled' => true,
            'results_published_at' => now(),
        ]);
        $registration = FestRegistration::create([
            'event_id' => $this->north->id, 'item_id' => $nonWinningItem->id,
            'school_id' => $this->northSchool->id, 'status' => 'approved',
        ]);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $this->north->id, 'participant_type' => 'student',
        ]);
        // score must land in the platform default table's B band (>= 60%, < 70%) -- the
        // page re-derives the effective grade from score, so a lower value here would
        // silently display as Grade C instead.
        FestMark::create([
            'event_id' => $this->north->id, 'item_id' => $nonWinningItem->id, 'participant_id' => $participant->id,
            'grade' => 'B', 'position' => 4, 'score' => 65,
        ]);

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/results?tab=school");

        $response->assertOk();
        $response->assertSee('North Poetry');
        $response->assertSee('North Elocution');
        $response->assertSee('Grade B', false);
    }

    public function test_school_winners_section_has_a_school_picker_dropdown(): void
    {
        $this->markCategoryWinner($this->north, $this->northSchool, 'North Poetry');

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/results?tab=school");

        $response->assertOk();
        $response->assertSee('id="school-winner-picker"', false);
        $response->assertSee('<option value="'.$this->northSchool->id.'">North Star School</option>', false);
        $response->assertSee('data-school-id="'.$this->northSchool->id.'"', false);
    }

    public function test_individual_tab_shows_points_alongside_position(): void
    {
        $this->markCategoryWinner($this->north, $this->northSchool, 'North Poetry');

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/results?tab=individual");

        $response->assertOk();
        $response->assertSee('>Points<', false);
        // Score=80 re-derives to grade A (the platform default table's top tier, no A+)
        // before points are looked up, so with no FestPointRule configured this resolves
        // to DEFAULT_POINTS['A']['1'] = 8.
        $response->assertSeeInOrder(['North Poetry', '8'], false);
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
            // The item-results page's heading renders the title server-side uppercased
            // (not just CSS text-transform), matching the school-name assertion below.
            ->assertSee('NORTH POETRY')
            ->assertSee('NORTH STAR SCHOOL')
            ->assertDontSee('South Valley School');

        $this->get("http://public-scoreboard.test/fest/{$this->north->id}/items/{$southItem->id}/results")
            ->assertNotFound();
    }

    public function test_item_full_results_table_shows_points_for_a_non_placing_grade_only_entrant(): void
    {
        // Same root cause as test_school_results_roster_shows_points_for_a_grade_only_mark_with_no_position:
        // publicWinnerRow() used to null out 'points'/'grade_points' whenever a mark had no
        // position, but itemResults()'s "Full Results" table intentionally lists every
        // entrant, not just the top-3 winners — so a grade-only, non-placing participant's
        // "Total" column silently rendered "0" (the blade's `?? 0` fallback) instead of the
        // real points their grade earns.
        $item = $this->markCategoryWinner($this->north, $this->northSchool, 'North Poetry');

        $registration = FestRegistration::create([
            'event_id' => $this->north->id, 'item_id' => $item->id,
            'school_id' => $this->northSchool->id, 'status' => 'approved',
        ]);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $this->north->id, 'participant_type' => 'student',
        ]);
        FestMark::create([
            'event_id' => $this->north->id, 'item_id' => $item->id, 'participant_id' => $participant->id,
            'grade' => 'B', 'position' => null,
        ]);

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/items/{$item->id}/results");

        $response->assertOk();
        // No FestPointRule configured, no scoring preset — $defaultGradeOnly['B'] for an
        // individual item is 2, not the "0" the bug produced. Matched against the Total
        // column's exact class combo (font-bold + text-white, unique among that table's
        // columns) so this doesn't false-match "2" appearing anywhere else on the page.
        $response->assertSee('font-mono font-bold text-white">2</td>', false);
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

    public function test_unpublished_child_does_not_leak_when_hub_is_published(): void
    {
        $this->north->update(['results_published' => false, 'status' => 'ongoing']);

        $scoreboard = $this->get(
            "http://public-scoreboard.test/fest/{$this->north->id}/scoreboard"
        );
        $scoreboard->assertOk();
        $scoreboard->assertSee('Official Standings Not Published Yet');
        $scoreboard->assertDontSee('North Star School');

        // The event itself remains a valid public catalogue entry; only its results are
        // disabled, so the results endpoint denies access without exposing any rows.
        $this->get("http://public-scoreboard.test/fest/{$this->north->id}/results")
            ->assertForbidden();
    }

    public function test_scoreboard_page_has_event_day_cache_policy(): void
    {
        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/scoreboard");

        $response->assertOk();
        $this->assertStringContainsString('s-maxage=30', $response->headers->get('Cache-Control'));
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

    /**
     * Regression test for a real production gap: once ANY item in an event publishes
     * its results, the whole /results page's tabs (item/category/school/individual)
     * previously showed marks from EVERY item in the event, published or not — because
     * only the top-level "at least one item published" check gated the page at all;
     * the per-tab queries never re-checked which specific item each mark belonged to.
     */
    public function test_results_page_hides_marks_from_unpublished_items_even_after_another_item_publishes(): void
    {
        $event = FestEvent::create([
            'tenant_id' => $this->sahodaya->id,
            'title' => 'Mixed Publish Fest',
            'event_type' => 'kalotsav',
            'conduct_mode' => 'standard',
            'status' => 'ongoing',
            'results_published' => false,
        ]);

        $publishedItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Published Poetry', 'category' => 'literary',
            'class_group' => 'hs', 'participant_type' => 'individual', 'is_enabled' => true,
            'results_published_at' => now(),
        ]);
        $unpublishedItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Secret Debate', 'category' => 'literary',
            'class_group' => 'hs', 'participant_type' => 'individual', 'is_enabled' => true,
            'results_published_at' => null,
        ]);

        $this->markItemWinner($event, $publishedItem, $this->northSchool);
        $this->markItemWinner($event, $unpublishedItem, $this->southSchool);

        // Default results page (no ?tab=) and the explicit item tab both pull from the
        // same $marks query — only the still-unpublished item's data should be absent.
        $itemTab = $this->get("http://public-scoreboard.test/fest/{$event->id}/results?tab=item");
        $itemTab->assertOk();
        $itemTab->assertSee('Published Poetry');
        $itemTab->assertSee('NORTH STAR SCHOOL');
        $itemTab->assertDontSee('Secret Debate');
        $itemTab->assertDontSee('SOUTH VALLEY SCHOOL');

        $individualTab = $this->get("http://public-scoreboard.test/fest/{$event->id}/results?tab=individual");
        $individualTab->assertOk();
        $individualTab->assertDontSee('SOUTH VALLEY SCHOOL');

        // The school-wise board's points/medal tally must also exclude the unpublished
        // item's mark, not just the item-tab listing.
        $schoolTab = $this->get("http://public-scoreboard.test/fest/{$event->id}/results?tab=school");
        $schoolTab->assertOk();
        $schoolTab->assertDontSee('South Valley School');
    }

    private function markItemWinner(FestEvent $event, FestEventItem $item, Tenant $school): FestParticipant
    {
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

        return $participant;
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
        $this->assertStringContainsString('s-maxage=10', $data->headers->get('Cache-Control'));
    }

    /**
     * The TV now scrolls continuously instead of paginating into fixed-height slides,
     * so a board's row count no longer forces a "Page N of M" split — every school
     * renders in one continuous Overall Standings section. Display controls (pause/
     * fullscreen/prev/next) still appear once there's more than one section to move
     * between — here, the standings section plus a published item's winners section.
     */
    public function test_tv_overall_standings_shows_all_schools_unpaginated_and_exposes_display_controls(): void
    {
        foreach (range(2, 6) as $rank) {
            $school = $this->school("TV School {$rank}");
            FestResult::create([
                'event_id' => $this->north->id,
                'school_id' => $school->id,
                'total_points' => 70 - $rank,
                'rank' => $rank,
                'published_at' => now(),
            ]);
        }

        $item = FestEventItem::create([
            'event_id' => $this->north->id, 'title' => 'Solo Song', 'category' => 'performing',
            'class_group' => 'hs', 'participant_type' => 'individual', 'is_enabled' => true,
            'results_published_at' => now(),
        ]);
        $this->markItemWinner($this->north, $item, $this->northSchool);

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/tv");
        $html = $response->getContent();

        $response->assertOk()
            ->assertSee('Results Display')
            ->assertSee('Overall Standings')
            ->assertSee('data-tv-pause', false)
            ->assertSee('data-tv-fullscreen', false)
            ->assertSee('data-tv-prev', false)
            ->assertSee('data-tv-next', false)
            ->assertDontSee('Page 1 of', false);

        // The published item's own category board repeats the same FestResult-derived
        // standings, so schools can legitimately appear more than once on the page —
        // what this test actually guards is that every school still appears SOMEWHERE,
        // unpaginated, not that it appears exactly once.
        foreach (array_merge(['North Star School'], array_map(fn ($rank) => "TV School {$rank}", range(2, 6))) as $name) {
            $this->assertStringContainsString($name, $html, "{$name} must appear on the page — nothing may be cut off by pagination.");
        }
    }

    /**
     * The TV board is meant to run continuously at the venue, unlike results()/
     * itemResults()/scoreboard() which stay 403'd until something is published — it
     * already had a graceful "nothing published yet" fallback (a schools-only roster)
     * built for exactly this case, so gating it behind publish status on top of that
     * only broke the venue display for no benefit.
     */
    public function test_tv_screen_never_403s_even_when_nothing_is_published_yet(): void
    {
        $freshEvent = FestEvent::create([
            'tenant_id' => $this->sahodaya->id,
            'title' => 'Fresh Unpublished Fest',
            'event_type' => 'kalotsav',
            'status' => 'ongoing',
            'schedule_published' => true,
            'results_published' => false,
        ]);

        $school = $this->school('Unpublished Fest School');
        $item = FestEventItem::create([
            'event_id' => $freshEvent->id,
            'title' => 'Solo Song',
            'participant_type' => 'individual',
            'is_enabled' => true,
        ]);
        FestRegistration::create([
            'event_id' => $freshEvent->id,
            'item_id' => $item->id,
            'school_id' => $school->id,
            'status' => 'approved',
        ]);

        $response = $this->get("http://public-scoreboard.test/fest/{$freshEvent->id}/tv");

        $response->assertOk()->assertSee('Participating Schools');
    }

    /**
     * A team item with 2+ winning positions renders them side by side in one winner
     * card (fest-winner-item-card-tv.blade.php's flex-wrap columns) — no more per-
     * position slide splitting now that the TV scrolls continuously instead of
     * paginating; a card too wide for one row just wraps to a second row on its own.
     */
    public function test_tv_shows_multiple_winning_positions_for_a_team_item_without_splitting(): void
    {
        $item = FestEventItem::create([
            'event_id' => $this->north->id, 'title' => 'Group Dance', 'category' => 'performing',
            'class_group' => 'hs', 'participant_type' => 'team', 'is_enabled' => true,
            'results_published_at' => now(),
        ]);
        $schoolClass = SchoolClass::create(['tenant_id' => $this->northSchool->id, 'name' => '9']);

        $goldReg = FestRegistration::create(['event_id' => $this->north->id, 'item_id' => $item->id, 'school_id' => $this->northSchool->id, 'status' => 'approved']);
        $goldStudent = Student::create(['tenant_id' => $this->northSchool->id, 'school_class_id' => $schoolClass->id, 'name' => 'Gold Team Member']);
        $goldParticipant = FestParticipant::create(['registration_id' => $goldReg->id, 'event_id' => $this->north->id, 'participant_type' => 'student', 'participant_role' => 'performer', 'student_id' => $goldStudent->id]);
        FestMark::create(['event_id' => $this->north->id, 'item_id' => $item->id, 'participant_id' => $goldParticipant->id, 'grade' => 'A', 'position' => 1, 'score' => 90]);

        $silverReg = FestRegistration::create(['event_id' => $this->north->id, 'item_id' => $item->id, 'school_id' => $this->southSchool->id, 'status' => 'approved']);
        $silverStudent = Student::create(['tenant_id' => $this->southSchool->id, 'school_class_id' => SchoolClass::create(['tenant_id' => $this->southSchool->id, 'name' => '9'])->id, 'name' => 'Silver Team Member']);
        $silverParticipant = FestParticipant::create(['registration_id' => $silverReg->id, 'event_id' => $this->north->id, 'participant_type' => 'student', 'participant_role' => 'performer', 'student_id' => $silverStudent->id]);
        FestMark::create(['event_id' => $this->north->id, 'item_id' => $item->id, 'participant_id' => $silverParticipant->id, 'grade' => 'A', 'position' => 2, 'score' => 80]);

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/tv");
        $html = $response->getContent();

        // Names render visually uppercase via CSS (text-transform), not server-side —
        // the raw HTML keeps the stored mixed case.
        $response->assertOk()
            ->assertSee('Gold Team Member')
            ->assertSee('Silver Team Member')
            ->assertDontSee('Slide', false);
        $this->assertSame(1, substr_count($html, 'Gold Team Member'));
        $this->assertSame(1, substr_count($html, 'Silver Team Member'));

        // Both positions must render inside the SAME winner card (one <article>), side
        // by side, not split into separate cards.
        $cardStart = strrpos(substr($html, 0, strpos($html, 'Gold Team Member')), '<article');
        $cardEnd = strpos($html, '</article>', $cardStart);
        $card = substr($html, $cardStart, $cardEnd - $cardStart);
        $this->assertStringContainsString('Silver Team Member', $card, "The gold and silver teams must share one winner card.");
    }

    /**
     * A band item's roster can run to 25 members (a duet or small team stays at 2-12) —
     * far more than the old fixed-height slide could show without a "+N more" tile
     * hiding most of the roster. The TV's continuous scroll removes that height limit
     * entirely: fest-winner-item-card-tv.blade.php's roster grid is an auto-fill CSS
     * grid, so all 25 members render in one card, wrapping to as many rows as needed.
     */
    public function test_tv_shows_a_large_bands_full_roster_without_truncating(): void
    {
        $item = FestEventItem::create([
            'event_id' => $this->north->id, 'title' => 'School Band', 'category' => 'performing',
            'class_group' => 'hs', 'participant_type' => 'group', 'is_enabled' => true,
            'results_published_at' => now(),
        ]);
        $registration = FestRegistration::create(['event_id' => $this->north->id, 'item_id' => $item->id, 'school_id' => $this->northSchool->id, 'status' => 'approved']);
        $schoolClass = SchoolClass::create(['tenant_id' => $this->northSchool->id, 'name' => '9']);

        $lastParticipant = null;
        for ($i = 1; $i <= 25; $i++) {
            $student = Student::create(['tenant_id' => $this->northSchool->id, 'school_class_id' => $schoolClass->id, 'name' => "Band Member {$i}"]);
            $lastParticipant = FestParticipant::create(['registration_id' => $registration->id, 'event_id' => $this->north->id, 'participant_type' => 'student', 'participant_role' => 'performer', 'student_id' => $student->id]);
        }
        FestMark::create(['event_id' => $this->north->id, 'item_id' => $item->id, 'participant_id' => $lastParticipant->id, 'grade' => 'A', 'position' => 1, 'score' => 90]);

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/tv");
        $html = $response->getContent();

        $response->assertOk();
        for ($i = 1; $i <= 25; $i++) {
            $this->assertSame(1, substr_count($html, "Band Member {$i}<"), "Band Member {$i} must appear exactly once — never dropped behind a \"+N more\" tile.");
        }
        $this->assertStringNotContainsString('more</span>', $html, 'A "+N more" truncation tile must never appear — the roster grid wraps instead of truncating.');
        $response->assertDontSee('Slide', false)->assertSee('25 members');
    }

    /**
     * A 12-member team (e.g. a Kolkali group) wraps to a second row of roster tiles at
     * 9-per-row — under the old fixed-height slide that second row would never have
     * fit, forcing a split across two slides. The TV's continuous scroll has no such
     * height limit, so the roster's auto-fill grid just wraps to a second row in place
     * and all 12 members render together in one card.
     */
    public function test_tv_shows_a_twelve_member_roster_wrapping_to_a_second_row(): void
    {
        $item = FestEventItem::create([
            'event_id' => $this->north->id, 'title' => 'Kolkali', 'category' => 'performing',
            'class_group' => 'hs', 'participant_type' => 'group', 'is_enabled' => true,
            'results_published_at' => now(),
        ]);
        $registration = FestRegistration::create(['event_id' => $this->north->id, 'item_id' => $item->id, 'school_id' => $this->northSchool->id, 'status' => 'approved']);
        $schoolClass = SchoolClass::create(['tenant_id' => $this->northSchool->id, 'name' => '9']);

        $lastParticipant = null;
        for ($i = 1; $i <= 12; $i++) {
            $student = Student::create(['tenant_id' => $this->northSchool->id, 'school_class_id' => $schoolClass->id, 'name' => "Kolkali Member {$i}"]);
            $lastParticipant = FestParticipant::create(['registration_id' => $registration->id, 'event_id' => $this->north->id, 'participant_type' => 'student', 'participant_role' => 'performer', 'student_id' => $student->id]);
        }
        FestMark::create(['event_id' => $this->north->id, 'item_id' => $item->id, 'participant_id' => $lastParticipant->id, 'grade' => 'A', 'position' => 1, 'score' => 90]);

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/tv");
        $html = $response->getContent();

        $response->assertOk();
        for ($i = 1; $i <= 12; $i++) {
            $this->assertSame(1, substr_count($html, "Kolkali Member {$i}<"), "Kolkali Member {$i} must appear exactly once.");
        }
        $response->assertDontSee('Slide', false)->assertSee('12 members');
    }

    /**
     * A team small enough that its roster never needs paginating still benefits from
     * an explicit member count — a viewer catching the slide for a couple of seconds
     * can't reliably count photos themselves. An individual item (a "team" of one)
     * gets no such badge; it adds nothing when the participant's own name is already
     * the whole card.
     */
    public function test_tv_shows_a_plain_member_count_for_a_small_team_and_none_for_an_individual(): void
    {
        $teamItem = FestEventItem::create([
            'event_id' => $this->north->id, 'title' => 'Duet Song', 'category' => 'performing',
            'class_group' => 'hs', 'participant_type' => 'pair', 'is_enabled' => true,
            'results_published_at' => now(),
        ]);
        $teamRegistration = FestRegistration::create(['event_id' => $this->north->id, 'item_id' => $teamItem->id, 'school_id' => $this->northSchool->id, 'status' => 'approved']);
        $schoolClass = SchoolClass::create(['tenant_id' => $this->northSchool->id, 'name' => '9']);
        $lastParticipant = null;
        foreach (['Duet Member One', 'Duet Member Two'] as $name) {
            $student = Student::create(['tenant_id' => $this->northSchool->id, 'school_class_id' => $schoolClass->id, 'name' => $name]);
            $lastParticipant = FestParticipant::create(['registration_id' => $teamRegistration->id, 'event_id' => $this->north->id, 'participant_type' => 'student', 'participant_role' => 'performer', 'student_id' => $student->id]);
        }
        FestMark::create(['event_id' => $this->north->id, 'item_id' => $teamItem->id, 'participant_id' => $lastParticipant->id, 'grade' => 'A', 'position' => 1, 'score' => 90]);

        $soloItem = FestEventItem::create([
            'event_id' => $this->north->id, 'title' => 'Solo Song', 'category' => 'performing',
            'class_group' => 'hs', 'participant_type' => 'individual', 'is_enabled' => true,
            'results_published_at' => now(),
        ]);
        $soloRegistration = FestRegistration::create(['event_id' => $this->north->id, 'item_id' => $soloItem->id, 'school_id' => $this->northSchool->id, 'status' => 'approved']);
        $soloStudent = Student::create(['tenant_id' => $this->northSchool->id, 'school_class_id' => $schoolClass->id, 'name' => 'Solo Singer']);
        $soloParticipant = FestParticipant::create(['registration_id' => $soloRegistration->id, 'event_id' => $this->north->id, 'participant_type' => 'student', 'participant_role' => 'performer', 'student_id' => $soloStudent->id]);
        FestMark::create(['event_id' => $this->north->id, 'item_id' => $soloItem->id, 'participant_id' => $soloParticipant->id, 'grade' => 'A', 'position' => 1, 'score' => 90]);

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/tv");
        $html = $response->getContent();

        $response->assertOk()->assertSee('2 members');

        // Isolate the solo item's own winner card (one <article>) — it must not show
        // a member-count badge, even though the duet's card elsewhere on the same
        // continuous-scroll winners section does.
        $soloCardStart = strrpos(substr($html, 0, strpos($html, 'Solo Singer')), '<article');
        $soloCardEnd = strpos($html, '</article>', $soloCardStart);
        $soloCard = substr($html, $soloCardStart, $soloCardEnd - $soloCardStart);
        $this->assertStringNotContainsString('members', $soloCard, 'An individual item must not show a member-count badge.');
    }

    /**
     * Ties are not artificially broken, so an individual item can have more than 3
     * winners sharing positions 1-3 (e.g. four students all placed first). Each
     * winner column is a flat min-w-[22rem]; only 3 fit across one row, so a 4th wraps
     * to a second row within the same winner card — the continuous-scroll TV has no
     * fixed-height slide to overflow, so nothing needs splitting into separate cards.
     */
    public function test_tv_shows_more_than_three_tied_winners_for_an_individual_item_without_splitting(): void
    {
        $item = FestEventItem::create([
            'event_id' => $this->north->id, 'title' => 'Anchoring (Single)', 'category' => 'performing',
            'class_group' => 'hs', 'participant_type' => 'individual', 'is_enabled' => true,
            'results_published_at' => now(),
        ]);
        $schoolClass = SchoolClass::create(['tenant_id' => $this->northSchool->id, 'name' => '9']);

        foreach (['Tied Winner One', 'Tied Winner Two', 'Tied Winner Three', 'Tied Winner Four'] as $name) {
            $registration = FestRegistration::create(['event_id' => $this->north->id, 'item_id' => $item->id, 'school_id' => $this->northSchool->id, 'status' => 'approved']);
            $student = Student::create(['tenant_id' => $this->northSchool->id, 'school_class_id' => $schoolClass->id, 'name' => $name]);
            $participant = FestParticipant::create(['registration_id' => $registration->id, 'event_id' => $this->north->id, 'participant_type' => 'student', 'participant_role' => 'performer', 'student_id' => $student->id]);
            FestMark::create(['event_id' => $this->north->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'grade' => 'A', 'position' => 1, 'score' => 90]);
        }

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/tv");
        $html = $response->getContent();

        $response->assertOk()->assertDontSee('Slide', false);
        foreach (['Tied Winner One', 'Tied Winner Two', 'Tied Winner Three', 'Tied Winner Four'] as $name) {
            $this->assertSame(1, substr_count($html, $name), "{$name} must appear exactly once.");
        }

        // All 4 tied winners must render inside the SAME winner card (one <article>),
        // wrapping to a second row of columns rather than splitting into separate cards.
        $cardStart = strrpos(substr($html, 0, strpos($html, 'Tied Winner One')), '<article');
        $cardEnd = strpos($html, '</article>', $cardStart);
        $card = substr($html, $cardStart, $cardEnd - $cardStart);
        foreach (['Tied Winner One', 'Tied Winner Two', 'Tied Winner Three', 'Tied Winner Four'] as $name) {
            $this->assertStringContainsString($name, $card, "{$name} must be in the single shared winner card.");
        }
    }

    /**
     * "Latest Item Winners" used to show EVERY published item, which on a busy event
     * (50+ items) meant many minutes of rotation before the TV ever cycled back to a
     * standings board. tv() now caps this to the 10 most recently published items —
     * $dynamic['latestWinners'] is already sorted most-recently-updated-item-first, so
     * this is genuinely "what just got published", not an arbitrary cut.
     */
    public function test_tv_latest_item_winners_caps_to_ten_most_recent_items(): void
    {
        for ($i = 1; $i <= 13; $i++) {
            $item = FestEventItem::create([
                'event_id' => $this->north->id, 'title' => "Recent Item {$i}", 'category' => 'literary',
                'class_group' => 'hs', 'participant_type' => 'individual', 'is_enabled' => true,
                // Staggered timestamps so item 13 is the most recently published and
                // item 1 the oldest — ordering must be deterministic for this test.
                'results_published_at' => now()->addSeconds($i),
            ]);
            $registration = FestRegistration::create(['event_id' => $this->north->id, 'item_id' => $item->id, 'school_id' => $this->northSchool->id, 'status' => 'approved']);
            $participant = FestParticipant::create(['registration_id' => $registration->id, 'event_id' => $this->north->id, 'participant_type' => 'student']);
            $mark = FestMark::create(['event_id' => $this->north->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'grade' => 'A', 'position' => 1, 'score' => 90]);
            // FestMark.updated_at (what $dynamic['latestWinners'] actually sorts by) is
            // stamped at create() time regardless of the item's own results_published_at
            // set above — force it to match so ordering follows publish order.
            $mark->forceFill(['updated_at' => now()->addSeconds($i)])->save();
        }

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/tv");
        $html = $response->getContent();

        $response->assertOk();
        foreach (range(4, 13) as $i) {
            $this->assertStringContainsString("Recent Item {$i}", $html, "Recent Item {$i} is among the 10 most recent and must appear.");
        }
        foreach (range(1, 3) as $i) {
            $this->assertStringNotContainsString("Recent Item {$i}<", $html, "Recent Item {$i} is older than the 10 most recent and must not appear.");
        }
    }

    /**
     * A category board can run to 20-30+ schools on a busy event, and scrolling
     * through every one of them for every category made the loop back to the boards
     * people actually care about (Overall Standings, Latest Item Winners) take too
     * long. Each category board is now capped to the top 15 schools — the Overall
     * Standings board is uncapped, since "everyone deserves to see their own row"
     * applies there but not to a per-category breakdown.
     */
    public function test_tv_category_board_caps_to_top_fifteen_schools(): void
    {
        // 20 schools, each with its own 'hs' item so every school's rank is deterministic
        // (position 1 in every item, but a descending score re-derives to descending
        // grade points — see markCategoryWinner()'s own comment on this).
        for ($i = 1; $i <= 20; $i++) {
            $school = $this->school("Category Cap School {$i}");
            $item = FestEventItem::create([
                'event_id' => $this->north->id, 'title' => "Category Cap Item {$i}", 'category' => 'literary',
                'class_group' => 'hs', 'participant_type' => 'individual', 'is_enabled' => true,
                'results_published_at' => now(),
            ]);
            $registration = FestRegistration::create(['event_id' => $this->north->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
            $participant = FestParticipant::create(['registration_id' => $registration->id, 'event_id' => $this->north->id, 'participant_type' => 'student']);
            FestMark::create(['event_id' => $this->north->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'grade' => 'A', 'position' => 1, 'score' => 100 - $i]);
        }

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/tv");
        $html = $response->getContent();
        $response->assertOk()->assertSee('Top 15', false);

        // The category board's own section is whichever comes after its "... Standings"
        // title through the end of the document (nothing else follows it) — isolates
        // this assertion to just the category board, not the uncapped Overall Standings
        // section earlier on the same page. Names/labels render visually uppercase via
        // CSS (text-transform), not server-side — the raw HTML keeps the stored case.
        // 'hs' resolves to "Category 3 — Classes 8, 9 & 10" under this fixture's default
        // class-group scheme (not a literal "HS" label) — every item's own winner card
        // also shows that same string as its category_label, so searching for the board
        // TITLE specifically (with its " Standings" suffix and HTML-escaped "&") is what
        // isolates the actual board section, not just the first winner card mentioning
        // the category.
        $categoryBoardStart = strpos($html, 'Category 3 — Classes 8, 9 &amp; 10 Standings');
        $this->assertNotFalse($categoryBoardStart, "Expected the 'hs' category board (Category 3) to render.");
        $categoryBoardHtml = substr($html, $categoryBoardStart);

        // All 20 schools tie at the same points here (position=1, same grade — the
        // grade/position pair is what actually drives points, not the score value used
        // above only to keep marks distinguishable), so which 15 schools land on the
        // capped board isn't deterministic — only the COUNT is what this test checks.
        preg_match_all('/title="(Category Cap School \d+)"/', $categoryBoardHtml, $matches);
        $this->assertCount(15, array_unique($matches[1]), 'Exactly 15 schools (the top-15 cap) must appear on the category board, not all 20.');
    }

    /**
     * Regression test: the event landing page's "Event item finder" grid used to render
     * items in plain display_order/title order regardless of publish state, so on an
     * event with a handful of published items scattered among many still-unpublished
     * ones, the grid was mostly "Not yet published" cards with the actually-useful
     * Results links buried wherever their title happened to sort. Items whose Results
     * button actually shows (results_published_at set, not results_hidden) now sort
     * first.
     */
    public function test_event_item_finder_sorts_published_results_before_unpublished_items(): void
    {
        FestEventItem::create([
            'event_id' => $this->north->id, 'title' => 'Aaa Unpublished Item',
            'participant_type' => 'individual', 'is_enabled' => true,
        ]);
        // Actually has a recorded mark, not just the publish flag -- a published item
        // with zero marks no longer counts as "ready" for this sort (see the fest
        // public-pages duplicate-link cleanup: floating an item with no data to the top
        // just to show a disabled "Not published" card isn't the point of this sort).
        $publishedItem = FestEventItem::create([
            'event_id' => $this->north->id, 'title' => 'Zzz Published Item',
            'participant_type' => 'individual', 'is_enabled' => true,
            'results_published_at' => now(), 'results_hidden' => false,
        ]);
        $this->markItemWinner($this->north, $publishedItem, $this->northSchool);

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}");

        $response->assertOk()->assertSeeInOrder(['Zzz Published Item', 'Aaa Unpublished Item']);
    }

    /** A published item with zero marks recorded (published too early, or a no-show item) must not float to the top of the item grid or render a clickable Results link -- it renders the same "not ready" disabled state as an unpublished item. */
    public function test_event_item_finder_does_not_promote_a_published_item_with_no_marks(): void
    {
        FestEventItem::create([
            'event_id' => $this->north->id, 'title' => 'Aaa Unpublished Item',
            'participant_type' => 'individual', 'is_enabled' => true,
        ]);
        FestEventItem::create([
            'event_id' => $this->north->id, 'title' => 'Zzz Published No Marks Item',
            'participant_type' => 'individual', 'is_enabled' => true,
            'results_published_at' => now(), 'results_hidden' => false,
        ]);

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}");

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('Zzz Published No Marks Item', $content);
        $this->assertStringNotContainsString(route('tenant.fest.item-results', [$this->north->id, FestEventItem::where('title', 'Zzz Published No Marks Item')->value('id')]), $content);
        // Both items are equally "not ready" -- original display order (Aaa before Zzz) wins the stable sort, unlike the promoted case above.
        $response->assertSeeInOrder(['Aaa Unpublished Item', 'Zzz Published No Marks Item']);
    }

    /**
     * Regression test for a real production bug: when a phase's leaf event's TV board
     * shows the CROSS-PHASE combined total (via crossPhaseScoreboard(), once another
     * phase becomes publicly visible too), the gold/silver/bronze medal tally used to
     * stay scoped to only the CURRENT phase's own FestMark rows — so a medal earned in
     * an earlier phase silently dropped out of the medal columns and got swept into the
     * catch-all "Grade" column instead, even though total_points correctly included it.
     */
    public function test_tv_medal_tally_includes_medals_from_other_visible_phases_in_combined_total(): void
    {
        $hub = FestEvent::create([
            'tenant_id' => $this->sahodaya->id, 'title' => 'Phased Kalotsav', 'event_type' => 'kalolsavam',
            'status' => 'ongoing', 'schedule_published' => true,
        ]);
        $hubPhase1 = FestEventPhase::create(['event_id' => $hub->id, 'name' => 'Phase 1', 'code' => 'P1', 'sort_order' => 1]);
        $hubPhase2 = FestEventPhase::create(['event_id' => $hub->id, 'name' => 'Phase 2', 'code' => 'P2', 'sort_order' => 2]);

        $leaf1 = FestEvent::create([
            'tenant_id' => $this->sahodaya->id, 'title' => 'Phased Kalotsav - Phase 1', 'event_type' => 'kalolsavam',
            'parent_event_id' => $hub->id, 'source_phase_id' => $hubPhase1->id,
            'status' => 'ongoing', 'schedule_published' => true, 'results_published' => true,
        ]);
        $leaf1Phase = FestEventPhase::create(['event_id' => $leaf1->id, 'source_phase_id' => $hubPhase1->id, 'name' => 'Phase 1', 'code' => 'P1', 'sort_order' => 1]);

        $leaf2 = FestEvent::create([
            'tenant_id' => $this->sahodaya->id, 'title' => 'Phased Kalotsav - Phase 2', 'event_type' => 'kalolsavam',
            'parent_event_id' => $hub->id, 'source_phase_id' => $hubPhase2->id,
            'status' => 'ongoing', 'schedule_published' => true, 'results_published' => true,
        ]);
        FestEventPhase::create(['event_id' => $leaf2->id, 'source_phase_id' => $hubPhase2->id, 'name' => 'Phase 2', 'code' => 'P2', 'sort_order' => 1]);

        $school = $this->school('Cross Phase School');

        // The school's only medal is earned in Phase 1 — a genuine 1st place, worth
        // real gold points, not grade-only points.
        $item = FestEventItem::create([
            'event_id' => $leaf1->id, 'title' => 'Phase 1 Item', 'phase_id' => $leaf1Phase->id,
            'category' => 'literary', 'class_group' => 'hs', 'participant_type' => 'individual',
            'is_enabled' => true, 'results_published_at' => now(),
        ]);
        $registration = FestRegistration::create(['event_id' => $leaf1->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
        $participant = FestParticipant::create(['registration_id' => $registration->id, 'event_id' => $leaf1->id, 'participant_type' => 'student']);
        FestMark::create(['event_id' => $leaf1->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'grade' => 'A', 'position' => 1, 'score' => 90]);

        // View Phase 2's own TV screen — its combined "Overall Standings" board must
        // show this school's Phase-1 gold medal, not fold it into "Grade".
        $response = $this->get("http://public-scoreboard.test/fest/{$leaf2->id}/tv");
        $html = $response->getContent();

        $response->assertOk()->assertSee('Cross Phase School');

        // Row order: rank badge, school name, gold, silver, bronze, grade, total —
        // gold must carry the Phase 1 medal's points (score 90% re-derives to grade A,
        // the platform default table's top tier -- DEFAULT_POINTS['A']['1'] = 8), not 0
        // with everything dumped into the grade column instead.
        $row = substr($html, strpos($html, 'Cross Phase School'));
        $this->assertMatchesRegularExpression('/text-amber-300 text-lg">8</', $row);
        $this->assertMatchesRegularExpression('/text-sky-300 text-lg">0</', $row);
    }

    /**
     * tv()'s per-category medal tally filtered $marks with a plain `=== $key` equality
     * on class_group, never expanding a merge TARGET (aggregation_config.
     * championship_category_map) back to its source categories via FestCategoryMerge::
     * sourceKeysFor() — so a merged-away source category's medal never showed up in the
     * merge target's own board, even though that board's Total Points (from
     * resolveScoreboard()) already correctly folded it in.
     */
    public function test_tv_medal_tally_includes_a_merged_source_categorys_medal(): void
    {
        $this->hub->update(['aggregation_config' => array_merge(
            $this->hub->aggregation_config ?? [],
            ['championship_category_map' => ['category_5' => 'category_3']],
        )]);

        $item = FestEventItem::create([
            'event_id' => $this->north->id, 'title' => 'Merged Source Item', 'category' => 'literary',
            'class_group' => 'category_5', 'participant_type' => 'individual', 'is_enabled' => true,
            'results_published_at' => now(),
        ]);
        $registration = FestRegistration::create([
            'event_id' => $this->north->id, 'item_id' => $item->id,
            'school_id' => $this->northSchool->id, 'status' => 'approved',
        ]);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $this->north->id, 'participant_type' => 'student',
        ]);
        FestMark::create([
            'event_id' => $this->north->id, 'item_id' => $item->id, 'participant_id' => $participant->id,
            'grade' => 'A', 'position' => 1, 'score' => 90,
        ]);

        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/tv");
        $html = $response->getContent();

        $response->assertOk();

        // North Star School appears on the Overall board first, then the (only)
        // category board — 'category_5' collapses into 'category_3', so there is
        // exactly one category board, which is the merge target's own. The LAST
        // occurrence isolates that category board's row.
        $row = substr($html, strrpos($html, 'North Star School'));
        $this->assertDoesNotMatchRegularExpression(
            '/text-amber-300 text-lg">0</', $row,
            "The merged source category's gold medal must count toward the target category's own board."
        );
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
            // PublicFestScoreboardService::scoreboard()'s category branch only counts a
            // mark once its own item has actually published results — matching every
            // other public results path (provisionalScoreboard(), the item-wise tab's
            // marks query). Without this, every test using this helper was only passing
            // because that per-item gate didn't exist yet.
            'results_published_at' => now(),
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
