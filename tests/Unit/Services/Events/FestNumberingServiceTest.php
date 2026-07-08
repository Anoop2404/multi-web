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
            'tenant_id'   => $sahodaya->id,
            'title'       => 'Sports Fest',
            'event_type'  => 'sports',
            'level_round' => 'sahodaya',
            'status'      => 'registration_open',
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

    public function test_same_student_gets_new_chest_number_for_different_item_head(): void
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

        $this->assertSame(1, $chest);
        $this->assertTrue($persist);
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
        $this->assertSame($itemA->head_id, $participant->chest_head_id);
    }
}
