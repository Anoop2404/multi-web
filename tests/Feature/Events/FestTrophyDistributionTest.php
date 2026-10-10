<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestTrophy;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestIndividualChampionshipService;
use App\Services\Events\FestTrophyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FestTrophyDistributionTest extends TestCase
{
    use RefreshDatabase;

    private FestEvent $parentEvent;
    private FestEvent $childEvent;
    private Tenant $sahodaya;
    private Tenant $school1;
    private Tenant $school2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sahodaya = Tenant::create(['id' => 'saho-1', 'name' => 'Kochi Metro Sahodaya', 'type' => 'sahodaya']);
        $this->school1 = Tenant::create(['id' => 'sch-1', 'name' => 'St Marys HSS', 'type' => 'school', 'parent_id' => 'saho-1']);
        $this->school2 = Tenant::create(['id' => 'sch-2', 'name' => 'Bhavans Vidya Mandir', 'type' => 'school', 'parent_id' => 'saho-1']);

        $this->parentEvent = FestEvent::create([
            'tenant_id' => 'saho-1',
            'title' => 'Metro Kalotsav 2025',
            'event_type' => 'kalolsavam',
            'results_published' => true,
            'status' => 'published',
            'scoring_preset' => 'confed_kalotsav',
        ]);

        $this->childEvent = FestEvent::create([
            'tenant_id' => 'saho-1',
            'parent_event_id' => $this->parentEvent->id,
            'title' => 'Metro Kalotsav 2025 — Zone A',
            'event_type' => 'kalolsavam',
            'results_published' => true,
            'status' => 'published',
            'scoring_preset' => 'confed_kalotsav',
        ]);
    }

    private function trophyService(): FestTrophyService
    {
        return app(FestTrophyService::class);
    }

    private function championshipService(): FestIndividualChampionshipService
    {
        return app(FestIndividualChampionshipService::class);
    }

    public function test_team_standings_show_all_performers_and_one_entry_per_group(): void
    {
        $item = FestEventItem::create(['event_id' => $this->parentEvent->id, 'title' => 'Group Dance',
            'item_code' => 'TEAM1', 'participant_type' => 'group']);
        $registration = FestRegistration::create(['event_id' => $this->parentEvent->id, 'item_id' => $item->id,
            'school_id' => $this->school1->id, 'status' => 'approved']);
        $group = \App\Models\FestGroup::create(['event_id' => $this->parentEvent->id, 'registration_id' => $registration->id]);
        $class = SchoolClass::create(['tenant_id' => $this->school1->id, 'name' => '10A']);
        foreach (['Aravind', 'Rahul'] as $name) {
            $student = Student::create(['tenant_id' => $this->school1->id, 'name' => $name, 'school_class_id' => $class->id]);
            $participant = FestParticipant::create(['registration_id' => $registration->id, 'event_id' => $this->parentEvent->id,
                'group_id' => $group->id, 'student_id' => $student->id, 'participant_role' => 'performer']);
            FestMark::create(['event_id' => $this->parentEvent->id, 'item_id' => $item->id,
                'participant_id' => $participant->id, 'position' => 1, 'score' => 95]);
        }
        $trophy = new FestTrophy(['item_id' => $item->id, 'position' => 1]);
        $method = new \ReflectionMethod(FestTrophyService::class, 'resolveItemWinner');
        $cache = [];
        $service = $this->trophyService();
        $args = [$this->parentEvent, $trophy, false, &$cache];
        $winner = $method->invokeArgs($service, $args);
        \Illuminate\Support\Facades\DB::enableQueryLog();
        \Illuminate\Support\Facades\DB::flushQueryLog();
        $sameWinner = $method->invokeArgs($service, $args);
        $this->assertSame($winner, $sameWinner);
        $this->assertCount(0, \Illuminate\Support\Facades\DB::getQueryLog(), 'Other trophy ranks must reuse the item standings.');
        \Illuminate\Support\Facades\DB::disableQueryLog();
        $this->assertSame($this->school1->name, $winner['name']);
        $this->assertEqualsCanonicalizing(['Aravind', 'Rahul'], $winner['team_members']);
        $this->assertCount(1, $winner['top_ten']);
        $this->assertSame(1, $winner['top_ten'][0]['rank']);
    }

    public function test_school_trophies_count_each_group_once_and_individuals_separately(): void
    {
        $marks = collect();
        $items = collect();
        foreach (['group', 'individual'] as $type) {
            $item = FestEventItem::create(['event_id' => $this->parentEvent->id, 'title' => $type.' Entry',
                'participant_type' => $type, 'class_group' => 'category_3', 'results_published_at' => now(), 'results_hidden' => false]);
            $items->push($item);
            $registration = FestRegistration::create(['event_id' => $this->parentEvent->id, 'item_id' => $item->id,
                'school_id' => $this->school1->id, 'status' => 'approved']);
            // No group_id: legacy group registrations must still count just once.
            foreach (['performer', 'performer', 'standby'] as $role) {
                $participant = FestParticipant::create(['event_id' => $this->parentEvent->id,
                    'registration_id' => $registration->id, 'participant_role' => $role]);
                $marks->push(FestMark::create(['event_id' => $this->parentEvent->id, 'item_id' => $item->id,
                    'participant_id' => $participant->id, 'position' => 1, 'grade' => 'A', 'score' => 95]));
            }
        }
        $points = app(\App\Services\Events\FestGradePointService::class);
        $expected = $points->pointsForMark($this->parentEvent, $marks[0])
            + $points->pointsForMark($this->parentEvent, $marks[3])
            + $points->pointsForMark($this->parentEvent, $marks[4]);
        $trophies = collect([
            new FestTrophy(['trophy_type' => FestTrophy::TYPE_OVERALL, 'position' => 1]),
            new FestTrophy(['trophy_type' => FestTrophy::TYPE_CATEGORY, 'category_key' => 'category_3', 'position' => 1]),
            new FestTrophy(['trophy_type' => FestTrophy::TYPE_ITEM_GROUP, 'item_ids' => $items->pluck('id')->all(), 'position' => 1]),
        ]);
        $resolved = $this->trophyService()->resolveWinners($this->parentEvent, $trophies);
        foreach ($resolved as $row) {
            $this->assertTrue($row['has_winner']);
            $this->assertEquals($expected, $row['winner']['points']);
        }
    }

    public function test_open_group_item_does_not_replace_the_individual_championship_category(): void
    {
        $solo = FestEventItem::create(['event_id' => $this->parentEvent->id, 'title' => 'Essay Writing',
            'class_group' => 'category_3', 'participant_type' => 'individual', 'results_published_at' => now(), 'results_hidden' => false]);
        $group = FestEventItem::create(['event_id' => $this->parentEvent->id, 'title' => 'Thiruvathirakali',
            'class_group' => 'open', 'participant_type' => 'group', 'results_published_at' => now(), 'results_hidden' => false]);
        $class = SchoolClass::create(['tenant_id' => $this->school1->id, 'name' => '10A']);
        // Verify both database iteration orders: solo then group, and group then solo.
        foreach ([[$solo, $group], [$group, $solo]] as $index => $items) {
            $student = Student::create(['tenant_id' => $this->school1->id, 'name' => 'Category student '.$index,
                'gender' => 'female', 'school_class_id' => $class->id]);
            foreach ($items as $item) {
                $registration = FestRegistration::create(['event_id' => $this->parentEvent->id, 'item_id' => $item->id,
                    'school_id' => $this->school1->id, 'status' => 'approved']);
                $participant = FestParticipant::create(['registration_id' => $registration->id,
                    'event_id' => $this->parentEvent->id, 'student_id' => $student->id, 'participant_role' => 'performer']);
                FestMark::create(['event_id' => $this->parentEvent->id, 'item_id' => $item->id,
                    'participant_id' => $participant->id, 'position' => 3, 'grade' => 'A', 'score' => 80]);
            }
        }
        $rows = $this->championshipService()->pointsForEvent($this->parentEvent);
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertSame('hs', $row->category);
            $this->assertGreaterThan(0, $row->points);
            $this->assertGreaterThan(0, $row->group_points);
        }
    }

    public function test_can_seed_kochi_metro_75_trophies_preset(): void
    {
        $count = $this->trophyService()->seedKochiMetroPreset($this->parentEvent);

        $this->assertEquals(75, $count);
        $this->assertDatabaseCount('fest_trophies', 75);

        // Check trophy #1: Ever-rolling overall points
        $trophy1 = FestTrophy::where('event_id', $this->parentEvent->id)->where('trophy_no', 1)->first();
        $this->assertNotNull($trophy1);
        $this->assertTrue($trophy1->is_rolling);
        $this->assertEquals('overall', $trophy1->trophy_type);
        $this->assertEquals(1, $trophy1->position);

        // Check trophy #4: Category I First position
        $trophy4 = FestTrophy::where('event_id', $this->parentEvent->id)->where('trophy_no', 4)->first();
        $this->assertNotNull($trophy4);
        $this->assertEquals('category', $trophy4->trophy_type);
        $this->assertEquals('category_1', $trophy4->category_key);
        $this->assertEquals(1, $trophy4->position);

        // Check trophy #22: Music items
        $trophy22 = FestTrophy::where('event_id', $this->parentEvent->id)->where('trophy_no', 22)->first();
        $this->assertNotNull($trophy22);
        $this->assertEquals('item_group', $trophy22->trophy_type);
        $this->assertEquals('Music items', $trophy22->item_group_name);

        // Check trophy #25: One Act Play
        $trophy25 = FestTrophy::where('event_id', $this->parentEvent->id)->where('trophy_no', 25)->first();
        $this->assertNotNull($trophy25);
        $this->assertEquals('item', $trophy25->trophy_type);
        $this->assertEquals('One Act Play', $trophy25->item_name_pattern);

        // Check trophy #58: Art items
        $trophy58 = FestTrophy::where('event_id', $this->parentEvent->id)->where('trophy_no', 58)->first();
        $this->assertNotNull($trophy58);
        $this->assertEquals('item_group', $trophy58->trophy_type);
        $this->assertEquals('Art items', $trophy58->item_group_name);
    }

    public function test_child_event_inherits_trophies_from_parent(): void
    {
        $this->trophyService()->seedKochiMetroPreset($this->parentEvent);

        $inheritedCount = $this->trophyService()->copyFromParent($this->childEvent);

        $this->assertEquals(75, $inheritedCount);
        $this->assertEquals(75, FestTrophy::where('event_id', $this->childEvent->id)->count());
    }

    public function test_parent_event_can_push_trophies_to_child_events(): void
    {
        $this->trophyService()->seedKochiMetroPreset($this->parentEvent);

        $pushedCount = $this->trophyService()->pushToChildEvents($this->parentEvent);

        $this->assertEquals(75, $pushedCount);
        $this->assertEquals(75, FestTrophy::where('event_id', $this->childEvent->id)->count());
    }

    public function test_trophy_winners_resolution_overall_category_and_item(): void
    {
        $playItem = FestEventItem::create([
            'event_id' => $this->parentEvent->id,
            'title' => 'One Act Play',
            'item_code' => 'DR01',
            'class_group' => 'category_3',
            'category' => 'drama',
            'participant_type' => 'group',
            'results_published_at' => now(),
            'results_hidden' => false,
        ]);

        $musicItem = FestEventItem::create([
            'event_id' => $this->parentEvent->id,
            'title' => 'Classical Music (Karnatic)',
            'item_code' => 'MS01',
            'class_group' => 'category_3',
            'category' => 'music',
            'participant_type' => 'individual',
            'results_published_at' => now(),
            'results_hidden' => false,
        ]);

        $class = SchoolClass::create(['tenant_id' => 'sch-1', 'name' => '10A']);
        $student = Student::create(['tenant_id' => 'sch-1', 'name' => 'Aravind Nair', 'gender' => 'male', 'school_class_id' => $class->id]);

        $reg1 = FestRegistration::create(['event_id' => $this->parentEvent->id, 'item_id' => $playItem->id, 'school_id' => 'sch-1', 'status' => 'approved']);
        $p1 = FestParticipant::create(['registration_id' => $reg1->id, 'student_id' => $student->id, 'chest_no' => '101']);
        FestMark::create(['event_id' => $this->parentEvent->id, 'item_id' => $playItem->id, 'participant_id' => $p1->id, 'position' => 1, 'score' => 95, 'grade' => 'A']);

        $reg2 = FestRegistration::create(['event_id' => $this->parentEvent->id, 'item_id' => $musicItem->id, 'school_id' => 'sch-1', 'status' => 'approved']);
        $p2 = FestParticipant::create(['registration_id' => $reg2->id, 'student_id' => $student->id, 'chest_number' => '101']);
        FestMark::create(['event_id' => $this->parentEvent->id, 'item_id' => $musicItem->id, 'participant_id' => $p2->id, 'position' => 1, 'score' => 92, 'grade' => 'A']);

        $this->trophyService()->seedKochiMetroPreset($this->parentEvent);

        $resolved = $this->trophyService()->resolveWinners($this->parentEvent);

        // Trophy 1: Overall 1st place -> St Marys HSS
        $r1 = $resolved->firstWhere('trophy.trophy_no', 1);
        $this->assertTrue($r1['has_winner']);
        $this->assertEquals('St Marys HSS', $r1['winner']['name']);

        // Trophy 10: Category-III 1st place -> St Marys HSS
        $r10 = $resolved->firstWhere('trophy.trophy_no', 10);
        $this->assertTrue($r10['has_winner']);
        $this->assertEquals('St Marys HSS', $r10['winner']['name']);

        // Trophy 22: Music items 1st place -> St Marys HSS
        $r22 = $resolved->firstWhere('trophy.trophy_no', 22);
        $this->assertTrue($r22['has_winner']);
        $this->assertEquals('St Marys HSS', $r22['winner']['name']);

        // Trophy 25: One Act Play 1st place -> St Marys HSS
        $r25 = $resolved->firstWhere('trophy.trophy_no', 25);
        $this->assertTrue($r25['has_winner']);
        $this->assertStringContainsString('St Marys HSS', $r25['winner']['name']);
        $this->assertEquals('101', $r25['winner']['chest_no']);
    }

    public function test_individual_championship_builder_rules_and_breakdown(): void
    {
        $class = SchoolClass::create(['tenant_id' => 'sch-1', 'name' => '10A']);
        $student = Student::create(['tenant_id' => 'sch-1', 'name' => 'Rahul Menon', 'gender' => 'male', 'school_class_id' => $class->id]);

        $item1 = FestEventItem::create([
            'event_id' => $this->parentEvent->id, 'title' => 'Elocution English',
            'class_group' => 'category_3', 'participant_type' => 'individual',
            'results_published_at' => now(), 'results_hidden' => false,
        ]);
        $item2 = FestEventItem::create([
            'event_id' => $this->parentEvent->id, 'title' => 'Essay Writing English',
            'class_group' => 'category_3', 'participant_type' => 'individual',
            'results_published_at' => now(), 'results_hidden' => false,
        ]);

        $reg1 = FestRegistration::create(['event_id' => $this->parentEvent->id, 'item_id' => $item1->id, 'school_id' => 'sch-1', 'status' => 'approved']);
        $p1 = FestParticipant::create(['registration_id' => $reg1->id, 'student_id' => $student->id]);
        FestMark::create(['event_id' => $this->parentEvent->id, 'item_id' => $item1->id, 'participant_id' => $p1->id, 'position' => 1, 'score' => 90, 'grade' => 'A']);

        $reg2 = FestRegistration::create(['event_id' => $this->parentEvent->id, 'item_id' => $item2->id, 'school_id' => 'sch-1', 'status' => 'approved']);
        $p2 = FestParticipant::create(['registration_id' => $reg2->id, 'student_id' => $student->id]);
        FestMark::create(['event_id' => $this->parentEvent->id, 'item_id' => $item2->id, 'participant_id' => $p2->id, 'position' => 2, 'score' => 85, 'grade' => 'A']);

        // Check champions summary (default title Kalaprathibha)
        $summary = $this->championshipService()->championsSummary($this->parentEvent);
        $this->assertEquals('Kalaprathibha', $summary['config']['male_title']);
        $this->assertEquals('Kalathilakam', $summary['config']['female_title']);
        $this->assertNotEmpty($summary['category_champions']);

        $cat3Champ = collect($summary['category_champions'])->first();
        $this->assertNotNull($cat3Champ['male_champion']);
        $this->assertEquals('Rahul Menon', $cat3Champ['male_champion']['student']['name']);
        $this->assertEquals('Kalaprathibha', $cat3Champ['male_champion']['title']);

        // Student item breakdown
        $breakdown = $this->championshipService()->studentItemBreakdown($this->parentEvent, $student->id);
        $this->assertEquals('Rahul Menon', $breakdown['student']['name']);
        $this->assertCount(2, $breakdown['items']);
        $this->assertEquals(1, $breakdown['firsts_count']);
    }
}
