<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestItemHead;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Services\Events\FestNumberingService;
use App\Services\Events\FestRegistrationApprovalService;
use Database\Seeders\SahodayaMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestNumberingServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, school: Tenant, event: FestEvent, headA: FestItemHead, headB: FestItemHead, itemA: FestEventItem, itemB: FestEventItem, itemC: FestEventItem} */
    private function sportsContext(): array
    {
        $this->seed(SahodayaMasterDataSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Numbering Test Sahodaya',
            'domain'    => 'numbering-test-sahodaya.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'         => $sahodaya->id,
            'prefix'            => 'NTS',
            'student_data_mode' => 'full_records',
        ]);

        $school = Tenant::create([
            'id'            => (string) Str::uuid(),
            'type'          => 'school',
            'name'          => 'Numbering Test School',
            'parent_id'     => $sahodaya->id,
            'school_prefix' => 'NT',
            'is_active'     => true,
        ]);

        $event = FestEvent::create([
            'tenant_id'          => $sahodaya->id,
            'title'              => 'Sports Fest',
            'event_type'         => 'sports',
            'level_round'        => 'sahodaya',
            'status'             => 'registration_open',
            'numbering_settings' => ['chest_no_start' => 1],
        ]);

        $headA = FestItemHead::create([
            'tenant_id'  => $sahodaya->id,
            'event_id'   => $event->id,
            'event_type' => 'sports',
            'name'       => 'Track',
            'slug'       => 'track',
            'sort_order' => 1,
        ]);

        $headB = FestItemHead::create([
            'tenant_id'  => $sahodaya->id,
            'event_id'   => $event->id,
            'event_type' => 'sports',
            'name'       => 'Field',
            'slug'       => 'field',
            'sort_order' => 2,
        ]);

        $itemA = FestEventItem::create([
            'event_id'         => $event->id,
            'head_id'          => $headA->id,
            'title'            => '100m Boys U14',
            'participant_type' => 'individual',
            'age_group'        => 'u14',
            'is_enabled'       => true,
        ]);

        $itemB = FestEventItem::create([
            'event_id'         => $event->id,
            'head_id'          => $headA->id,
            'title'            => '200m Boys U14',
            'participant_type' => 'individual',
            'age_group'        => 'u14',
            'is_enabled'       => true,
        ]);

        $itemC = FestEventItem::create([
            'event_id'         => $event->id,
            'head_id'          => $headB->id,
            'title'            => 'Long Jump U14',
            'participant_type' => 'individual',
            'age_group'        => 'u14',
            'is_enabled'       => true,
        ]);

        return compact('sahodaya', 'school', 'event', 'headA', 'headB', 'itemA', 'itemB', 'itemC');
    }

    private function student(Tenant $school, string $name = 'Test Athlete'): Student
    {
        $class = SchoolClass::firstOrCreate(
            ['tenant_id' => $school->id, 'name' => '10'],
            ['section' => 'A'],
        );

        return Student::create([
            'tenant_id'       => $school->id,
            'school_class_id' => $class->id,
            'name'            => $name,
            'gender'          => 'male',
        ]);
    }

    private function participant(
        FestEvent $event,
        FestEventItem $item,
        Tenant $school,
        ?Student $student = null,
        ?int $chestNo = null,
        int $chestHeadId = 0,
    ): FestParticipant {
        $registration = FestRegistration::create([
            'event_id'  => $event->id,
            'item_id'   => $item->id,
            'school_id' => $school->id,
            'mode'      => 'full',
            'status'    => 'approved',
        ]);

        return FestParticipant::create([
            'registration_id' => $registration->id,
            'event_id'        => $event->id,
            'chest_head_id'   => $chestHeadId ?: app(FestNumberingService::class)->chestHeadScope($event, $item),
            'student_id'      => $student?->id,
            'chest_no'        => $chestNo,
        ]);
    }

    public function test_sports_without_school_range_uses_event_start_for_active_performers_only(): void
    {
        ['event' => $event, 'itemA' => $itemA, 'itemB' => $itemB, 'school' => $school] = $this->sportsContext();
        $event->update(['numbering_settings' => ['chest_no_start' => 100]]);
        $firstStudent = $this->student($school, 'First Athlete');
        $first = $this->participant($event, $itemA, $school, $firstStudent);
        $sibling = $this->participant($event, $itemB, $school, $firstStudent);
        $second = $this->participant($event, $itemA, $school, $this->student($school, 'Second Athlete'));
        $standby = $this->participant($event, $itemA, $school, $this->student($school, 'Reserve Athlete'));
        $standby->update(['participant_role' => 'standby']);
        $disqualified = $this->participant($event, $itemA, $school, $this->student($school, 'Disqualified Athlete'));
        $disqualified->update(['disqualified_at' => now()]);
        $service = app(FestNumberingService::class);
        $service->assignMissingChestNumbers($event, schoolId: $school->id);
        $this->assertSame(100, $first->fresh()->chest_no);
        $this->assertSame(100, $sibling->fresh()->chest_no);
        $this->assertSame(101, $second->fresh()->chest_no);
        $this->assertNull($standby->fresh()->getRawOriginal('chest_no'));
        $this->assertNull($disqualified->fresh()->getRawOriginal('chest_no'));
        $service->assignParticipantNumbers($standby->fresh());
        $this->assertNull($standby->fresh()->getRawOriginal('chest_no'));
        $service->assignMissingChestNumbers($event, schoolId: $school->id);
        $this->assertSame(100, $first->fresh()->chest_no);
        $this->assertSame(101, $second->fresh()->chest_no);
    }

    public function test_sports_configured_school_range_takes_priority_over_event_start(): void
    {
        ['event' => $event, 'itemA' => $itemA, 'school' => $school] = $this->sportsContext();
        \App\Models\FestSchoolChestRange::create(['event_id' => $event->id, 'school_id' => $school->id, 'chest_no_start' => 500, 'chest_no_end' => 501]);
        $first = $this->participant($event, $itemA, $school, $this->student($school, 'First Athlete'));
        $second = $this->participant($event, $itemA, $school, $this->student($school, 'Second Athlete'));
        app(FestNumberingService::class)->assignMissingChestNumbers($event, schoolId: $school->id);
        $this->assertSame(500, $first->fresh()->chest_no);
        $this->assertSame(501, $second->fresh()->chest_no);
    }

    public function test_bulk_assignment_reconciles_legacy_numbers_across_heads_without_duplicating_students(): void
    {
        ['event' => $event, 'itemA' => $itemA, 'itemC' => $itemC, 'school' => $school] = $this->sportsContext();
        $student = $this->student($school);
        $first = $this->participant($event, $itemA, $school, $student, 101, $itemA->head_id);
        $second = $this->participant($event, $itemC, $school, $student, 202, $itemC->head_id);
        $other = $this->participant($event, $itemC, $school, $this->student($school, 'Other Athlete'));
        $service = app(FestNumberingService::class);
        $service->assignMissingChestNumbers($event);
        $this->assertSame(101, $first->fresh()->chest_no);
        $this->assertSame(101, $second->fresh()->chest_no);
        $this->assertNull($second->fresh()->getRawOriginal('chest_no'));
        $this->assertNotEquals(101, $other->fresh()->chest_no);
        $service->assignMissingChestNumbers($event);
        $this->assertSame(101, $second->fresh()->chest_no);
        $this->assertSame(1, FestParticipant::where('event_id', $event->id)->where('chest_no', 101)->count());
    }

    public function test_reconciling_legacy_head_numbers_does_not_assign_two_students_the_same_chest(): void
    {
        ['event' => $event, 'itemA' => $itemA, 'itemC' => $itemC, 'school' => $school] = $this->sportsContext();
        $first = $this->participant($event, $itemA, $school, $this->student($school, 'First Athlete'), 101, $itemA->head_id);
        $other = $this->participant($event, $itemC, $school, $this->student($school, 'Other Athlete'), 101, $itemC->head_id);
        $service = app(FestNumberingService::class);
        $service->assignMissingChestNumbers($event);
        $assigned = [$first->fresh()->chest_no, $other->fresh()->chest_no];
        $this->assertCount(2, array_unique($assigned));
        $this->assertNotContains(null, $assigned);
        $service->assignMissingChestNumbers($event);
        $this->assertSame($assigned, [$first->fresh()->chest_no, $other->fresh()->chest_no]);
    }

    public function test_sports_relay_members_keep_personal_chests_in_assignment_reports_and_edits(): void
    {
        ['event' => $event, 'itemA' => $itemA, 'school' => $school] = $this->sportsContext();
        $student = $this->student($school);
        $personal = $this->participant($event, $itemA, $school, $student, 2351);
        $relay = FestEventItem::create(['event_id' => $event->id, 'title' => 'Relay', 'participant_type' => 'group', 'is_enabled' => true]);
        $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $relay->id, 'school_id' => $school->id, 'status' => 'approved']);
        $group = \App\Models\FestGroup::create(['event_id' => $event->id, 'registration_id' => $registration->id, 'chest_no' => 2350]);
        $member = FestParticipant::create(['event_id' => $event->id, 'registration_id' => $registration->id, 'group_id' => $group->id, 'student_id' => $student->id, 'participant_role' => 'performer']);
        $newMember = FestParticipant::create(['event_id' => $event->id, 'registration_id' => $registration->id, 'group_id' => $group->id, 'student_id' => $this->student($school, 'Relay Only')->id, 'participant_role' => 'performer']);
        $reserve = FestParticipant::create(['event_id' => $event->id, 'registration_id' => $registration->id, 'group_id' => $group->id, 'student_id' => $this->student($school, 'Reserve')->id, 'participant_role' => 'standby']);
        $service = app(FestNumberingService::class);
        $service->assignMissingChestNumbers($event);
        $this->assertSame(2351, $member->fresh()->chest_no);
        $this->assertSame(1, $newMember->fresh()->chest_no);
        $this->assertNull($reserve->fresh()->chest_no);
        $this->assertSame('1, 2351', $service->entryChestLabel($member->fresh()));
        $report = new \App\Services\Events\FestEventReportAnalyticsService($event);
        $this->assertSame([2351, 1], collect($report->itemWiseReportRows(itemId: $relay->id))->sortBy('id')->pluck('chest_no')->all());
        ob_start();
        $report->exportNumberingRegister()->sendContent();
        $xml = ob_get_clean();
        $this->assertStringNotContainsString('>Head</Data>', $xml);
        app(\App\Services\Events\FestChestNumberService::class)->setChest($member->fresh(), 777);
        $this->assertSame(777, $personal->fresh()->chest_no);
        $this->assertSame(777, $member->fresh()->chest_no);
        $this->assertSame(1, $newMember->fresh()->chest_no);
        $service->assignMissingChestNumbers($event);
        $this->assertSame(777, $member->fresh()->chest_no);

        $chests = app(\App\Services\Events\FestChestNumberService::class);
        // Clear from the relay row, including the stored owner in an individual item.
        $chests->clearChest($member->fresh());
        $this->assertNull($personal->fresh()->chest_no);
        $this->assertNull($member->fresh()->chest_no, 'Legacy team chest must not reappear as a fallback.');
        $this->assertSame(1, $newMember->fresh()->chest_no);
        FestParticipant::whereIn('id', [$personal->id, $member->id, $newMember->id])->update(['chest_revealed_at' => now()]);
        $group->update(['event_id' => null, 'chest_revealed_at' => now()]);
        \App\Models\FestMark::create(['event_id' => $event->id, 'item_id' => $itemA->id, 'participant_id' => $personal->id, 'score' => 75]);
        $otherEvent = FestEvent::create(['tenant_id' => $event->tenant_id, 'title' => 'Other event', 'event_type' => 'sports', 'status' => 'ongoing']);
        $otherItem = FestEventItem::create(['event_id' => $otherEvent->id, 'title' => 'Other item', 'participant_type' => 'individual']);
        $untouched = $this->participant($otherEvent, $otherItem, $school, $student, 999);
        $chests->clearAllForEvent($event);
        foreach ([$personal, $member, $newMember, $reserve] as $row) {
            $this->assertNull($row->fresh()->chest_no);
            $this->assertNull($row->fresh()->chest_revealed_at);
        }
        $this->assertNull($group->fresh()->chest_no);
        $this->assertNull($group->fresh()->chest_revealed_at);
        $this->assertSame(999, $untouched->fresh()->chest_no);
        $this->assertDatabaseHas('fest_marks', ['participant_id' => $personal->id, 'score' => 75]);
        $this->assertDatabaseHas('fest_registrations', ['id' => $registration->id]);
        $service->assignMissingChestNumbers($event);
        $this->assertSame(1, $personal->fresh()->chest_no);
        $this->assertSame(1, $member->fresh()->chest_no);
        $this->assertSame(2, $newMember->fresh()->chest_no);
    }

    public function test_next_chest_number_is_unique_within_item_head(): void
    {
        ['event' => $event, 'itemA' => $itemA, 'itemB' => $itemB, 'school' => $school] = $this->sportsContext();
        $studentA = $this->student($school);
        $studentB = $this->student($school, 'Second Athlete');

        $this->participant($event, $itemA, $school, $studentA, 1);

        $service = app(FestNumberingService::class);

        $this->assertSame(2, $service->nextChestNumber($event, $itemB));

        $participantB = FestParticipant::create([
            'registration_id' => FestRegistration::create([
                'event_id'  => $event->id,
                'item_id'   => $itemB->id,
                'school_id' => $school->id,
                'mode'      => 'full',
                'status'    => 'submitted',
            ])->id,
            'event_id'      => $event->id,
            'chest_head_id' => $service->chestHeadScope($event, $itemB),
            'student_id'    => $studentB->id,
        ]);

        ['chest' => $chest, 'persist' => $persist] = $service->resolveChestAssignment($event, $itemB, $participantB);
        $this->assertSame(2, $chest);
        $this->assertTrue($persist);
    }

    public function test_same_student_reuses_chest_number_within_item_head(): void
    {
        ['event' => $event, 'itemA' => $itemA, 'itemB' => $itemB, 'school' => $school] = $this->sportsContext();
        $student = $this->student($school);

        $first = $this->participant($event, $itemA, $school, $student, 7);

        $registrationB = FestRegistration::create([
            'event_id'  => $event->id,
            'item_id'   => $itemB->id,
            'school_id' => $school->id,
            'mode'      => 'full',
            'status'    => 'submitted',
        ]);

        $service = app(FestNumberingService::class);
        $second = FestParticipant::create([
            'registration_id' => $registrationB->id,
            'event_id'        => $event->id,
            'chest_head_id'   => $service->chestHeadScope($event, $itemB),
            'student_id'      => $student->id,
        ]);

        ['chest' => $chest, 'persist' => $persist] = $service->resolveChestAssignment($event, $itemB, $second);

        $this->assertSame(7, $chest);
        $this->assertFalse($persist);

        app(FestRegistrationApprovalService::class)->approve($registrationB->fresh(['participants', 'item', 'event']));

        $second->refresh();
        $this->assertNull($second->getAttributes()['chest_no']);
        $this->assertSame(7, $second->chest_no);
        $this->assertSame(7, $first->fresh()->chest_no);
    }

    public function test_shared_sports_chest_resolves_in_item_report_and_partially_loaded_relations(): void
    {
        ['event' => $event, 'itemA' => $itemA, 'itemB' => $itemB, 'school' => $school] = $this->sportsContext();
        $student = $this->student($school);
        $this->participant($event, $itemA, $school, $student, 2450);
        $second = $this->participant($event, $itemB, $school, $student);
        $partial = FestParticipant::with(['registration.event:id,title', 'registration.item:id,title'])->findOrFail($second->id);
        $this->assertSame(2450, $partial->chest_no);
        $rows = collect((new \App\Services\Events\FestEventReportAnalyticsService($event))->itemWiseReportRows());
        $this->assertCount(2, $rows);
        $this->assertSame([2450, 2450], $rows->pluck('chest_no')->all());
        $this->assertNull($second->fresh()->getRawOriginal('chest_no'), 'Display lookup must not copy or regenerate the stored number.');
    }

    public function test_same_student_reuses_chest_number_for_different_item_head(): void
    {
        ['event' => $event, 'itemA' => $itemA, 'itemC' => $itemC, 'school' => $school] = $this->sportsContext();
        $student = $this->student($school);

        $this->participant($event, $itemA, $school, $student, 7);

        $registrationC = FestRegistration::create([
            'event_id'  => $event->id,
            'item_id'   => $itemC->id,
            'school_id' => $school->id,
            'mode'      => 'full',
            'status'    => 'submitted',
        ]);

        $service = app(FestNumberingService::class);
        $fieldParticipant = FestParticipant::create([
            'registration_id' => $registrationC->id,
            'event_id'        => $event->id,
            'chest_head_id'   => $service->chestHeadScope($event, $itemC),
            'student_id'      => $student->id,
        ]);

        ['chest' => $chest, 'persist' => $persist] = $service->resolveChestAssignment($event, $itemC, $fieldParticipant);

        $this->assertSame(7, $chest);
        $this->assertFalse($persist);
    }

    /**
     * Regression coverage for a real bug report: an admin sets item B's own
     * chest_no_start (e.g. 500) expecting its numbering to start there, but once
     * item A (sharing the same head) has already assigned numbers past 500, every
     * new item B participant silently jumped onto item A's sequence instead —
     * nextChestNumber() only ever tracked the single highest number in the shared
     * head and always continued past it (max($start, $highest + 1)), so a later
     * item's own configured start could never "win" once a sibling had climbed
     * above it. Fixed by scanning for the lowest number at-or-above the item's own
     * start that isn't already used anywhere in the head, instead of always
     * jumping past the head-wide maximum.
     */
    public function test_sports_uses_event_fallback_instead_of_legacy_item_starts(): void
    {
        ['event' => $event, 'itemA' => $itemA, 'itemB' => $itemB, 'school' => $school] = $this->sportsContext();
        $itemA->update(['chest_no_start' => 100]);
        $itemB->update(['chest_no_start' => 500]);
        $service = app(FestNumberingService::class);
        $this->assertSame(1, $service->nextChestNumber($event, $itemA));
        $this->participant($event, $itemA, $school, $this->student($school), 1);
        $this->assertSame(2, $service->nextChestNumber($event, $itemB));
    }

    public function test_bulk_assignment_reuses_canonical_chest_without_persisting_duplicate(): void
    {
        ['event' => $event, 'itemA' => $itemA, 'itemB' => $itemB, 'school' => $school] = $this->sportsContext();
        $student = $this->student($school);
        $this->participant($event, $itemA, $school, $student, 3202);
        $sibling = $this->participant($event, $itemB, $school, $student);
        $service = app(FestNumberingService::class);
        $service->assignMissingChestNumbers($event);
        $sibling->refresh();
        $this->assertNull($service->persistedChestNumber($sibling));
        $this->assertSame(3202, $sibling->chest_no);
        $service->assignMissingChestNumbers($event);
        $this->assertSame(1, FestParticipant::where('event_id', $event->id)->where('chest_no', 3202)->count());
    }

    public function test_assigns_chest_on_create_for_first_sports_item_in_head(): void
    {
        ['event' => $event, 'itemA' => $itemA, 'school' => $school] = $this->sportsContext();
        $student = $this->student($school);

        $registration = FestRegistration::create([
            'event_id'  => $event->id,
            'item_id'   => $itemA->id,
            'school_id' => $school->id,
            'status'    => 'submitted',
        ]);

        $participant = FestParticipant::create([
            'registration_id' => $registration->id,
            'student_id'      => $student->id,
            'participant_role'=> 'performer',
        ]);

        app(FestNumberingService::class)->assignParticipantNumbers($participant->fresh(['registration.event', 'registration.item', 'student']));

        $participant->refresh();
        $this->assertSame(1, $participant->getAttributes()['chest_no']);
        $this->assertSame(FestNumberingService::CHEST_SCOPE_EVENT, $participant->chest_head_id);
    }
}
