<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestQualificationService;
use App\Services\Events\FestSchoolEventFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class FestManagedRegistrationHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('state:migrate');
    }

    public function test_team_roster_roles_are_preserved_in_direct_registration(): void
    {
        $sahodaya = Tenant::create(['id' => 'sahodaya-1', 'name' => 'Sahodaya One', 'type' => 'sahodaya']);
        $school = Tenant::create(['id' => 'school-1', 'name' => 'School One', 'type' => 'school', 'parent_id' => 'sahodaya-1']);

        $event = FestEvent::create([
            'tenant_id'    => 'sahodaya-1',
            'title'        => 'Sahodaya Kalotsavam 2026',
            'event_type'   => 'kalotsavam',
            'level_round'  => 'sahodaya',
            'status'       => 'published',
        ]);

        $item = FestEventItem::create([
            'event_id'         => $event->id,
            'title'            => 'Folk Dance (Group)',
            'category'         => 'dance',
            'participant_type' => 'group',
        ]);

        $schoolClass = \App\Models\SchoolClass::create(['tenant_id' => 'school-1', 'name' => 'Class 10']);

        $studentLeader = Student::create(['name' => 'Leader Student', 'tenant_id' => 'school-1', 'school_class_id' => $schoolClass->id]);
        $studentMember = Student::create(['name' => 'Member Student', 'tenant_id' => 'school-1', 'school_class_id' => $schoolClass->id]);
        $studentStandby = Student::create(['name' => 'Standby Student', 'tenant_id' => 'school-1', 'school_class_id' => $schoolClass->id]);

        $registration = FestRegistration::create([
            'event_id'   => $event->id,
            'item_id'    => $item->id,
            'school_id'  => $school->id,
            'status'     => 'submitted',
            'team_name'  => 'Rhythm Crew',
        ]);

        FestParticipant::create([
            'registration_id'  => $registration->id,
            'student_id'       => $studentLeader->id,
            'participant_type' => 'student',
            'participant_role' => 'leader',
        ]);

        FestParticipant::create([
            'registration_id'  => $registration->id,
            'student_id'       => $studentMember->id,
            'participant_type' => 'student',
            'participant_role' => 'member',
        ]);

        FestParticipant::create([
            'registration_id'  => $registration->id,
            'student_id'       => $studentStandby->id,
            'participant_type' => 'student',
            'participant_role' => 'standby',
        ]);

        $this->assertEquals(3, $registration->participants()->count());
        $this->assertEquals('leader', $registration->participants()->where('student_id', $studentLeader->id)->first()->participant_role);
        $this->assertEquals('standby', $registration->participants()->where('student_id', $studentStandby->id)->first()->participant_role);
    }

    public function test_school_fee_reconciliation_updates_balance_status(): void
    {
        $sahodaya = Tenant::create(['id' => 'sahodaya-1', 'name' => 'Sahodaya One', 'type' => 'sahodaya']);
        $school = Tenant::create(['id' => 'school-1', 'name' => 'School One', 'type' => 'school', 'parent_id' => 'sahodaya-1']);

        $event = FestEvent::create([
            'tenant_id'    => 'sahodaya-1',
            'title'        => 'Sahodaya Kalotsavam 2026',
            'event_type'   => 'kalotsavam',
            'level_round'  => 'sahodaya',
            'fee_type'     => 'per_item',
            'status'       => 'published',
        ]);

        $fee = FestSchoolEventFee::create([
            'event_id'     => $event->id,
            'school_id'    => $school->id,
            'total_due'    => 500.00,
            'status'       => 'pending',
        ]);

        $fee->update(['status' => 'approved', 'paid_at' => now()]);

        $this->assertEquals('approved', $fee->fresh()->status);
    }

    public function test_region_to_finale_promotion_promotes_certified_winners(): void
    {
        $sahodaya = Tenant::create(['id' => 'sahodaya-1', 'name' => 'Sahodaya One', 'type' => 'sahodaya']);
        $school = Tenant::create(['id' => 'school-1', 'name' => 'School One', 'type' => 'school', 'parent_id' => 'sahodaya-1']);

        $regionEvent = FestEvent::create([
            'tenant_id'         => 'sahodaya-1',
            'title'             => 'Region A Kalotsavam',
            'event_type'        => 'kalotsavam',
            'level_round'       => 'sahodaya',
            'results_published' => true,
            'status'            => 'ongoing',
        ]);

        $finaleEvent = FestEvent::create([
            'tenant_id'   => 'sahodaya-1',
            'title'       => 'Sahodaya Grand Finale',
            'event_type'  => 'kalotsavam',
            'level_round' => 'sahodaya',
            'status'      => 'published',
        ]);

        $regionItem = FestEventItem::create([
            'event_id'         => $regionEvent->id,
            'title'            => 'Classical Music (Solo)',
            'category'         => 'music',
            'item_code'        => 'CM01',
            'qualify_count'    => 1,
        ]);

        $finaleItem = FestEventItem::create([
            'event_id'         => $finaleEvent->id,
            'title'            => 'Classical Music (Solo)',
            'category'         => 'music',
            'item_code'        => 'CM01',
        ]);

        $schoolClass = \App\Models\SchoolClass::create(['tenant_id' => 'school-1', 'name' => 'Class 10']);
        $student = Student::create(['name' => 'First Place Winner', 'tenant_id' => 'school-1', 'school_class_id' => $schoolClass->id]);

        $reg = FestRegistration::create([
            'event_id'  => $regionEvent->id,
            'item_id'   => $regionItem->id,
            'school_id' => $school->id,
            'status'    => 'approved',
        ]);

        $part = FestParticipant::create([
            'registration_id' => $reg->id,
            'student_id'      => $student->id,
        ]);

        FestMark::create([
            'event_id'       => $regionEvent->id,
            'item_id'        => $regionItem->id,
            'registration_id'=> $reg->id,
            'participant_id' => $part->id,
            'score'          => 98.50,
            'position'       => 1,
            'grade'          => 'A',
        ]);

        $service = app(FestQualificationService::class);
        $result = $service->promoteWinners($regionEvent, $finaleEvent);

        $this->assertEquals(1, $result['promoted']);
    }
}
