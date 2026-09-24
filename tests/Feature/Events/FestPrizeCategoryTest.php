<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestPrizeCategory;
use App\Models\FestRegistration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestPrizeCategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Prize categories at Sahodaya level — the counterpart of StatePrizeCategoryTest.
 *
 * A Sahodaya event competes School against School, so these trophies crown a student or a school and
 * there is no Sahodaya title to give.
 */
class FestPrizeCategoryTest extends TestCase
{
    use RefreshDatabase;

    private FestEvent $event;

    private FestEventItem $dance;

    private FestEventItem $music;

    protected function setUp(): void
    {
        parent::setUp();

        Tenant::create(['id' => 'sahodaya-1', 'name' => 'Sahodaya One', 'type' => 'sahodaya']);
        Tenant::create(['id' => 'school-1', 'name' => 'St Joseph HSS', 'type' => 'school', 'parent_id' => 'sahodaya-1']);
        Tenant::create(['id' => 'school-2', 'name' => 'Holy Family HSS', 'type' => 'school', 'parent_id' => 'sahodaya-1']);

        $this->event = FestEvent::create([
            'tenant_id' => 'sahodaya-1', 'title' => 'Sahodaya Kalotsavam 2026',
            'event_type' => 'kalolsavam', 'level_round' => 'sahodaya',
            // Event-wide publication, so every item counts without per-item flags.
            'results_published' => true, 'status' => 'published',
            'scoring_preset' => 'confed_kalotsav',
        ]);

        $this->dance = FestEventItem::create([
            'event_id' => $this->event->id, 'title' => 'Bharatanatyam',
            'item_code' => 'DN01', 'category' => 'dance', 'participant_type' => 'individual',
        ]);
        $this->music = FestEventItem::create([
            'event_id' => $this->event->id, 'title' => 'Light Music',
            'item_code' => 'LM01', 'category' => 'music', 'participant_type' => 'individual',
        ]);
    }

    private function prizes(): FestPrizeCategoryService
    {
        return app(FestPrizeCategoryService::class);
    }

    private function winner(FestEventItem $item, string $name, int $position, float $score, string $schoolId = 'school-1'): FestMark
    {
        $class = SchoolClass::firstOrCreate(['tenant_id' => $schoolId, 'name' => 'Class 10']);
        $student = Student::create(['name' => $name, 'tenant_id' => $schoolId, 'school_class_id' => $class->id]);

        $registration = FestRegistration::create([
            'event_id' => $this->event->id, 'item_id' => $item->id,
            'school_id' => $schoolId, 'status' => 'approved',
        ]);
        $participant = FestParticipant::create(['registration_id' => $registration->id, 'student_id' => $student->id]);

        return FestMark::create([
            'event_id' => $this->event->id, 'item_id' => $item->id,
            'participant_id' => $participant->id, 'position' => $position,
            'score' => $score, 'grade' => 'A',
        ]);
    }

    private function category(array $overrides = []): FestPrizeCategory
    {
        return $this->prizes()->save($this->event, array_merge([
            'name' => 'Dance Champion',
            'awards' => ['individual', 'school'],
            'honour_count' => 3,
        ], $overrides));
    }

    public function test_a_category_must_award_at_least_one_title(): void
    {
        $this->expectException(ValidationException::class);

        $this->prizes()->save($this->event, ['name' => 'Crowns nobody', 'awards' => []]);
    }

    public function test_an_item_can_count_towards_several_categories(): void
    {
        $dance = $this->category(['name' => 'Dance Champion']);
        $stage = $this->category(['name' => 'Stage Overall']);

        $this->prizes()->assignItems($this->event, $dance->id, [$this->dance->id]);
        $this->prizes()->assignItems($this->event, $stage->id, [$this->dance->id, $this->music->id]);

        $this->assertSame(1, $this->prizes()->itemIdsFor($this->event, $dance->fresh('items'))->count());
        $this->assertSame(2, $this->prizes()->itemIdsFor($this->event, $stage->fresh('items'))->count());
    }

    public function test_an_overall_category_covers_every_item_without_assignments(): void
    {
        $overall = $this->category(['name' => 'Overall Champion', 'is_overall' => true]);

        $this->assertSame(2, $this->prizes()->itemIdsFor($this->event, $overall)->count());
    }

    public function test_an_overall_category_refuses_item_assignments(): void
    {
        $overall = $this->category(['name' => 'Overall Champion', 'is_overall' => true]);

        $this->expectException(ValidationException::class);
        $this->prizes()->assignItems($this->event, $overall->id, [$this->dance->id]);
    }

    public function test_an_item_from_another_event_cannot_be_assigned(): void
    {
        $otherEvent = FestEvent::create([
            'tenant_id' => 'sahodaya-1', 'title' => 'Another fest',
            'event_type' => 'kalolsavam', 'status' => 'published',
        ]);
        $foreign = FestEventItem::create([
            'event_id' => $otherEvent->id, 'title' => 'Foreign', 'item_code' => 'XX01',
        ]);

        $category = $this->category();

        $this->assertSame(1, $this->prizes()->assignItems($this->event, $category->id, [$this->dance->id, $foreign->id]));
    }

