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

        $this->assertMedalRow($content, 'Alpha School', gold: 2, silver: 0, bronze: 0);
        $this->assertMedalRow($content, 'Beta School', gold: 0, silver: 1, bronze: 0);
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

    public function test_item_results_page_shows_full_roster_not_just_one_member(): void
    {
        $groupItem = FestEventItem::where('title', 'Group Dance')->firstOrFail();

        $response = $this->get("http://roster-test.test/fest/{$this->event->id}/items/{$groupItem->id}/results");

        $response->assertOk();
        $response->assertSee('Ravi Nair');
        $response->assertSee('Sita Menon');
        $response->assertSee('Meera Pillai');
    }

    private function assertMedalRow(string $html, string $schoolName, int $gold, int $silver, int $bronze): void
    {
        $pattern = '/'.preg_quote($schoolName, '/').'<\/td>\s*<td[^>]*>\s*(\d+)\s*<\/td>\s*<td[^>]*>\s*(\d+)\s*<\/td>\s*<td[^>]*>\s*(\d+)\s*<\/td>/s';
        $this->assertMatchesRegularExpression($pattern, $html, "Could not find medal row for {$schoolName}");
        preg_match($pattern, $html, $matches);
        $this->assertSame(
            [$gold, $silver, $bronze],
            [(int) $matches[1], (int) $matches[2], (int) $matches[3]],
            "Medal counts mismatch for {$schoolName}"
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
