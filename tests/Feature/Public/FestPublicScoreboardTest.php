<?php

namespace Tests\Feature\Public;

use App\Models\FestEvent;
use App\Models\FestEventItem;
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
        // markCategoryWinner() stores grade=A with score=80, but pointsForMark()
        // re-derives the effective grade from score first — 80% clears the A+ band
        // (>=70%) under the default Kalotsavam scale — so with no FestPointRule
        // configured this resolves through the default CKSC-style table to A+'s
        // 10 points, not A's 8.
        $response->assertSeeInOrder(['North Poetry', '10'], false);
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
    }

    public function test_school_detail_page_404s_for_a_school_with_no_results(): void
    {
        $response = $this->get("http://public-scoreboard.test/fest/{$this->north->id}/results/schools/{$this->southSchool->id}");

        $response->assertNotFound();
    }

    public function test_school_detail_page_scopes_the_roster_and_total_to_a_selected_category(): void
    {
        // hs item: grade A, position 1, score 80 — same as markCategoryWinner(), which
        // re-derives to grade A+ (score-based re-derivation, see the comment on
        // test_individual_tab_shows_points_alongside_position) and resolves to 10 points.
        $this->markCategoryWinner($this->north, $this->northSchool, 'North Poetry');

        // lp item: grade B, position 1, no score (so grade stays literally 'B', no
        // re-derivation) — DEFAULT_POINTS['B']['1'] for an individual item is 5.
        $lpItem = FestEventItem::create([
            'event_id' => $this->north->id, 'title' => 'North LP Item', 'category' => 'literary',
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
        $filtered->assertSee('text-2xl font-mono font-extrabold text-amber-400">10 <small', false);
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
        ]);
        $reg1 = FestRegistration::create(['event_id' => $this->north->id, 'item_id' => $laterCategoryItem->id, 'school_id' => $this->northSchool->id, 'status' => 'approved']);
        $p1 = FestParticipant::create(['registration_id' => $reg1->id, 'event_id' => $this->north->id, 'participant_type' => 'student']);
        FestMark::create(['event_id' => $this->north->id, 'item_id' => $laterCategoryItem->id, 'participant_id' => $p1->id, 'grade' => 'A', 'position' => 1, 'score' => 80]);

        // Position 3 but in a category that sorts BEFORE — should still appear first.
        $earlierCategoryItem = FestEventItem::create([
            'event_id' => $this->north->id, 'title' => 'Aaa Early Item', 'category' => 'literary',
            'class_group' => 'aaa_category', 'participant_type' => 'individual', 'is_enabled' => true,
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
        ]);
        $registration = FestRegistration::create([
            'event_id' => $this->north->id, 'item_id' => $nonWinningItem->id,
            'school_id' => $this->northSchool->id, 'status' => 'approved',
        ]);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $this->north->id, 'participant_type' => 'student',
        ]);
        FestMark::create([
            'event_id' => $this->north->id, 'item_id' => $nonWinningItem->id, 'participant_id' => $participant->id,
            'grade' => 'B', 'position' => 4, 'score' => 55,
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
        // Score=80 re-derives to grade A+ (>=70% band) before points are looked up, so
        // with no FestPointRule configured this resolves to A+'s default value, 10 —
        // see the matching comment on test_school_tab_lists_a_winner_roster_with_points_per_school.
        $response->assertSeeInOrder(['North Poetry', '10'], false);
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
