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

class FestPublicResultsTeamRosterTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $sahodaya;

    private Tenant $schoolA;

    private Tenant $schoolB;

    private FestEvent $event;

    /** @var array<string, SchoolClass> */
    private array $schoolClasses = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Roster Test Sahodaya',
            'domain' => 'roster-test.test',
            'is_active' => true,
        ]);

        $this->schoolA = $this->school('Alpha School');
        $this->schoolB = $this->school('Beta School');

        $this->event = FestEvent::create([
            'tenant_id' => $this->sahodaya->id,
            'title' => 'Roster Test Fest',
            'event_type' => 'kalotsav',
            'conduct_mode' => 'standard',
            'status' => 'completed',
            'results_published' => true,
            'schedule_published' => true,
        ]);

        // Individual item: Alpha takes gold, Beta takes silver. results_published_at is
        // required in its own right now — an item's marks only show on /results once
        // that item itself has published, the whole event's results_published flag no
        // longer being a substitute (see FestPortalController::results()'s $marks query).
        $soloItem = FestEventItem::create([
            'event_id' => $this->event->id,
            'title' => 'Solo Song',
            'participant_type' => 'individual',
            'class_group' => 'hs',
            'is_enabled' => true,
            'results_published_at' => now(),
        ]);
        $this->markSolo($soloItem, $this->schoolA, 'Anu Krishna', 1);
        $this->markSolo($soloItem, $this->schoolB, 'Beena Thomas', 2);

        // Group item: Alpha's trio takes gold. Only the first performer's row is what
        // the mark is attached to — mirrors real judging, where the mark isn't
        // necessarily entered against whichever member a caller thinks of as "first".
        $groupItem = FestEventItem::create([
            'event_id' => $this->event->id,
            'title' => 'Group Dance',
            'participant_type' => 'group',
            'class_group' => 'hs',
            'is_enabled' => true,
            'results_published_at' => now(),
        ]);
        $this->markGroup($groupItem, $this->schoolA, ['Ravi Nair', 'Sita Menon', 'Meera Pillai'], 1);

        // The school-wise points board reads from the published FestResult snapshot,
        // not live from FestMark — mirrors how results are actually published.
        FestResult::create([
            'event_id' => $this->event->id,
            'school_id' => $this->schoolA->id,
            'total_points' => 50,
            'rank' => 1,
            'published_at' => now(),
        ]);
        FestResult::create([
            'event_id' => $this->event->id,
            'school_id' => $this->schoolB->id,
            'total_points' => 20,
            'rank' => 2,
            'published_at' => now(),
        ]);
    }

    public function test_results_school_tab_shows_medal_counts_per_school(): void
    {
        $response = $this->get("http://roster-test.test/fest/{$this->event->id}/results?tab=school");

        $response->assertOk();
        $content = $response->getContent();

        $this->assertMedalCounts($content, $this->schoolA, gold: 2, silver: 0, bronze: 0);
        $this->assertMedalCounts($content, $this->schoolB, gold: 0, silver: 1, bronze: 0);
    }

    public function test_results_item_tab_shows_full_roster_and_group_label_for_team_items(): void
    {
        $response = $this->get("http://roster-test.test/fest/{$this->event->id}/results?tab=item");

        $response->assertOk();
        $response->assertSee('Ravi Nair');
        $response->assertSee('Sita Menon');
        $response->assertSee('Meera Pillai');
        $response->assertSee('Group');
    }

    public function test_results_item_tab_shows_the_items_gender_badge(): void
    {
        $item = FestEventItem::create([
            'event_id' => $this->event->id,
            'title' => 'Girls Solo Dance',
            'participant_type' => 'individual',
            'class_group' => 'hs',
            'gender' => 'female',
            'is_enabled' => true,
            'results_published_at' => now(),
        ]);
        $this->markSolo($item, $this->schoolA, 'Divya Menon', 1);

        $response = $this->get("http://roster-test.test/fest/{$this->event->id}/results?tab=item");

        $response->assertOk();
        $response->assertSee('Girls Solo Dance');
        $response->assertSee('Girls');
    }

    public function test_championship_tab_shows_humanized_category_and_a_link_to_the_students_page(): void
    {
        // The individual championship is computed live off Anu Krishna's existing mark
        // from setUp() (Solo Song, hs, published) — just needs a gender on file to be
        // included at all (see FestIndividualChampionshipService::pointsForEvent()).
        $student = Student::where('name', 'Anu Krishna')->firstOrFail();
        $student->update(['gender' => 'female']);
        FestParticipant::whereHas('student', fn ($q) => $q->where('name', 'Anu Krishna'))
            ->update(['level_registration_number' => 'CHAMP-REF-1']);
        $participant = FestParticipant::whereHas('student', fn ($q) => $q->where('name', 'Anu Krishna'))->firstOrFail();

        $response = $this->get("http://roster-test.test/fest/{$this->event->id}/results?tab=championship");

        $response->assertOk();
        // Humanized label, not the raw enum key stored on the row.
        $response->assertDontSee('>hs<', false);
        $response->assertSee('Classes 8, 9 &amp; 10', false);
        $response->assertSee('Girls');
        // Eye icon links to this student's own public participant page.
        $response->assertSee("/fest/{$this->event->id}/participant/p-{$participant->id}", false);
    }

    /**
     * publicParticipantItems() (FestPublicVisibilityService) used to build results_url
     * off the item's publish gate alone -- a published item with zero marks recorded
     * (published too early, or a no-show item) still got a "View full item results"
     * link into item-results.blade.php's empty "No published results for this item."
     * state. Fixed with the same has-any-marks gate item-finder.blade.php's grid uses.
     */
    public function test_participant_page_hides_results_link_for_a_published_item_with_no_marks(): void
    {
        $noMarksItem = FestEventItem::create([
            'event_id' => $this->event->id,
            'title' => 'No Marks Item',
            'participant_type' => 'individual',
            'class_group' => 'hs',
            'is_enabled' => true,
            'results_published_at' => now(),
        ]);
        $student = Student::where('name', 'Anu Krishna')->firstOrFail();
        FestParticipant::create([
            'registration_id' => FestRegistration::create([
                'event_id' => $this->event->id,
                'item_id' => $noMarksItem->id,
                'school_id' => $this->schoolA->id,
                'status' => 'approved',
            ])->id,
            'event_id' => $this->event->id,
            'student_id' => $student->id,
            'participant_type' => 'student',
        ]);
        $participant = FestParticipant::whereHas('student', fn ($q) => $q->where('name', 'Anu Krishna'))
            ->whereHas('registration', fn ($q) => $q->where('item_id', FestEventItem::where('title', 'Solo Song')->value('id')))
            ->firstOrFail();

        $response = $this->get("http://roster-test.test/fest/{$this->event->id}/participant/p-{$participant->id}");

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('No Marks Item', $content);
        $soloItem = FestEventItem::where('title', 'Solo Song')->firstOrFail();
        $this->assertStringContainsString(route('tenant.fest.item-results', [$this->event->id, $soloItem->id]), $content);
        $this->assertStringNotContainsString(route('tenant.fest.item-results', [$this->event->id, $noMarksItem->id]), $content);
    }

    public function test_empty_championship_uses_an_explanatory_state_instead_of_an_empty_table(): void
    {
        $response = $this->get("http://roster-test.test/fest/{$this->event->id}/results?tab=championship");

        // The "Browse item results"/"School results" buttons this empty state used to
        // duplicate were dropped -- the page's own tab switcher, right above this empty
        // state, already links to both (see the fest public-pages duplicate-link cleanup).
        $response->assertOk()
            ->assertSee('No individual championship standing is published')
            ->assertDontSee('<table', false);
    }

    public function test_item_results_page_shows_full_roster_not_just_one_member(): void
    {
        $groupItem = FestEventItem::where('title', 'Group Dance')->firstOrFail();

        $response = $this->get("http://roster-test.test/fest/{$this->event->id}/items/{$groupItem->id}/results");

        $response->assertOk();
        $response->assertSee('Ravi Nair');
        $response->assertSee('Sita Menon');
        $response->assertSee('Meera Pillai');
    }

    public function test_item_results_winner_roster_only_shows_podium_while_full_results_keeps_every_rank(): void
    {
        $item = FestEventItem::where('title', 'Solo Song')->firstOrFail();
        $this->markSolo($item, $this->schoolB, 'Fourth Place Child', 4);

        $response = $this->get("http://roster-test.test/fest/{$this->event->id}/items/{$item->id}/results");

        $response->assertOk()->assertSee('Fourth Place Child');
        $winnerRosterHtml = Str::before($response->getContent(), 'Full Results');
        $this->assertStringNotContainsString('Fourth Place Child', $winnerRosterHtml);
        $this->assertStringContainsString('Podium finishers · ties included', $winnerRosterHtml);
    }

    /**
     * The public event page's own item grid (show.blade.php) used to render "Results" as
     * a normal, clickable link the moment an item's results_published_at was set, even
     * for an item with zero marks ever recorded (published too early, or a no-show
     * item) -- landing on item-results.blade.php's empty "No published results for this
     * item." state. Fixed to reuse the same visible+has-data guard item-finder.blade.php
     * already applied on its own copy of this grid.
     */
    public function test_show_page_item_grid_disables_results_link_for_a_published_item_with_no_marks(): void
    {
        $unmarkedItem = FestEventItem::create([
            'event_id' => $this->event->id,
            'title' => 'Unmarked Item',
            'participant_type' => 'individual',
            'class_group' => 'hs',
            'is_enabled' => true,
            'results_published_at' => now(),
        ]);

        $response = $this->get("http://roster-test.test/fest/{$this->event->id}");

        $response->assertOk();
        $content = $response->getContent();

        // The item still appears (as a card), but without a clickable Results link --
        // asserting on the absence of an <a> to its results route within that card is
        // fragile against markup changes, so instead assert the disabled-state copy
        // this item's card must show is present, and the "Solo Song" card (which does
        // have marks) still links normally.
        $this->assertStringContainsString('Unmarked Item', $content);
        $this->assertStringContainsString('Published, but no marks recorded yet', $content);
        $soloItem = FestEventItem::where('title', 'Solo Song')->firstOrFail();
        $this->assertStringContainsString(route('tenant.fest.item-results', [$this->event->id, $soloItem->id]), $content);
        $this->assertStringNotContainsString(route('tenant.fest.item-results', [$this->event->id, $unmarkedItem->id]), $content);
    }

    /** The "Item Wise Results" button duplicated "Browse items"' destination exactly; "Detailed Results" duplicated "School results" once published (results() defaults its tab to 'school' then) -- see the fest public-pages duplicate-link cleanup. */
    public function test_show_page_event_services_grid_has_no_duplicate_destination_buttons(): void
    {
        $response = $this->get("http://roster-test.test/fest/{$this->event->id}");

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringNotContainsString('Item Wise Results', $content);
        $this->assertStringContainsString('Category-wise Results', $content);
        $this->assertStringContainsString(route('tenant.fest.results', ['event' => $this->event->id, 'tab' => 'category']), $content);
    }

    public function test_search_consolidates_one_students_items_and_uses_an_unambiguous_link(): void
    {
        $firstItem = FestEventItem::where('title', 'Solo Song')->firstOrFail();
        $secondItem = FestEventItem::create([
            'event_id' => $this->event->id,
            'title' => 'Water Colour',
            'participant_type' => 'individual',
            'class_group' => 'hs',
            'is_enabled' => true,
            'results_published_at' => now(),
        ]);
        $student = $this->student($this->schoolA, 'Aashi P');

        $participants = collect([$firstItem, $secondItem])->map(function (FestEventItem $item) use ($student) {
            $registration = FestRegistration::create([
                'event_id' => $this->event->id,
                'item_id' => $item->id,
                'school_id' => $this->schoolA->id,
                'status' => 'approved',
            ]);

            return FestParticipant::create([
                'registration_id' => $registration->id,
                'event_id' => $this->event->id,
                'student_id' => $student->id,
                'participant_type' => 'student',
                'level_registration_number' => '102',
            ]);
        });

        // A different student owns chest 102. A legacy bare /participant/102 URL will
        // still resolve that chest, but the search result must use p-{id} and therefore
        // open Aashi's page rather than this decoy.
        $decoyItem = FestEventItem::create([
            'event_id' => $this->event->id,
            'title' => 'Decoy Item',
            'participant_type' => 'individual',
            'class_group' => 'hs',
            'is_enabled' => true,
            'results_published_at' => now(),
        ]);
        $decoyRegistration = FestRegistration::create([
            'event_id' => $this->event->id,
            'item_id' => $decoyItem->id,
            'school_id' => $this->schoolB->id,
            'status' => 'approved',
        ]);
        $decoy = FestParticipant::create([
            'registration_id' => $decoyRegistration->id,
            'event_id' => $this->event->id,
            'student_id' => $this->student($this->schoolB, 'Aadhya Abhilash')->id,
            'participant_type' => 'student',
            'chest_no' => 102,
        ]);

        $canonicalRef = 'p-'.$participants->first()->id;
        $search = $this->get("http://roster-test.test/fest/{$this->event->id}/search?q=Aashi");

        $search->assertOk()
            ->assertSee('1 participant found')
            ->assertSee('Solo Song')
            ->assertSee('Water Colour')
            ->assertSee("/participant/{$canonicalRef}", false)
            ->assertDontSee('/participant/102', false);

        $this->get("http://roster-test.test/fest/{$this->event->id}/search?q=102")
            ->assertOk()
            ->assertSee('2 participants found')
            ->assertSee('Aashi P')
            ->assertSee('Aadhya Abhilash')
            ->assertSee("/participant/{$canonicalRef}", false)
            ->assertSee("/participant/p-{$decoy->id}", false);

        $this->get("http://roster-test.test/fest/{$this->event->id}/participant/{$canonicalRef}")
            ->assertOk()
            ->assertSee('AASHI P')
            ->assertDontSee('AADHYA ABHILASH');
    }

    /**
     * The school-wise tab shows a points summary table plus an expandable roster card
     * per school (see resources/views/public/fest/results.blade.php) — there's no
     * aggregated gold/silver/bronze count table anymore; medal tallies are conveyed per
     * item via rank-N.webp badges inside each school's own card, so that's what this
     * counts. Matched by school id (via data-school-id), not name, since
     * Tenant::getNameAttribute() uppercases school-type tenant names on read.
     */
    private function assertMedalCounts(string $html, Tenant $school, int $gold, int $silver, int $bronze): void
    {
        $cardPattern = '/data-school-winner-card data-school-id="'.preg_quote($school->id, '/').'">(.*?)<\/article>/s';
        $this->assertMatchesRegularExpression($cardPattern, $html, "Could not find roster card for {$school->name} ({$school->id})");
        preg_match($cardPattern, $html, $cardMatch);
        $card = $cardMatch[1];

        $this->assertSame($gold, substr_count($card, 'rank-1.webp'), "Gold count mismatch for {$school->name}");
        $this->assertSame($silver, substr_count($card, 'rank-2.webp'), "Silver count mismatch for {$school->name}");
        $this->assertSame($bronze, substr_count($card, 'rank-3.webp'), "Bronze count mismatch for {$school->name}");
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

    private function student(Tenant $school, string $name): Student
    {
        $class = $this->schoolClasses[$school->id] ??= SchoolClass::create([
            'tenant_id' => $school->id,
            'name' => '10',
        ]);

        return Student::create([
            'tenant_id' => $school->id,
            'school_class_id' => $class->id,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function markSolo(FestEventItem $item, Tenant $school, string $studentName, int $position): void
    {
        $registration = FestRegistration::create([
            'event_id' => $item->event_id,
            'item_id' => $item->id,
            'school_id' => $school->id,
            'status' => 'approved',
        ]);

        $student = $this->student($school, $studentName);

        $participant = FestParticipant::create([
            'registration_id' => $registration->id,
            'event_id' => $item->event_id,
            'student_id' => $student->id,
            'participant_type' => 'student',
        ]);

        FestMark::create([
            'event_id' => $item->event_id,
            'item_id' => $item->id,
            'participant_id' => $participant->id,
            'grade' => 'A',
            'position' => $position,
            'score' => 90,
        ]);
    }

    private function markGroup(FestEventItem $item, Tenant $school, array $memberNames, int $position): void
    {
        $registration = FestRegistration::create([
            'event_id' => $item->event_id,
            'item_id' => $item->id,
            'school_id' => $school->id,
            'status' => 'approved',
        ]);

        $participants = [];
        foreach ($memberNames as $name) {
            $student = $this->student($school, $name);
            $participants[] = FestParticipant::create([
                'registration_id' => $registration->id,
                'event_id' => $item->event_id,
                'student_id' => $student->id,
                'participant_type' => 'student',
            ]);
        }

        FestMark::create([
            'event_id' => $item->event_id,
            'item_id' => $item->id,
            'participant_id' => $participants[0]->id,
            'grade' => 'A',
            'position' => $position,
            'score' => 88,
        ]);
    }
}
