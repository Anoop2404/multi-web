<?php

namespace Tests\Feature\State;

use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\PlatformState;
use App\Models\PlatformUser;
use App\Models\State\StateClassCategory;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestMark;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateGradeBand;
use App\Models\State\StatePointRule;
use App\Models\State\StateSahodaya;
use App\Services\State\Fest\StateEligibilityService;
use App\Services\State\Fest\StateGradingService;
use App\Services\State\Fest\StateResultService;
use App\Services\State\StateGradePointService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Grade Master, Grade & Rank Points and Categories & Eligibility.
 *
 * The first test is the one that matters most: before this work a State event with no scoring_preset
 * — which is every event the module creates — scored every mark at zero, silently.
 */
class StateGradingAndEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private PlatformState $state;

    private StateFestEvent $event;

    private FestStateProgramItem $item;

    private StateSahodaya $sahodaya;

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
        $this->item = FestStateProgramItem::create([
            'state_program_id' => $program->id, 'title' => 'Light Music', 'item_code' => 'LM01',
            'qualify_count' => 3, 'class_group' => 'category_2', 'participant_type' => 'individual',
        ]);
        $this->sahodaya = StateSahodaya::create([
            'id' => (string) Str::uuid(), 'state_id' => $this->state->id, 'name' => 'Malappuram Sahodaya',
            'tenant_id' => (string) Str::uuid(), 'origin' => StateSahodaya::ORIGIN_MANAGED,
        ]);
    }

    private function grading(): StateGradingService
    {
        return app(StateGradingService::class);
    }

    private function points(): StateGradePointService
    {
        return app(StateGradePointService::class);
    }

    private function competitor(string $name, float $score, string $className = 'Class 6', ?FestStateProgramItem $item = null): StateFestParticipant
    {
        $item ??= $this->item;

        $registration = StateFestRegistration::create([
            'state_event_id' => $this->event->id, 'sahodaya_id' => $this->sahodaya->id,
            'sahodaya_name' => $this->sahodaya->name, 'school_id' => 'sj', 'school_name' => 'St Joseph HSS',
            'item_id' => $item->id, 'item_code' => $item->item_code, 'status' => 'approved',
        ]);

        $participant = StateFestParticipant::create([
            'state_event_id' => $this->event->id, 'registration_id' => $registration->id,
            'student_name' => $name, 'class_name' => $className,
        ]);

        StateFestMark::create([
            'state_event_id' => $this->event->id, 'registration_id' => $registration->id,
            'participant_id' => $participant->id, 'score' => $score, 'status' => 'aggregated',
        ]);

        return $participant;
    }

    // ── The silent-zero bug ────────────────────────────────────────────────────────────────

    public function test_an_event_with_no_preset_and_no_rules_still_scores(): void
    {
        $this->assertNull($this->event->scoring_preset);

        // Before the manual fallback existed, every one of these was 0 and a Sahodaya ranking of all
        // zeros read as "nobody has been marked yet".
        $this->assertGreaterThan(0, $this->points()->pointsForGradePosition($this->event, 'A', 1, false));
        $this->assertGreaterThan(0, $this->points()->pointsForGradePosition($this->event, 'B', 2, false));
        $this->assertNotNull($this->points()->resolveGradeFromScore($this->event, 85.0));
    }

    public function test_a_group_item_is_worth_more_than_an_individual_one(): void
    {
        $individual = $this->points()->pointsForGradePosition($this->event, 'A', 1, false);
        $group = $this->points()->pointsForGradePosition($this->event, 'A', 1, true);

        $this->assertGreaterThan($individual, $group);
    }

    public function test_the_standings_are_not_all_zeros(): void
    {
        $this->competitor('First', 95);
        $this->competitor('Second', 88);

        app(StateResultService::class)->computeItem($this->event, $this->item);
        app(StateResultService::class)->publishItem($this->event, $this->item);

        $standings = app(StateResultService::class)->sahodayaStandings($this->event);

        $this->assertCount(1, $standings);
        $this->assertGreaterThan(0, $standings[0]['points']);
    }

    // ── Grade bands ────────────────────────────────────────────────────────────────────────

    public function test_the_events_own_bands_beat_the_manual(): void
    {
        $this->grading()->saveBands($this->event, [
            ['grade' => 'Distinction', 'min_score' => 90, 'max_score' => 100],
            ['grade' => 'Pass', 'min_score' => 0, 'max_score' => 89],
        ]);

        $this->assertSame('Distinction', $this->points()->resolveGradeFromScore($this->event, 95.0));
        $this->assertSame('Pass', $this->points()->resolveGradeFromScore($this->event, 60.0));
    }

    public function test_overlapping_bands_are_refused_with_the_reason(): void
    {
        try {
            $this->grading()->saveBands($this->event, [
                ['grade' => 'A', 'min_score' => 70, 'max_score' => 100],
                ['grade' => 'B', 'min_score' => 60, 'max_score' => 75],
            ]);
            $this->fail('Overlapping bands were accepted.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('overlap', $e->errors()['bands'][0]);
        }
    }

    public function test_a_gap_between_bands_is_refused(): void
    {
        try {
            $this->grading()->saveBands($this->event, [
                ['grade' => 'A', 'min_score' => 70, 'max_score' => 100],
                ['grade' => 'B', 'min_score' => 50, 'max_score' => 60],
            ]);
            $this->fail('A gap was accepted.');
        } catch (ValidationException $e) {
            // A mark in the gap would get no grade and score nothing, silently.
            $this->assertStringContainsString('61', $e->errors()['bands'][0]);
        }
    }

    public function test_an_item_can_override_the_event_scale_and_others_are_unaffected(): void
    {
        $this->grading()->saveBands($this->event, [
            ['grade' => 'A', 'min_score' => 70, 'max_score' => 100],
            ['grade' => 'B', 'min_score' => 0, 'max_score' => 69],
        ]);
        $this->grading()->saveBands($this->event, [
            ['grade' => 'A', 'min_score' => 90, 'max_score' => 100],
            ['grade' => 'B', 'min_score' => 0, 'max_score' => 89],
        ], $this->item->id);

        $this->assertSame('B', $this->points()->resolveGradeFromScore($this->event, 85.0, $this->item->id));
        $this->assertSame('A', $this->points()->resolveGradeFromScore($this->event, 85.0));
    }

    public function test_an_item_with_no_scale_of_its_own_uses_the_events(): void
    {
        $this->grading()->saveBands($this->event, [
            ['grade' => 'A', 'min_score' => 70, 'max_score' => 100],
            ['grade' => 'B', 'min_score' => 0, 'max_score' => 69],
        ]);

        // Falls back rather than grading nothing, which would score the whole item at zero.
        $this->assertSame('A', $this->points()->resolveGradeFromScore($this->event, 80.0, $this->item->id));
    }

    // ── Point rules ────────────────────────────────────────────────────────────────────────

    public function test_the_most_specific_rule_wins_regardless_of_row_order(): void
    {
        $this->grading()->saveRules($this->event, [
            ['grade' => null, 'position' => 1, 'is_group' => false, 'points' => 4],
            ['grade' => 'A', 'position' => 1, 'is_group' => false, 'points' => 10],
            ['grade' => 'A', 'position' => null, 'is_group' => false, 'points' => 5],
        ]);

        $this->assertSame(10, $this->grading()->pointsFor($this->event, 'A', 1, false));
        $this->assertSame(5, $this->grading()->pointsFor($this->event, 'A', 7, false));
        $this->assertSame(4, $this->grading()->pointsFor($this->event, 'B', 1, false));
    }

    public function test_group_rules_do_not_apply_to_individual_items(): void
    {
        $this->grading()->saveRules($this->event, [
            ['grade' => 'A', 'position' => 1, 'is_group' => true, 'points' => 20],
        ]);

        $this->assertSame(20, $this->grading()->pointsFor($this->event, 'A', 1, true));
        $this->assertSame(0, $this->grading()->pointsFor($this->event, 'A', 1, false));
    }

    public function test_two_rules_for_the_same_case_are_refused(): void
    {
        $this->expectException(ValidationException::class);

        $this->grading()->saveRules($this->event, [
            ['grade' => 'A', 'position' => 1, 'is_group' => false, 'points' => 10],
            ['grade' => 'A', 'position' => 1, 'is_group' => false, 'points' => 8],
        ]);
    }

    // ── The manual's standard tables ───────────────────────────────────────────────────────

    public function test_loading_the_default_standard_sets_both_bands_and_rules(): void
    {
        $result = $this->grading()->applyManualStandard($this->event, StateGradingService::DEFAULT_GRADING);

        $this->assertGreaterThan(0, $result['bands']);
        $this->assertGreaterThan(0, $result['rules']);
        $this->assertSame(4, StateGradeBand::where('state_event_id', $this->event->id)->count());
        // A, B, C and No Grade — the band that keeps a mark under 50 from resolving to nothing.
        $this->assertSame('No Grade', $this->points()->resolveGradeFromScore($this->event, 30.0));
    }

    public function test_the_confederation_table_matches_the_manual_numbers(): void
    {
        $this->grading()->applyManualStandard($this->event, StateGradingService::CONFED_SCORING);

        // From config/fest_confed_kalotsav_scoring.php: A/1st is 10 individual, 20 group.
        $this->assertSame(10, $this->grading()->pointsFor($this->event, 'A', 1, false));
        $this->assertSame(20, $this->grading()->pointsFor($this->event, 'A', 1, true));
        $this->assertSame('A', $this->points()->resolveGradeFromScore($this->event, 75.0));
        $this->assertSame('B', $this->points()->resolveGradeFromScore($this->event, 65.0));
    }

    public function test_the_confederation_bands_leave_no_gap_below_the_lowest_grade(): void
    {
        $this->grading()->applyManualStandard($this->event, StateGradingService::CONFED_SCORING);

        // Below C (50) the manual states nothing; a band is derived so the mark still resolves.
        $this->assertNotNull($this->points()->resolveGradeFromScore($this->event, 20.0));
    }

    public function test_the_source_says_which_tier_is_in_force(): void
    {
        $this->assertSame('manual', $this->points()->sourceFor($this->event)['source']);

        $this->grading()->applyManualStandard($this->event);

        $this->assertSame('event', $this->points()->sourceFor($this->event->fresh())['source']);
    }

    // ── Class categories ───────────────────────────────────────────────────────────────────

    public function test_seeding_reads_the_class_range_out_of_the_scheme_labels(): void
    {
        $this->grading()->seedCategories($this->event);

        $second = StateClassCategory::where('state_event_id', $this->event->id)
            ->where('code', 'category_2')->first();

        // "Category 2 — Classes 5, 6 & 7": the leading category number must not be read as a class.
        $this->assertSame(5, $second->min_class);
        $this->assertSame(7, $second->max_class);
    }

    public function test_the_group_category_is_open(): void
    {
        $this->grading()->seedCategories($this->event);

        $fifth = StateClassCategory::where('state_event_id', $this->event->id)
            ->where('code', 'category_5')->first();

        // "Category 5 — Group Items (Open)" states no classes, so it restricts none.
        $this->assertTrue($fifth->is_open);
        $this->assertTrue($fifth->admits(3));
        $this->assertTrue($fifth->admits(12));
    }

    public function test_seeding_twice_updates_rather_than_duplicates(): void
    {
        $this->grading()->seedCategories($this->event);
        $this->grading()->seedCategories($this->event);

        $this->assertSame(5, StateClassCategory::where('state_event_id', $this->event->id)->count());
    }

    public function test_a_category_with_items_in_it_cannot_be_deleted(): void
    {
        $this->grading()->seedCategories($this->event);
        $category = StateClassCategory::where('code', 'category_2')->first();

        try {
            $this->grading()->deleteCategory($this->event, $category->id);
            $this->fail('A category still in use was deleted.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('1 item(s)', $e->errors()['category'][0]);
        }
    }

    public function test_items_coded_to_a_missing_category_are_reported(): void
    {
        // No categories seeded at all, so nothing checks this item's entries.
        $unknown = $this->grading()->itemsWithUnknownCategory($this->event);

        $this->assertCount(1, $unknown);
        $this->assertSame('category_2', $unknown[0]['class_group']);
    }

    // ── Eligibility ────────────────────────────────────────────────────────────────────────

    private function eligibility(): StateEligibilityService
    {
        return app(StateEligibilityService::class);
    }

    public function test_a_pupil_outside_the_items_category_is_flagged(): void
    {
        $this->grading()->seedCategories($this->event);
        $this->competitor('Too old', 90, 'Class 9');

        $audit = $this->eligibility()->audit($this->event);

        $this->assertCount(1, $audit);
        $this->assertSame(StateEligibilityService::WRONG_CATEGORY, $audit[0]['status']);
        $this->assertStringContainsString('Class 9', $audit[0]['problems'][0]['message']);
    }

    public function test_a_pupil_inside_the_category_passes(): void
    {
        $this->grading()->seedCategories($this->event);
        $this->competitor('Right age', 90, 'Class 6');

        $this->assertCount(0, $this->eligibility()->audit($this->event));
    }

    public function test_an_unreadable_class_is_reported_as_unknown_not_as_a_violation(): void
    {
        $this->grading()->seedCategories($this->event);
        $this->competitor('No class recorded', 90, '');

        $audit = $this->eligibility()->audit($this->event);

        // Reported, because nothing can be checked — but not counted as the Sahodaya sending the
        // wrong pupil, which is a different conversation.
        $this->assertSame(StateEligibilityService::UNKNOWN_CLASS, $audit[0]['status']);
    }

    public function test_a_roman_numeral_class_is_understood(): void
    {
        $this->grading()->seedCategories($this->event);
        $this->competitor('Roman', 90, 'VI B');

        $this->assertCount(0, $this->eligibility()->audit($this->event));
    }

    public function test_a_team_below_the_items_minimum_is_flagged(): void
    {
        $this->grading()->seedCategories($this->event);

        $group = FestStateProgramItem::create([
            'state_program_id' => $this->event->state_program_id, 'title' => 'Group Dance',
            'item_code' => 'GD01', 'qualify_count' => 1, 'class_group' => 'category_5',
            'participant_type' => 'group', 'min_group_size' => 4, 'max_group_size' => 7,
        ]);

        $this->competitor('Solo', 90, 'Class 6', $group);

        $audit = $this->eligibility()->audit($this->event)->firstWhere('item_code', 'GD01');

        $this->assertSame(StateEligibilityService::TEAM_SIZE, $audit['status']);
        $this->assertStringContainsString('at least 4', $audit['problems'][0]['message']);
    }

    public function test_an_item_with_no_category_on_the_event_says_so_rather_than_passing_silently(): void
    {
        // Categories never seeded: the entry is unchecked, and that is the finding.
        $this->competitor('Anyone', 90, 'Class 9');

        $audit = $this->eligibility()->audit($this->event);

        $this->assertSame(StateEligibilityService::NO_CATEGORY, $audit[0]['status']);
        $this->assertStringContainsString('nothing checks its entries', $audit[0]['problems'][0]['message']);
    }

    public function test_the_summary_separates_passes_from_flags(): void
    {
        $this->grading()->seedCategories($this->event);
        $this->competitor('Fine', 90, 'Class 6');
        $this->competitor('Wrong', 90, 'Class 11');

        $summary = $this->eligibility()->summary($this->event);

        $this->assertSame(2, $summary['checked']);
        $this->assertSame(1, $summary['ok']);
        $this->assertSame(1, $summary['problems'][StateEligibilityService::WRONG_CATEGORY]);
    }

    // ── Access ─────────────────────────────────────────────────────────────────────────────

    public function test_the_tabs_need_the_settings_capability(): void
    {
        $operator = tap(PlatformUser::create([
            'name' => 'Mark operator', 'email' => 'marks@example.test', 'username' => 'markop',
            'password' => 'password', 'state_id' => $this->state->id, 'email_verified_at' => now(),
        ]), fn ($u) => $u->assignRole('state_mark_operator'));

        // A mark operator enters marks; deciding what a mark is worth is a different trust.
        $this->actingAs($operator, 'platform')
            ->get("http://superadmin.test/admin/state/fest/{$this->event->id}/points")
            ->assertForbidden();
    }

    public function test_a_state_admin_can_open_all_three_tabs(): void
    {
        $admin = tap(PlatformUser::create([
            'name' => 'Registrar', 'email' => 'registrar@example.test', 'username' => 'registrar',
            'password' => 'password', 'state_id' => $this->state->id, 'email_verified_at' => now(),
        ]), fn ($u) => $u->assignRole('state_admin'));

        foreach (['grades', 'points', 'eligibility'] as $tab) {
            $this->actingAs($admin, 'platform')
                ->get("http://superadmin.test/admin/state/fest/{$this->event->id}/{$tab}")
                ->assertOk();
        }
    }
}