    public function test_a_student_champion_is_crowned_with_their_school(): void
    {
        $this->winner($this->dance, 'Winner', 1, 95, 'school-1');
        $this->winner($this->dance, 'Runner', 2, 88, 'school-2');

        $category = $this->category();
        $this->prizes()->assignItems($this->event, $category->id, [$this->dance->id]);

        $rows = $this->prizes()->standings($this->event, $category->fresh('items'))['individual'];

        $this->assertSame('Winner', $rows[0]['name']);
        $this->assertSame(1, $rows[0]['rank']);
        $this->assertSame('St Joseph HSS', $rows[0]['school']);
        $this->assertGreaterThan(0, $rows[0]['points']);
    }

    public function test_a_school_champion_sums_every_counted_item(): void
    {
        $this->winner($this->dance, 'A', 1, 95, 'school-1');
        $this->winner($this->music, 'B', 1, 95, 'school-2');
        $this->winner($this->music, 'C', 2, 88, 'school-1');

        $overall = $this->category(['name' => 'Overall', 'is_overall' => true, 'awards' => ['school']]);
        $rows = $this->prizes()->standings($this->event, $overall)['school'];

        // St Joseph took a first and a second across two items; Holy Family took one first.
        $this->assertSame('St Joseph HSS', $rows[0]['name']);
        $this->assertSame(2, $rows[0]['items']);
    }

    public function test_a_category_crowns_only_the_titles_it_awards(): void
    {
        $this->winner($this->dance, 'Winner', 1, 95);

        $category = $this->category(['name' => 'School only', 'awards' => ['school']]);
        $this->prizes()->assignItems($this->event, $category->id, [$this->dance->id]);

        $standings = $this->prizes()->standings($this->event, $category->fresh('items'));

        $this->assertNull($standings['individual']);
        $this->assertNotNull($standings['school']);
    }

    public function test_points_come_from_the_events_own_rules(): void
    {
        // A first place under the confed table is worth more than a third.
        $this->winner($this->dance, 'First', 1, 95, 'school-1');
        $this->winner($this->dance, 'Third', 3, 75, 'school-2');

        $category = $this->category(['awards' => ['individual']]);
        $this->prizes()->assignItems($this->event, $category->id, [$this->dance->id]);

        $rows = collect($this->prizes()->standings($this->event, $category->fresh('items'))['individual'])
            ->keyBy('name');

        $this->assertGreaterThan($rows['Third']['points'], $rows['First']['points']);
    }

    public function test_a_true_tie_shares_the_rank(): void
    {
        $this->winner($this->dance, 'A', 1, 95, 'school-1');
        $this->winner($this->music, 'B', 1, 95, 'school-2');

        $overall = $this->category(['name' => 'Overall', 'is_overall' => true, 'awards' => ['school']]);
        $rows = $this->prizes()->standings($this->event, $overall)['school'];

        $this->assertSame(1, $rows[0]['rank']);
        $this->assertSame(1, $rows[1]['rank']);
        $this->assertTrue($rows[0]['is_tied']);
    }

    public function test_an_unpublished_event_counts_only_items_published_on_their_own(): void
    {
        $this->event->forceFill(['results_published' => false])->save();
        $this->winner($this->dance, 'Hidden', 1, 95);
        $this->winner($this->music, 'Shown', 1, 95, 'school-2');
        $this->music->forceFill(['results_published_at' => now()])->save();

        $overall = $this->category(['name' => 'Overall', 'is_overall' => true, 'awards' => ['individual']]);
        $standings = $this->prizes()->standings($this->event->fresh(), $overall);

        // A trophy cannot name a winner the public cannot yet see.
        $this->assertSame(1, $standings['items_counted']);
        $this->assertSame(['Shown'], array_column($standings['individual'], 'name'));
    }

    public function test_an_inactive_category_is_left_out_of_the_computed_standings(): void
    {
        $this->winner($this->dance, 'A', 1, 95);
        $this->category(['name' => 'Retired', 'is_active' => false, 'is_overall' => true]);

        $this->assertCount(0, $this->prizes()->allStandings($this->event));
    }

    public function test_deleting_a_category_takes_its_assignments_with_it(): void
    {
        $category = $this->category();
        $this->prizes()->assignItems($this->event, $category->id, [$this->dance->id]);

        $this->prizes()->delete($this->event, $category->id);

        $this->assertSame(0, \App\Models\FestPrizeCategoryItem::where('prize_category_id', $category->id)->count());
    }

    public function test_the_awards_a_sahodaya_trophy_can_give_exclude_the_sahodaya_title(): void
    {
        // The State competes Sahodaya against Sahodaya; a Sahodaya event competes schools, so there is
        // no Sahodaya title here — asserted so the two levels cannot silently drift.
        $this->assertSame(['individual', 'school'], array_keys(FestPrizeCategory::AWARDS));
    }
}
