<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\FestMarkCriteriaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A judge's own subtotal previously had no ceiling at all — unlike per-criterion
 * scores, which are already clamped to each criterion's own max_score. This covers
 * the fix: each judge's entry is capped at the item's Total Marks.
 */
class FestJudgeScoreCapTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, admin: User, event: FestEvent, item: FestEventItem, participant: FestParticipant} */
    private function fixture(?float $totalMarks = 100): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Judge Cap Sahodaya',
            'domain' => 'judge-cap-'.Str::random(8).'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'JC', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Judge Cap School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Judge Cap Event',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'ongoing',
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Conversation (Pair)',
            'category' => 'literary',
            'item_code' => 'CONV',
            'total_marks' => $totalMarks,
        ]);
        app(FestMarkCriteriaService::class)->setJudgeCount($item, 2);

        $registration = FestRegistration::create([
            'event_id' => $event->id,
            'item_id' => $item->id,
            'school_id' => $school->id,
            'status' => 'approved',
        ]);
        $participant = FestParticipant::create([
            'registration_id' => $registration->id,
            'participant_type' => 'student',
            'participant_role' => 'performer',
        ]);

        return compact('sahodaya', 'admin', 'event', 'item', 'participant');
    }

    public function test_judge_score_exceeding_total_marks_is_rejected(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event, 'item' => $item, 'participant' => $participant] = $this->fixture(totalMarks: 100);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.marks.store', [
            'tenantId' => $sahodaya->id,
            'event' => $event->id,
        ]), [
            'participant_id' => $participant->id,
            'item_id' => $item->id,
            'judge_scores' => ['1' => 45, '2' => 500],
        ]);

        $response->assertSessionHasErrors('judge_scores.2');
        $this->assertDatabaseCount('fest_marks', 0);
    }

    public function test_judge_score_within_total_marks_is_accepted(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event, 'item' => $item, 'participant' => $participant] = $this->fixture(totalMarks: 100);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.marks.store', [
            'tenantId' => $sahodaya->id,
            'event' => $event->id,
        ]), [
            'participant_id' => $participant->id,
            'item_id' => $item->id,
            'judge_scores' => ['1' => 45, '2' => 48],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('fest_marks', [
            'participant_id' => $participant->id,
            'item_id' => $item->id,
            'score' => 93,
        ]);
    }

    public function test_judge_score_is_unbounded_when_total_marks_is_not_set(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event, 'item' => $item, 'participant' => $participant] = $this->fixture(totalMarks: null);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.marks.store', [
            'tenantId' => $sahodaya->id,
            'event' => $event->id,
        ]), [
            'participant_id' => $participant->id,
            'item_id' => $item->id,
            'judge_scores' => ['1' => 9999],
        ]);

        $response->assertSessionDoesntHaveErrors();
    }
    public function test_direct_score_cannot_exceed_the_configured_maximum(): void
    {
        $env = $this->fixture(totalMarks: 100);
        $response = $this->actingAs($env['admin'])->post(route('sahodaya.events.marks.store', [
            'tenantId' => $env['sahodaya']->id, 'event' => $env['event']->id,
        ]), ['participant_id' => $env['participant']->id, 'item_id' => $env['item']->id, 'score' => 101]);
        $response->assertSessionHasErrors('score');
        $this->assertDatabaseCount('fest_marks', 0);
    }

    public function test_judge_numbers_outside_the_configured_panel_are_rejected(): void
    {
        $env = $this->fixture(totalMarks: 100);
        $response = $this->actingAs($env['admin'])->post(route('sahodaya.events.marks.store', [
            'tenantId' => $env['sahodaya']->id, 'event' => $env['event']->id,
        ]), ['participant_id' => $env['participant']->id, 'item_id' => $env['item']->id,
            'judge_scores' => [1 => 40, 2 => 40, 3 => 10]]);
        $response->assertSessionHasErrors('judge_scores');
        $this->assertDatabaseCount('fest_mark_judge_scores', 0);
        $this->assertDatabaseCount('fest_marks', 0);
    }

    public function test_zero_maximum_marks_is_rejected_in_bulk_settings(): void
    {
        $env = $this->fixture(totalMarks: 100);
        $response = $this->actingAs($env['admin'])->post(route('sahodaya.events.mark-settings.bulk-update', [
            'tenantId' => $env['sahodaya']->id, 'event' => $env['event']->id,
        ]), ['items' => [['id' => $env['item']->id, 'total_marks' => 0, 'judge_count' => 2]]]);
        $response->assertSessionHasErrors('items.0.total_marks');
        $this->assertSame('100.00', $env['item']->fresh()->total_marks);
    }

    public function test_reducing_the_maximum_cannot_invalidate_saved_judge_marks(): void
    {
        $env = $this->fixture(totalMarks: 100);
        $service = app(FestMarkCriteriaService::class);
        $service->saveParticipantJudgeScores($env['item'], $env['participant']->id, [1 => 40, 2 => 45]);
        $response = $this->actingAs($env['admin'])->post(route('sahodaya.events.mark-settings.bulk-update', [
            'tenantId' => $env['sahodaya']->id, 'event' => $env['event']->id,
        ]), ['items' => [['id' => $env['item']->id, 'total_marks' => 60, 'judge_count' => 2]]]);
        $response->assertSessionHasErrors('total_marks');
        $this->assertSame('100.00', $env['item']->fresh()->total_marks);
    }

    public function test_reducing_the_panel_cannot_drop_saved_judge_marks(): void
    {
        $env = $this->fixture(totalMarks: 100);
        $service = app(FestMarkCriteriaService::class);
        $service->saveParticipantJudgeScores($env['item'], $env['participant']->id, [1 => 40, 2 => 45]);
        try {
            $service->setJudgeCount($env['item'], 1);
            $this->fail('A judge with saved marks must not be removed.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('judge_count', $e->errors());
        }
        $this->assertSame(2, $service->judgeCountForItem($env['item']->fresh()));
    }

    public function test_item_grade_range_cannot_exceed_maximum_marks(): void
    {
        $env = $this->fixture(totalMarks: 50);
        $response = $this->actingAs($env['admin'])->post(route('sahodaya.events.grade-configs.store', [
            'tenantId' => $env['sahodaya']->id, 'event' => $env['event']->id,
        ]), ['item_id' => $env['item']->id, 'grade' => 'Excellent', 'min_score' => 40, 'max_score' => 60]);
        $response->assertSessionHasErrors('max_score');
        $this->assertDatabaseCount('fest_grade_configs', 0);
    }

    public function test_criterion_scores_are_rejected_instead_of_silently_clamped(): void
    {
        $env = $this->fixture(totalMarks: 100);
        $service = app(FestMarkCriteriaService::class);
        $criteria = $service->saveCriteria($env['event'], $env['item'], [['label' => 'Content', 'max_score' => 20]]);
        try {
            $service->saveParticipantScores($env['item'], $env['participant']->id, [$criteria->first()->id => 25]);
            $this->fail('Out-of-range criterion scores must be rejected.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('criteria_scores.'.$criteria->first()->id, $e->errors());
        }
        $this->assertDatabaseCount('fest_mark_criterion_scores', 0);
    }

}
