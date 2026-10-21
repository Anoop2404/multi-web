<?php

namespace Tests\Feature\Events;

use App\Models\{FestEvent, FestEventItem, FestMark, FestParticipant, FestRegistration, SahodayaProfile, Tenant, User};
use App\Services\Events\{FestMarkSaveService, FestSportsAutoRankService};
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SportsEvaluationReviewRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $method = 'time'): array
    {
        $tenant = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Evaluation Review', 'is_active' => true]);
        $school = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $tenant->id, 'name' => 'Review School', 'membership_status' => 'approved', 'is_active' => true]);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'email_verified_at' => now()]);
        $event = FestEvent::create(['tenant_id' => $tenant->id, 'title' => 'Review Meet', 'event_type' => 'sports', 'level_round' => 'sahodaya', 'conduct_levels' => ['school', 'sahodaya'], 'status' => 'ongoing']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Test Item', 'result_method' => $method, 'is_enabled' => true]);
        $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
        $participant = FestParticipant::create(['registration_id' => $registration->id, 'event_id' => $event->id, 'participant_type' => 'student', 'participant_role' => 'performer']);
        return compact('tenant', 'school', 'user', 'event', 'item', 'participant');
    }

    public function test_school_round_spawn_endpoint_accepts_its_controller_arguments(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        extract($this->fixture());
        SahodayaProfile::create(['tenant_id' => $tenant->id, 'prefix' => 'REV', 'student_data_mode' => 'counts_only']);
        $user->assignRole('sahodaya_admin');
        $this->withoutExceptionHandling();
        $this->actingAs($user)->post(route('sahodaya.events.spawn-school-rounds', ['tenantId' => $tenant->id, 'event' => $event->id]))->assertRedirect();
        $this->assertDatabaseHas('fest_events', ['parent_event_id' => $event->id, 'conducting_school_id' => $school->id]);
    }

    public function test_measured_item_rejects_rank_with_explicit_null_measurement(): void
    {
        extract($this->fixture());
        $this->expectException(ValidationException::class);
        app(FestMarkSaveService::class)->save($event, ['participant_id' => $participant->id, 'item_id' => $item->id, 'position' => 1, 'measurement_value' => null], $user->id, false);
    }

    public function test_measured_item_cannot_clear_its_measurement_while_retaining_rank(): void
    {
        extract($this->fixture());
        FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 1, 'measurement_value' => '12.5']);
        $this->expectException(ValidationException::class);
        app(FestMarkSaveService::class)->save($event, ['participant_id' => $participant->id, 'item_id' => $item->id, 'position' => 1, 'measurement_value' => null], $user->id, false);
    }

    public function test_time_result_method_ranks_minute_format_against_seconds(): void
    {
        extract($this->fixture());
        $first = FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'measurement_value' => '1:02.5']);
        $other = $participant->replicate();
        $other->save();
        $second = FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $other->id, 'measurement_value' => '65']);
        app(FestSportsAutoRankService::class)->rankItem($event, $item);
        $this->assertSame(1, $first->fresh()->position);
        $this->assertSame(2, $second->fresh()->position);
    }

    public function test_rank_only_item_rejects_auto_ranking(): void
    {
        extract($this->fixture('rank'));
        $this->expectException(ValidationException::class);
        app(FestSportsAutoRankService::class)->rankItem($event, $item);
    }
    public function test_non_sports_school_round_spawn_keeps_its_program_type(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        extract($this->fixture('rank'));
        $event->update(['event_type' => 'kalolsavam']);
        SahodayaProfile::create(['tenant_id' => $tenant->id, 'prefix' => 'REV', 'student_data_mode' => 'counts_only']);
        $user->assignRole('sahodaya_admin');
        $this->actingAs($user)->post(route('sahodaya.events.spawn-school-rounds', ['tenantId' => $tenant->id, 'event' => $event->id]))->assertRedirect();
        $child = FestEvent::where('parent_event_id', $event->id)->firstOrFail();
        $this->assertTrue($child->isSchoolResultsOnly());
        $this->assertSame('kalolsavam', $child->event_type);
    }

    public function test_measured_item_rejects_whitespace_and_keeps_existing_result(): void
    {
        extract($this->fixture('distance'));
        $mark = FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 1, 'measurement_value' => '4.2']);
        try {
            app(FestMarkSaveService::class)->save($event, ['participant_id' => $participant->id, 'item_id' => $item->id, 'measurement_value' => '  '], $user->id, false);
            $this->fail('A ranked measurement must not be cleared.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('measurement_value', $error->errors());
            $this->assertSame('4.2', $mark->fresh()->measurement_value);
        }
    }

    public function test_existing_measurement_can_be_retained_when_only_rank_changes(): void
    {
        extract($this->fixture());
        $mark = FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $participant->id, 'position' => 1, 'measurement_value' => '12.5']);
        app(FestMarkSaveService::class)->save($event, ['participant_id' => $participant->id, 'item_id' => $item->id, 'position' => 2], $user->id, false);
        $this->assertSame(2, $mark->fresh()->position);
        $this->assertSame('12.5', $mark->fresh()->measurement_value);
    }

    public function test_assigned_judge_saves_shared_rank_only_result_and_can_read_it_back(): void
    {
        extract($this->fixture('rank'));
        $event->update(['event_type' => 'kalolsavam']);
        \App\Models\FestJudgeAssignment::create(['event_id' => $event->id, 'item_id' => $item->id, 'user_id' => $user->id]);
        $service = app(\App\Services\Events\FestJudgeScoreService::class);
        $service->save($event, ['participant_id' => $participant->id, 'item_id' => $item->id, 'position' => 2, 'score' => 99], $user->id);
        $this->assertDatabaseHas('fest_marks', ['participant_id' => $participant->id, 'position' => 2, 'locked_by' => $user->id]);
        $this->assertSame(2, $service->scoresForJudge($event, $user->id, [$item->id])[$participant->id]->position);
        $this->assertDatabaseMissing('fest_judge_scores', ['participant_id' => $participant->id]);
    }

    public function test_unassigned_judge_cannot_save_a_rank_only_result(): void
    {
        extract($this->fixture('rank'));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(\App\Services\Events\FestJudgeScoreService::class)->save($event, ['participant_id' => $participant->id, 'item_id' => $item->id, 'position' => 1], $user->id);
    }

    public function test_distance_method_prefers_the_larger_result_and_shares_tied_ranks(): void
    {
        extract($this->fixture('distance'));
        $marks = [];
        foreach (['4.2', '4.8', '4.8'] as $measurement) {
            $athlete = $participant->replicate();
            $athlete->save();
            $marks[] = FestMark::create(['event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $athlete->id, 'measurement_value' => $measurement]);
        }
        app(FestSportsAutoRankService::class)->rankItem($event, $item);
        $this->assertSame([2, 1, 1], array_map(fn ($mark) => $mark->fresh()->position, $marks));
    }

    public function test_school_sports_route_validates_and_saves_through_the_shared_service(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        extract($this->fixture());
        SahodayaProfile::create(['tenant_id' => $tenant->id, 'prefix' => 'REV', 'student_data_mode' => 'counts_only']);
        $user->update(['tenant_id' => $school->id]);
        $user->assignRole('school_admin');
        $event->update(['level_round' => 'school', 'conducting_school_id' => $school->id]);
        $url = route('school.sports.my-event.marks.store', ['tenantId' => $school->id, 'event' => $event->id]);
        $data = ['participant_id' => $participant->id, 'item_id' => $item->id, 'position' => 1, 'measurement_value' => null];
        $this->actingAs($user)->post($url, $data)->assertSessionHasErrors('measurement_value');
        $this->assertDatabaseMissing('fest_marks', ['participant_id' => $participant->id]);
        $data['measurement_value'] = '12.5';
        $this->actingAs($user)->post($url, $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fest_marks', ['participant_id' => $participant->id, 'position' => 1, 'measurement_value' => '12.5']);
    }

    public function test_judge_rank_only_http_save_and_dashboard_progress(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        extract($this->fixture('rank'));
        SahodayaProfile::create(['tenant_id' => $tenant->id, 'prefix' => 'REV', 'student_data_mode' => 'counts_only']);
        $event->update(['event_type' => 'kalolsavam']);
        $user->assignRole('judge');
        \App\Models\FestJudgeAssignment::create(['event_id' => $event->id, 'item_id' => $item->id, 'user_id' => $user->id]);
        $this->actingAs($user)->post(route('portal.judge.marks.store', ['tenantId' => $tenant->id, 'event' => $event->id]), [
            'participant_id' => $participant->id, 'item_id' => $item->id, 'position' => 1,
            'score' => null, 'grade' => null, 'measurement_value' => null, 'measurement_unit' => null,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fest_marks', ['participant_id' => $participant->id, 'position' => 1]);
        $this->get(route('portal.judge.dashboard', ['tenantId' => $tenant->id]))->assertInertia(fn ($page) => $page->where('itemProgress.0.marked', 1));
    }

    public function test_school_sports_draft_round_allows_mark_entry_and_auto_rank(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        extract($this->fixture('time'));
        SahodayaProfile::create(['tenant_id' => $tenant->id, 'prefix' => 'REV', 'student_data_mode' => 'counts_only']);
        $user->update(['tenant_id' => $school->id]);
        $user->assignRole('school_admin');

        // School round created in draft
        $event->update([
            'level_round'          => 'school',
            'conducting_school_id' => $school->id,
            'status'               => 'draft',
        ]);

        $url = route('school.sports.my-event.marks.store', ['tenantId' => $school->id, 'event' => $event->id]);
        $this->actingAs($user)->post($url, [
            'participant_id'    => $participant->id,
            'item_id'           => $item->id,
            'position'          => null,
            'measurement_value' => '11.8',
            'measurement_unit'  => 's',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('fest_marks', ['participant_id' => $participant->id, 'measurement_value' => '11.8']);

        $rankUrl = route('school.sports.my-event.auto-rank', ['tenantId' => $school->id, 'event' => $event->id, 'item' => $item->id]);
        $this->actingAs($user)->post($rankUrl)->assertRedirect()->assertSessionHas('success');
        $this->assertSame(1, FestMark::where('participant_id', $participant->id)->first()->position);
    }

    public function test_school_sports_bulk_save_and_auto_rank(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        extract($this->fixture('time'));
        SahodayaProfile::create(['tenant_id' => $tenant->id, 'prefix' => 'REV', 'student_data_mode' => 'counts_only']);
        $user->update(['tenant_id' => $school->id]);
        $user->assignRole('school_admin');

        $event->update([
            'level_round'          => 'school',
            'conducting_school_id' => $school->id,
            'status'               => 'draft',
        ]);

        $p2 = $participant->replicate();
        $p2->save();

        $bulkUrl = route('school.sports.my-event.marks.bulk', ['tenantId' => $school->id, 'event' => $event->id]);
        $this->actingAs($user)->post($bulkUrl, [
            'rows' => [
                ['participant_id' => $participant->id, 'item_id' => $item->id, 'measurement_value' => '12.4', 'measurement_unit' => 's'],
                ['participant_id' => $p2->id, 'item_id' => $item->id, 'measurement_value' => '11.2', 'measurement_unit' => 's'],
            ],
            'auto_rank_item_id' => $item->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        // 11.2 is faster, so p2 should be rank 1, participant rank 2
        $this->assertSame(2, FestMark::where('participant_id', $participant->id)->first()->position);
        $this->assertSame(1, FestMark::where('participant_id', $p2->id)->first()->position);
    }
}
