<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestPointRule;
use App\Models\FestRegistration;
use App\Models\FestResult;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Grade Points Master actions (add/remove a rule, load the Kalolsavam Manual standard)
 * previously only wrote FestPointRule rows without refreshing the school scoreboard —
 * unlike their sports rank-points sibling actions (updateRankPoints()/seedRankPoints()),
 * which already call EventContext::recalculateSchoolPoints(). That's why a newly added
 * or edited point rule never showed up in the officially published totals until some
 * unrelated action (like re-saving a mark) happened to trigger a recalculation.
 */
class FestPointRuleRecalculationTest extends TestCase
{
    use RefreshDatabase;

    private function makeFixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Point Rule Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'PR', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Point Rule School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Point Rule Event', 'event_type' => 'kalotsav']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'item_code' => 'SS1', 'participant_type' => 'individual', 'results_published_at' => now()]);

        $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
        $participant = FestParticipant::create(['registration_id' => $registration->id, 'event_id' => $event->id, 'participant_type' => 'student']);
        FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'grade' => 'A', 'position' => 1, 'score' => 65]);

        return compact('sahodaya', 'school', 'admin', 'event');
    }

    public function test_adding_a_point_rule_immediately_refreshes_the_school_scoreboard(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'admin' => $admin, 'event' => $event] = $this->makeFixture();

        // Before any rule exists, no FestResult snapshot has ever been computed.
        $this->assertDatabaseMissing('fest_results', ['event_id' => $event->id, 'school_id' => $school->id]);

        $this->actingAs($admin)->post(route('sahodaya.events.point-rules.store', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), ['grade' => 'A', 'position' => 1, 'points' => 25, 'is_group' => false])
            ->assertRedirect();

        $this->assertDatabaseHas('fest_results', [
            'event_id' => $event->id, 'school_id' => $school->id, 'item_id' => null, 'total_points' => 25,
        ]);
    }

    /**
     * Regression test for a real production bug: storePointRule() used create(), so
     * submitting the same (grade, position, type) combination twice — a re-submitted
     * form, or an admin not realizing a rule already existed for it — silently saved
     * TWO rules with different points values instead of updating the one that already
     * existed. FestGradePointService::pointsForMark()'s lookup has no tiebreaker for
     * which duplicate it reads, so which points value actually applied became
     * effectively arbitrary. Confirmed against a real event where this had already
     * happened: "A, 2nd place, Individual" had both an 8-point and a 16-point rule.
     */
    public function test_adding_the_same_rule_twice_updates_it_instead_of_duplicating(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'admin' => $admin, 'event' => $event] = $this->makeFixture();

        // Matches makeFixture()'s own mark (grade A, position 1) so the scoreboard
        // assertion below is meaningful, not just the rule-row count.
        $payload = ['grade' => 'A', 'position' => 1, 'points' => 8, 'is_group' => false];
        $this->actingAs($admin)->post(route('sahodaya.events.point-rules.store', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), $payload)->assertRedirect();

        // Same grade/position/type again, but with a different (wrong/corrected) points
        // value — exactly what re-submitting the "Add rule" form produces.
        $this->actingAs($admin)->post(route('sahodaya.events.point-rules.store', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), [...$payload, 'points' => 16])->assertRedirect();

        $this->assertSame(1, FestPointRule::where([
            'event_id' => $event->id, 'grade' => 'A', 'position' => 1, 'is_group' => false,
        ])->count());
        $this->assertDatabaseHas('fest_point_rules', [
            'event_id' => $event->id, 'grade' => 'A', 'position' => 1, 'is_group' => false, 'points' => 16,
        ]);
        // The scoreboard snapshot must reflect the single, updated rule too — not be
        // stuck on whichever duplicate happened to be recalculated first.
        $this->assertDatabaseHas('fest_results', [
            'event_id' => $event->id, 'school_id' => $school->id, 'item_id' => null, 'total_points' => 16,
        ]);
    }

    public function test_removing_a_point_rule_recalculates_back_to_the_default_table(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'admin' => $admin, 'event' => $event] = $this->makeFixture();

        $rule = FestPointRule::create(['event_id' => $event->id, 'grade' => 'A', 'position' => 1, 'points' => 25, 'is_group' => false]);
        FestResult::create(['event_id' => $event->id, 'item_id' => null, 'school_id' => $school->id, 'total_points' => 25, 'rank' => 1]);

        $this->actingAs($admin)->delete(route('sahodaya.events.point-rules.destroy', [
            'tenantId' => $sahodaya->id, 'event' => $event->id, 'pointRule' => $rule->id,
        ]))->assertRedirect();

        // No custom rule left — FestGradePointService's default CKSC-style table gives
        // grade A, position 1 => 8 points.
        $this->assertDatabaseHas('fest_results', [
            'event_id' => $event->id, 'school_id' => $school->id, 'item_id' => null, 'total_points' => 8,
        ]);
    }

    public function test_loading_the_confed_kalotsav_standard_recalculates_with_its_own_point_values(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event, 'school' => $school] = $this->makeFixture();

        $this->actingAs($admin)->post(route('sahodaya.events.point-rules.seed-confed-kalotsav', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]))->assertRedirect();

        // config/fest_confed_kalotsav_scoring.php: individual_points.A.1 = 10.
        $this->assertDatabaseHas('fest_results', [
            'event_id' => $event->id, 'school_id' => $school->id, 'item_id' => null, 'total_points' => 10,
        ]);
    }
}
