<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestEventReportAnalyticsService;
use App\Services\Events\FestParticipationLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestRemovedParticipantReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_withdrawn_and_fee_pipeline_students_are_excluded_from_item_wise_and_limits(): void
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Report Sahodaya',
            'is_active' => true,
        ]);
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Report School',
            'parent_id' => $sahodaya->id,
            'is_active' => true,
        ]);
        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => '5']);
        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Athletic Meet',
            'event_type' => 'sports',
            'status' => 'published',
        ]);
        $item = FestEventItem::create([
            'event_id' => $event->id,
            'title' => '100 M',
            'participant_type' => 'individual',
            'is_enabled' => true,
        ]);

        $activeStudent = Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Active', 'status' => 'active']);
        $withdrawnStudent = Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Withdrawn', 'status' => 'active']);
        $pipelineStudent = Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Pipeline', 'status' => 'active']);
        $standbyStudent = Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Standby', 'status' => 'active']);

        $activeReg = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved',
        ]);
        FestParticipant::create([
            'registration_id' => $activeReg->id, 'student_id' => $activeStudent->id,
            'participant_type' => 'student', 'participant_role' => 'performer',
        ]);

        $withdrawnReg = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'withdrawn',
        ]);
        FestParticipant::create([
            'registration_id' => $withdrawnReg->id, 'student_id' => $withdrawnStudent->id,
            'participant_type' => 'student', 'participant_role' => 'performer',
        ]);

        $pipelineReg = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'proof_uploaded',
        ]);
        FestParticipant::create([
            'registration_id' => $pipelineReg->id, 'student_id' => $pipelineStudent->id,
            'participant_type' => 'student', 'participant_role' => 'performer',
        ]);

        FestParticipant::create([
            'registration_id' => $activeReg->id, 'student_id' => $standbyStudent->id,
            'participant_type' => 'student', 'participant_role' => 'standby',
        ]);

        $itemWise = collect((new FestEventReportAnalyticsService($event))->itemWiseReportRows($school->id));
        $this->assertCount(1, $itemWise);
        $this->assertSame('Active', $itemWise->first()['participant']);

        $limits = collect((new FestParticipationLimitService($event))->studentLimitReportRows($school->id));
        $this->assertCount(1, $limits);
        $this->assertSame('Active', $limits->first()['name']);
    }
}
