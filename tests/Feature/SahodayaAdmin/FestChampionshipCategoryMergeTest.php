<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestIndividualChampionshipPoint;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * fee_settings-style event config already had a working but unexposed category-merge
 * reader (FestCumulativeChampionshipService::championshipCategoryKey(), reading
 * aggregation_config.championship_category_map) used only by the school/team cumulative
 * scoreboard. This adds the first UI/endpoint to write that map, and wires the same map
 * into the individual championship (FestChampionshipController::recalculate()) too --
 * constrained there to the five-value DB enum (lp/up/hs/hss/open) it's stuck with.
 */
class FestChampionshipCategoryMergeTest extends TestCase
{
    use RefreshDatabase;

    private function makeFixture(string $classGroup = 'hs'): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Category Merge Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'CM', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Category Merge School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Category Merge Event', 'event_type' => 'kalotsav']);
        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Solo Song', 'item_code' => 'SS1',
            'participant_type' => 'individual', 'class_group' => $classGroup, 'results_published_at' => now(),
        ]);

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => 'Class 10', 'class_number' => 10]);
        $student = Student::create([
            'tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => 'Merge Test Student',
            'status' => 'active', 'verification_status' => 'verified', 'eligible_kalolsav' => true, 'gender' => 'male',
        ]);

        $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
        $participant = FestParticipant::create(['registration_id' => $registration->id, 'event_id' => $event->id, 'participant_type' => 'student', 'student_id' => $student->id]);
        FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'grade' => 'A', 'position' => 1, 'score' => 65]);

        return compact('sahodaya', 'school', 'admin', 'event', 'student');
    }

    public function test_saving_a_merge_rule_persists_into_the_root_events_aggregation_config(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->makeFixture();

        $this->actingAs($admin)->put(route('sahodaya.events.championship.category-merge', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), ['groups' => [['target' => 'open', 'sources' => ['hs']]]])->assertRedirect();

        $this->assertSame(['hs' => 'open'], $event->fresh()->aggregation_config['championship_category_map']);
    }

    public function test_recalculate_applies_the_merge_to_the_individual_championship_category(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event, 'student' => $student] = $this->makeFixture('hs');

        $event->update(['aggregation_config' => ['championship_category_map' => ['hs' => 'open']]]);

        $this->actingAs($admin)->post(route('sahodaya.events.championship.recalculate', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]))->assertRedirect();

        $this->assertDatabaseHas('fest_individual_championship_points', [
            'event_id' => $event->id, 'student_id' => $student->id, 'category' => 'open',
        ]);
    }

    /**
     * The DB enum backing fest_individual_championship_points.category only accepts
     * lp/up/hs/hss/open — a merge target outside that set must be silently skipped for
     * the individual table (not crash, not violate the constraint), even though the
     * exact same map is fully honored by the unconstrained school/team scoreboard.
     */
    public function test_a_merge_target_outside_the_individual_enum_is_ignored_for_that_table(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event, 'student' => $student] = $this->makeFixture('hs');

        $event->update(['aggregation_config' => ['championship_category_map' => ['hs' => 'category_custom_bucket']]]);

        $this->actingAs($admin)->post(route('sahodaya.events.championship.recalculate', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]))->assertRedirect();

        $this->assertDatabaseHas('fest_individual_championship_points', [
            'event_id' => $event->id, 'student_id' => $student->id, 'category' => 'hs',
        ]);
    }

    /**
     * Regression: index() used to assign one flat, global rank across every category
     * combined, so a school's "HS champion" (rank #1 among HS students) could show up
     * as rank #47 the moment the page was filtered to just HS -- there was no real
     * category-wise ranking, only a category-blind list with a category label on it.
     */
    public function test_leaderboard_ranks_students_within_their_own_category_not_globally(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->makeFixture();
        $school = Tenant::where('parent_id', $sahodaya->id)->firstOrFail();

        // HS: two students, 30 then 20 points. LP: one student with only 5 points, but
        // since LP has just one entrant they must still be LP's #1 -- not buried behind
        // HS's two higher-scoring students in a single global ranking.
        $hsTop = Student::create(['tenant_id' => $school->id, 'school_class_id' => SchoolClass::create(['tenant_id' => $school->id, 'name' => 'C9', 'class_number' => 9])->id, 'name' => 'HS Top', 'status' => 'active', 'verification_status' => 'verified', 'eligible_kalolsav' => true]);
        $hsSecond = Student::create(['tenant_id' => $school->id, 'school_class_id' => $hsTop->school_class_id, 'name' => 'HS Second', 'status' => 'active', 'verification_status' => 'verified', 'eligible_kalolsav' => true]);
        $lpOnly = Student::create(['tenant_id' => $school->id, 'school_class_id' => $hsTop->school_class_id, 'name' => 'LP Only', 'status' => 'active', 'verification_status' => 'verified', 'eligible_kalolsav' => true]);

        FestIndividualChampionshipPoint::create(['event_id' => $event->id, 'student_id' => $hsTop->id, 'category' => 'hs', 'gender' => 'open', 'points' => 30, 'group_points' => 0]);
        FestIndividualChampionshipPoint::create(['event_id' => $event->id, 'student_id' => $hsSecond->id, 'category' => 'hs', 'gender' => 'open', 'points' => 20, 'group_points' => 0]);
        FestIndividualChampionshipPoint::create(['event_id' => $event->id, 'student_id' => $lpOnly->id, 'category' => 'lp', 'gender' => 'open', 'points' => 5, 'group_points' => 0]);

        $this->actingAs($admin)
            ->get(route('sahodaya.events.championship.index', ['tenantId' => $sahodaya->id, 'event' => $event->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sahodaya/Events/Championship', false)
                // Sorted by category key ("hs" before "lp"), then rank within it.
                ->where('leaderboard.0.student.id', $hsTop->id)
                ->where('leaderboard.0.rank', 1)
                ->where('leaderboard.0.overall_rank', 1)
                ->where('leaderboard.1.student.id', $hsSecond->id)
                ->where('leaderboard.1.rank', 2)
                ->where('leaderboard.1.overall_rank', 2)
                ->where('leaderboard.2.student.id', $lpOnly->id)
                ->where('leaderboard.2.rank', 1)
                ->where('leaderboard.2.overall_rank', 3));
    }

    public function test_a_source_category_cannot_be_merged_into_two_different_targets(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->makeFixture();

        $this->actingAs($admin)->put(route('sahodaya.events.championship.category-merge', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), ['groups' => [
            ['target' => 'open', 'sources' => ['hs']],
            ['target' => 'lp', 'sources' => ['hs']],
        ]])->assertStatus(422);
    }
}
