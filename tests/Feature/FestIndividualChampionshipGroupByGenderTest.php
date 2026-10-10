<?php

namespace Tests\Feature;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestIndividualChampionshipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestIndividualChampionshipGroupByGenderTest extends TestCase
{
    use RefreshDatabase;

    private function makeChampionshipFixture(bool $groupByGender = true, bool $disabled = false): array
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya',
            'domain' => uniqid() . '.test',
            'is_active' => true,
        ]);
        $event = FestEvent::create([
            'tenant_id' => $tenant->id,
            'title' => 'Test Kalotsav',
            'event_type' => 'kalolsavam',
        ]);
        $config = [
            'disabled' => $disabled,
            'group_by_gender' => $groupByGender,
            'male_title' => 'Kalaprathibha',
            'female_title' => 'Kalathilakam',
            'runner_up_title' => 'Runner Up',
            'max_counting_items' => 0,
            'multi_person_mode' => 'tie_break_only',
            'group_weight_percent' => 100,
            'must_have_first_place' => false,
            'minimum_points' => 0,
        ];
        $event->update(['aggregation_config' => ['individual_championship_config' => $config]]);

        $item = FestEventItem::create([
            'event_id' => $event->id,
            'tenant_id' => $tenant->id,
            'title' => 'Bharatanatyam',
            'item_code' => 'BN1',
            'participant_type' => 'individual',
            'class_group' => 'open',
            'gender' => 'open',
            'sort_order' => 1,
            'results_published_at' => now(),
            'results_hidden' => false,
        ]);

        $schoolClass = SchoolClass::create([
            'tenant_id' => $tenant->id,
            'name' => 'Class 12',
            'class_number' => 12,
        ]);

        $boy1 = Student::create(['tenant_id' => $tenant->id, 'school_class_id' => $schoolClass->id, 'name' => 'Boy A', 'gender' => 'male', 'status' => 'active']);
        $boy2 = Student::create(['tenant_id' => $tenant->id, 'school_class_id' => $schoolClass->id, 'name' => 'Boy B', 'gender' => 'male', 'status' => 'active']);
        $girl1 = Student::create(['tenant_id' => $tenant->id, 'school_class_id' => $schoolClass->id, 'name' => 'Girl A', 'gender' => 'female', 'status' => 'active']);
        $girl2 = Student::create(['tenant_id' => $tenant->id, 'school_class_id' => $schoolClass->id, 'name' => 'Girl B', 'gender' => 'female', 'status' => 'active']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'parent_id' => $tenant->id,
            'name' => 'Test School',
            'domain' => uniqid() . '-school.test',
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        foreach ([[$boy1, 95], [$boy2, 88], [$girl1, 95], [$girl2, 88]] as [$student, $points]) {
            $reg = FestRegistration::create([
                'event_id' => $event->id,
                'item_id' => $item->id,
                'school_id' => $school->id,
                'status' => 'approved',
            ]);
            $participant = FestParticipant::create([
                'registration_id' => $reg->id,
                'event_id' => $event->id,
                'participant_type' => 'student',
                'student_id' => $student->id,
            ]);
            FestMark::create([
                'event_id' => $event->id,
                'item_id' => $item->id,
                'participant_id' => $participant->id,
                'score' => $points,
                'grade' => 'A',
                'position' => 1,
            ]);
        }

        return [$event, $tenant, $school];
    }

    public function test_excluded_category_and_disabled_phase_rankings_are_empty(): void
    {
        [$event] = $this->makeChampionshipFixture();
        $service = app(FestIndividualChampionshipService::class);
        $config = $event->aggregation_config;
        $config['individual_championship_config']['excluded_individual_categories'] = ['open'];
        $event->update(['aggregation_config' => $config]);
        $this->assertEmpty($service->leaderboardForEvent($event, true));
        $config['individual_championship_config']['disabled'] = true;
        $event->update(['aggregation_config' => $config]);
        $this->assertFalse($service->getPublicOverlayConfig($event)['enabled']);
        $this->assertEmpty($service->crossPhaseStanding($event, true));
        $this->assertEmpty($service->crossPhaseStandingForVisibleLeaves($event, collect([$event->id]), true));
    }

    public function test_combined_rankings_compare_boys_and_girls_in_the_same_category(): void
    {
        $rows = collect([
            (object) ['student_id' => 1, 'student' => null, 'category' => 'open', 'gender' => 'male', 'points' => 10, 'firsts' => 0, 'group_points' => 0],
            (object) ['student_id' => 2, 'student' => null, 'category' => 'open', 'gender' => 'female', 'points' => 20, 'firsts' => 0, 'group_points' => 0],
        ]);
        $service = app(FestIndividualChampionshipService::class);
        $separate = $service->rankAndFormat($rows, true, ['group_by_gender' => true]);
        $combined = $service->rankAndFormat($rows, true, ['group_by_gender' => false]);
        $this->assertSame([1, 1], $separate->pluck('rank')->all());
        $this->assertSame(2, $combined->firstWhere('gender', 'male')['rank']);
        $this->assertSame(1, $combined->firstWhere('gender', 'female')['rank']);
    }

    public function test_combined_public_tabs_match_their_rows_and_keep_category_order(): void
    {
        $service = app(FestIndividualChampionshipService::class);
        $rows = collect(['hs', 'hss', 'lp', 'open', 'up'])->map(fn ($category, $index) =>
            (object) ['student_id' => $index + 1, 'student' => null, 'category' => $category, 'gender' => 'male', 'points' => 10, 'firsts' => 0, 'group_points' => 0]
        );
        $ranked = $service->rankAndFormat($rows, true, ['group_by_gender' => false]);
        $this->assertSame(['lp', 'up', 'hs', 'hss', 'open'], $ranked->pluck('category')->all());
        $championship = $ranked->map(fn ($row) => [
            'category_key' => $row['category'], 'category' => $row['category'],
            'gender_key' => $row['gender'], 'gender' => $row['gender'], 'rank' => $row['rank'],
            'photo' => null, 'student' => 'Test Student', 'school' => 'Test School',
            'points' => $row['points'], 'ref' => null,
        ])->all();
        $source = file_get_contents(resource_path('views/public/fest/results.blade.php'));
        $start = strrpos(substr($source, 0, strpos($source, '$groupByGender =')), '@php');
        $end = strpos($source, '</script>', $start) + strlen('</script>');
        $html = \Illuminate\Support\Facades\Blade::render(substr($source, $start, $end - $start), [
            'championship' => $championship, 'championshipConfig' => ['group_by_gender' => false],
        ]);
        $this->assertStringContainsString('<tr data-group="lp" >', $html);
        $this->assertStringContainsString('<tr data-group="up"  hidden >', $html);
        $this->assertStringNotContainsString('data-group="lp|male"', $html);
    }

    public function test_returns_empty_leaderboard_when_disabled(): void
    {
        [$event] = $this->makeChampionshipFixture(true, true);
        $service = app(FestIndividualChampionshipService::class);

        $this->assertEmpty($service->leaderboardForEvent($event));
    }

    public function test_group_by_gender_true_splits_genders_in_leaderboard(): void
    {
        [$event] = $this->makeChampionshipFixture(true, false);
        $service = app(FestIndividualChampionshipService::class);
        $result = $service->leaderboardForEvent($event);

        $this->assertCount(4, $result);
        $genders = $result->pluck('gender')->unique()->sort()->values()->all();
        $this->assertSame(['female', 'male'], $genders);
    }

    public function test_group_by_gender_false_produces_single_genderless_leaderboard(): void
    {
        [$event] = $this->makeChampionshipFixture(false, false);
        $service = app(FestIndividualChampionshipService::class);
        $result = $service->leaderboardForEvent($event);

        $this->assertCount(4, $result);
        $this->assertNotEmpty($result->pluck('overall_rank')->unique());
    }

    public function test_champions_summary_returns_gender_split_keys_when_group_by_gender_true(): void
    {
        [$event] = $this->makeChampionshipFixture(true, false);
        $service = app(FestIndividualChampionshipService::class);
        $summary = $service->championsSummary($event);

        $this->assertArrayHasKey('overall_male_champion', $summary);
        $this->assertArrayHasKey('overall_female_champion', $summary);
        $this->assertArrayNotHasKey('overall_champion', $summary);
    }

    public function test_champions_summary_returns_unified_overall_key_when_group_by_gender_false(): void
    {
        [$event] = $this->makeChampionshipFixture(false, false);
        $service = app(FestIndividualChampionshipService::class);
        $summary = $service->championsSummary($event);

        $this->assertArrayHasKey('overall_champion', $summary);
        $this->assertArrayNotHasKey('overall_male_champion', $summary);
        $this->assertArrayNotHasKey('overall_female_champion', $summary);
    }
}
