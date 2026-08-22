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
            'judge_scores' => ['1' => 95, '2' => 500],
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
            'judge_scores' => ['1' => 95, '2' => 88],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('fest_marks', [
            'participant_id' => $participant->id,
            'item_id' => $item->id,
            'score' => 183,
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
}
