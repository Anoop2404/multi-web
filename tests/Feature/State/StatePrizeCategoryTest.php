<?php

namespace Tests\Feature\State;

use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\PlatformState;
use App\Models\PlatformUser;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestMark;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateFestRegistration;
use App\Models\State\StatePrizeCategory;
use App\Models\State\StateSahodaya;
use App\Services\State\Fest\StateGradingService;
use App\Services\State\Fest\StatePrizeCategoryService;
use App\Services\State\Fest\StateResultService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Prize categories — trophies over groups of items, crowning an individual, a school or a Sahodaya.
 */
class StatePrizeCategoryTest extends TestCase
{
    use RefreshDatabase;

    private PlatformState $state;

    private StateFestEvent $event;

    private FestStateProgramItem $dance;

    private FestStateProgramItem $music;

    private StateSahodaya $first;

    private StateSahodaya $second;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Artisan::call('state:migrate');

        $this->state = PlatformState::create(['code' => 'KL', 'name' => 'Kerala', 'is_active' => true]);
        $program = FestStateProgram::create([
            'title' => 'Kerala State Kalotsavam 2026', 'state_id' => $this->state->id,
            'event_type' => 'kalolsavam', 'conduct_levels' => ['state'], 'status' => 'published',
        ]);
        $this->event = StateFestEvent::create([
            'state_program_id' => $program->id, 'state_id' => $this->state->id,
            'name' => 'State Finals', 'slug' => 'finals', 'status' => 'active',
        ]);

        $this->dance = FestStateProgramItem::create([
            'state_program_id' => $program->id, 'title' => 'Bharatanatyam', 'item_code' => 'DN01',
            'category' => 'dance', 'qualify_count' => 3, 'participant_type' => 'individual',
        ]);
        $this->music = FestStateProgramItem::create([
            'state_program_id' => $program->id, 'title' => 'Light Music', 'item_code' => 'LM01',
            'category' => 'music', 'qualify_count' => 3, 'participant_type' => 'individual',
        ]);

        $this->first = StateSahodaya::create([
            'id' => (string) Str::uuid(), 'state_id' => $this->state->id, 'name' => 'Malappuram Sahodaya',
            'district' => 'Malappuram', 'origin' => StateSahodaya::ORIGIN_MANAGED, 'tenant_id' => (string) Str::uuid(),
        ]);
        $this->second = StateSahodaya::create([
            'id' => (string) Str::uuid(), 'state_id' => $this->state->id, 'name' => 'Kottayam Sahodaya',
            'district' => 'Kottayam', 'origin' => StateSahodaya::ORIGIN_MANAGED, 'tenant_id' => (string) Str::uuid(),
        ]);

