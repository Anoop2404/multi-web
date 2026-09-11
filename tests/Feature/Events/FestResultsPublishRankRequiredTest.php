<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Services\Events\FestItemResultsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Publishing an item previously only required every performer to have a grade/score/
 * position entered (any one of the three) -- a mark could carry a score with position
 * left null and still count as "marked", so an item could be published with nobody
 * actually ranked. assertCanPublish() now separately requires every performer to have a
 * position, mirroring the existing marks-entered check but scoped to the position column
 * specifically. Absent participants are excluded from the denominator the same way the
 * marks-entered check already excludes them (see FestEventReportAnalyticsService::
 * assignmentCompletenessRows()'s performer query).
 */
class FestResultsPublishRankRequiredTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{event: FestEvent, item: FestEventItem, school: Tenant} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Rank Required Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'RR', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Rank Required School', 'domain' => Str::uuid().'.test',
            'membership_status' => 'approved', 'is_active' => true,
        ]);

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Rank Required Event', 'event_type' => 'kalolsavam']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Group Song', 'item_code' => 'RR1', 'total_marks' => 100]);

        return compact('event', 'item', 'school');
    }

    private function addPerformer(FestEvent $event, FestEventItem $item, Tenant $school, int $studentId): FestParticipant
    {
        $registration = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved',
        ]);

        return FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $event->id, 'student_id' => $studentId,
            'participant_type' => 'student', 'participant_role' => 'performer',
        ]);
    }

    public function test_publish_is_blocked_when_a_performer_has_a_score_but_no_rank(): void
    {
        $f = $this->fixture();
        $performer = $this->addPerformer($f['event'], $f['item'], $f['school'], 4001);

        // Score entered, position left blank -- exactly the gap this closes.
        FestMark::create(['event_id' => $f['event']->id, 'item_id' => $f['item']->id, 'participant_id' => $performer->id, 'score' => 75]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Assign ranks for all participants before publishing');

        app(FestItemResultsService::class)->assertCanPublish($f['item']->fresh());
    }

    public function test_publish_succeeds_once_every_performer_is_ranked(): void
    {
        $f = $this->fixture();
        $performer = $this->addPerformer($f['event'], $f['item'], $f['school'], 4002);

        FestMark::create(['event_id' => $f['event']->id, 'item_id' => $f['item']->id, 'participant_id' => $performer->id, 'score' => 75, 'position' => 1]);

        app(FestItemResultsService::class)->assertCanPublish($f['item']->fresh());
        $this->addToAssertionCount(1);
    }

    public function test_publish_is_not_blocked_by_an_absent_participant_missing_a_rank(): void
    {
        $f = $this->fixture();
        $ranked = $this->addPerformer($f['event'], $f['item'], $f['school'], 4003);
        $absent = $this->addPerformer($f['event'], $f['item'], $f['school'], 4004);

        FestMark::create(['event_id' => $f['event']->id, 'item_id' => $f['item']->id, 'participant_id' => $ranked->id, 'score' => 80, 'position' => 1]);
        \App\Models\FestAttendance::create([
            'event_id' => $f['event']->id, 'item_id' => $f['item']->id, 'participant_id' => $absent->id, 'status' => 'absent',
        ]);

        app(FestItemResultsService::class)->assertCanPublish($f['item']->fresh());
        $this->addToAssertionCount(1);
    }
}
