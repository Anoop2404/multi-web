<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipationPolicy;
use App\Models\FestRegistration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestRegistrationCreateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FestAutoApprovalFeeGatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_initial_status_is_approved_when_approval_policy_is_auto(): void
    {
        $sahodaya = Tenant::create([
            'id' => 'test-sahodaya-auto-approval-01',
            'name' => 'Test Sahodaya Auto Approval',
            'type' => 'sahodaya',
            'is_active' => true,
        ]);
        
        $sahodaya->run(function () use ($sahodaya) {
            $event = FestEvent::create([
                'tenant_id' => $sahodaya->id,
                'title' => 'Test Auto Approval Event',
                'event_type' => 'kalotsav',
                'status' => 'registration_open',
                'approval_policy' => 'auto',
                'fee_type' => 'per_student',
                'fee_amount' => 100.00,
            ]);

            $item = FestEventItem::create([
                'event_id' => $event->id,
                'title' => 'Solo Song',
                'participant_type' => 'individual',
                'is_enabled' => true,
            ]);

            $school = Tenant::create([
                'id' => 'test-school-auto-approval-01',
                'name' => 'Test School Auto Approval',
                'type' => 'school',
                'parent_id' => $sahodaya->id,
                'membership_status' => 'approved',
                'is_active' => true,
            ]);

            $class = SchoolClass::create([
                'tenant_id' => $school->id,
                'name' => 'Class 5',
            ]);

            $student = Student::create([
                'tenant_id' => $school->id,
                'school_class_id' => $class->id,
                'name' => 'Test Student',
                'admission_number' => '1001',
                'status' => 'active',
                'verified_at' => now(),
            ]);

            $service = app(FestRegistrationCreateService::class);
            $registration = $service->createForSchool($event, $item, $school, [$student->id]);

            $this->assertEquals('approved', $registration->status);
        });
    }
}