        // A grade/point table so marks are worth something.
        app(StateGradingService::class)->applyManualStandard($this->event);
    }

    private function prizes(): StatePrizeCategoryService
    {
        return app(StatePrizeCategoryService::class);
    }

    private function competitor(FestStateProgramItem $item, StateSahodaya $sahodaya, string $name, float $score, string $school = 'St Joseph HSS'): StateFestParticipant
    {
        $registration = StateFestRegistration::create([
            'state_event_id' => $this->event->id, 'sahodaya_id' => $sahodaya->id,
            'sahodaya_name' => $sahodaya->name, 'school_id' => Str::slug($school), 'school_name' => $school,
            'item_id' => $item->id, 'item_code' => $item->item_code, 'status' => 'approved',
        ]);

        $participant = StateFestParticipant::create([
            'state_event_id' => $this->event->id, 'registration_id' => $registration->id,
            'student_name' => $name, 'class_name' => 'Class 10',
        ]);

        StateFestMark::create([
            'state_event_id' => $this->event->id, 'registration_id' => $registration->id,
            'participant_id' => $participant->id, 'score' => $score, 'status' => 'aggregated',
        ]);

        return $participant;
    }

    private function publish(FestStateProgramItem $item): void
    {
        app(StateResultService::class)->computeItem($this->event, $item);
        app(StateResultService::class)->publishItem($this->event, $item);
    }

    private function category(array $overrides = []): StatePrizeCategory
    {
        return $this->prizes()->save($this->event, array_merge([
            'name' => 'Dance Champion',
            'awards' => ['individual', 'school', 'sahodaya'],
            'honour_count' => 3,
        ], $overrides));
    }

    // ── Categories ─────────────────────────────────────────────────────────────────────────

    public function test_a_category_must_award_at_least_one_title(): void
    {
        $this->expectException(ValidationException::class);

        $this->prizes()->save($this->event, ['name' => 'Crowns nobody', 'awards' => []]);
    }

    public function test_a_code_is_derived_from_the_name_and_must_be_unique(): void
    {
        $category = $this->category();
        $this->assertSame('dance-champion', $category->code);

        $this->expectException(ValidationException::class);
        $this->category();
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

        try {
            $this->prizes()->assignItems($this->event, $overall->id, [$this->dance->id]);
            $this->fail('An overall category accepted item assignments.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('every item', $e->errors()['items'][0]);
        }
    }

    public function test_an_item_from_another_program_cannot_be_assigned(): void
    {
        $otherProgram = FestStateProgram::create([
            'title' => 'Another State', 'state_id' => $this->state->id,
            'event_type' => 'kalolsavam', 'conduct_levels' => ['state'], 'status' => 'published',
        ]);
        $foreign = FestStateProgramItem::create([
            'state_program_id' => $otherProgram->id, 'title' => 'Foreign Item',
            'item_code' => 'XX01', 'qualify_count' => 1,
        ]);

        $category = $this->category();
        $count = $this->prizes()->assignItems($this->event, $category->id, [$this->dance->id, $foreign->id]);

        // Silently dropping it would be wrong, but so would accepting it: only this program's items
        // are counted, and the number returned says how many landed.
        $this->assertSame(1, $count);
    }

    public function test_deleting_a_category_takes_its_assignments_with_it(): void
    {
        $category = $this->category();
        $this->prizes()->assignItems($this->event, $category->id, [$this->dance->id]);

        $this->prizes()->delete($this->event, $category->id);

        $this->assertSame(0, \App\Models\State\StatePrizeCategoryItem::where('prize_category_id', $category->id)->count());
    }

    // ── Standings ──────────────────────────────────────────────────────────────────────────

    public function test_only_published_items_count_towards_a_trophy(): void
    {
        $this->competitor($this->dance, $this->first, 'Dancer', 95);
        $category = $this->category();
        $this->prizes()->assignItems($this->event, $category->id, [$this->dance->id]);

        // Computed but not published: the marks exist and the trophy must still be empty.
        app(StateResultService::class)->computeItem($this->event, $this->dance);

        $standings = $this->prizes()->standings($this->event, $category->fresh('items'));

        $this->assertSame([], $standings['individual']);
        $this->assertFalse($standings['is_complete']);
    }

    public function test_an_individual_champion_is_crowned_with_both_names(): void
    {
        $this->competitor($this->dance, $this->first, 'Winner', 95, 'St Joseph HSS');
        $this->competitor($this->dance, $this->second, 'Runner', 88, 'Holy Family HSS');
        $this->publish($this->dance);

        $category = $this->category();
        $this->prizes()->assignItems($this->event, $category->id, [$this->dance->id]);

        $rows = $this->prizes()->standings($this->event, $category->fresh('items'))['individual'];

        $this->assertSame('Winner', $rows[0]['name']);
        $this->assertSame(1, $rows[0]['rank']);
        $this->assertSame('St Joseph HSS', $rows[0]['school']);
        $this->assertSame('Malappuram Sahodaya', $rows[0]['sahodaya']);
    }

    public function test_a_category_crowns_only_the_titles_it_awards(): void
    {
        $this->competitor($this->dance, $this->first, 'Winner', 95);
        $this->publish($this->dance);

        $category = $this->category(['name' => 'Individual only', 'awards' => ['individual']]);
        $this->prizes()->assignItems($this->event, $category->id, [$this->dance->id]);

        $standings = $this->prizes()->standings($this->event, $category->fresh('items'));

        $this->assertNotNull($standings['individual']);
        $this->assertNull($standings['school']);
        $this->assertNull($standings['sahodaya']);
    }

    public function test_a_sahodaya_champion_sums_every_counted_item(): void
    {
        // Malappuram wins dance, Kottayam wins music: over both items Malappuram's second place in
        // music is what separates them.
        $this->competitor($this->dance, $this->first, 'A', 95);
        $this->competitor($this->dance, $this->second, 'B', 80);
        $this->competitor($this->music, $this->second, 'C', 95);
        $this->competitor($this->music, $this->first, 'D', 90);
        $this->publish($this->dance);
        $this->publish($this->music);

        $overall = $this->category(['name' => 'Overall', 'is_overall' => true, 'awards' => ['sahodaya']]);
        $rows = $this->prizes()->standings($this->event, $overall)['sahodaya'];

        $this->assertCount(2, $rows);
        $this->assertSame(2, collect($rows)->sum('firsts'));
        $this->assertSame(2, $rows[0]['items']);
    }

    public function test_a_true_tie_shares_the_rank(): void
    {
        $this->competitor($this->dance, $this->first, 'A', 95);
        $this->competitor($this->music, $this->second, 'B', 95);
        $this->publish($this->dance);
        $this->publish($this->music);

        $overall = $this->category(['name' => 'Overall', 'is_overall' => true, 'awards' => ['sahodaya']]);
        $rows = $this->prizes()->standings($this->event, $overall)['sahodaya'];

        // Same points, same firsts: joint champions rather than a trophy awarded on row order.
        $this->assertSame(1, $rows[0]['rank']);
        $this->assertSame(1, $rows[1]['rank']);
        $this->assertTrue($rows[0]['is_tied']);
        $this->assertTrue($rows[1]['is_tied']);
    }

    public function test_the_honour_count_limits_places_not_rows(): void
    {
        // Three tied firsts with honour_count 1: all three are champions, so all three are listed.
        foreach (['A', 'B', 'C'] as $i => $name) {
            $sahodaya = StateSahodaya::create([
                'id' => (string) Str::uuid(), 'state_id' => $this->state->id,
                'name' => "Sahodaya {$name}", 'origin' => StateSahodaya::ORIGIN_MANAGED,
                'tenant_id' => (string) Str::uuid(),
            ]);
            $item = FestStateProgramItem::create([
                'state_program_id' => $this->event->state_program_id, 'title' => "Item {$name}",
                'item_code' => "IT0{$i}", 'qualify_count' => 1, 'participant_type' => 'individual',
            ]);
            $this->competitor($item, $sahodaya, "Competitor {$name}", 95);
            $this->publish($item);
        }

        $overall = $this->category(['name' => 'Overall', 'is_overall' => true, 'awards' => ['sahodaya'], 'honour_count' => 1]);
        $rows = $this->prizes()->standings($this->event, $overall)['sahodaya'];

        $this->assertCount(3, $rows);
        $this->assertSame([1, 1, 1], array_column($rows, 'rank'));
    }

    public function test_a_partly_published_category_is_reported_as_provisional(): void
    {
        $this->competitor($this->dance, $this->first, 'A', 95);
        $this->competitor($this->music, $this->second, 'B', 95);
        $this->publish($this->dance);

        $overall = $this->category(['name' => 'Overall', 'is_overall' => true, 'awards' => ['sahodaya']]);
        $standings = $this->prizes()->standings($this->event, $overall);

        $this->assertFalse($standings['is_complete']);
        $this->assertSame(1, $standings['items_counted']);
        $this->assertSame(2, $standings['items']);
    }

    public function test_an_inactive_category_is_left_out_of_the_computed_standings(): void
    {
        $this->competitor($this->dance, $this->first, 'A', 95);
        $this->publish($this->dance);

        $this->category(['name' => 'Retired trophy', 'is_active' => false, 'is_overall' => true]);

        $this->assertCount(0, $this->prizes()->allStandings($this->event));
    }

    // ── Access ─────────────────────────────────────────────────────────────────────────────

    public function test_the_page_opens_for_a_state_admin(): void
    {
        $admin = tap(PlatformUser::create([
            'name' => 'Registrar', 'email' => 'registrar@example.test', 'username' => 'registrar',
            'password' => 'password', 'state_id' => $this->state->id, 'email_verified_at' => now(),
        ]), fn ($u) => $u->assignRole('state_admin'));

        $this->actingAs($admin, 'platform')
            ->get("http://superadmin.test/admin/state/fest/{$this->event->id}/prizes")
            ->assertOk();
    }

    public function test_a_report_user_cannot_change_the_trophies(): void
    {
        $reporter = tap(PlatformUser::create([
            'name' => 'Reporter', 'email' => 'reporter@example.test', 'username' => 'reporter',
            'password' => 'password', 'state_id' => $this->state->id, 'email_verified_at' => now(),
        ]), fn ($u) => $u->assignRole('state_report_user'));

        $this->actingAs($reporter, 'platform')
            ->get("http://superadmin.test/admin/state/fest/{$this->event->id}/prizes")
            ->assertForbidden();
    }
}
