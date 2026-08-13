<?php

namespace Tests\Unit\Services\School;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestSchoolEventFee;
use App\Models\Registration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestRegistrationCreateService;
use App\Services\Events\FestSchoolEventFeeService;
use App\Services\School\SchoolDocumentDownloadGateService;
use App\Support\AcademicYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SchoolDocumentDownloadGateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_downloads_are_blocked_until_event_fee_is_paid_and_approved(): void
    {
        $sahodaya = Tenant::create([
            'id' => 'sahodaya-dl-gate',
            'name' => 'Sahodaya DL Gate Test',
            'type' => 'sahodaya',
            'status' => 'active',
            'is_active' => true,
            'membership_status' => 'approved',
        ]);

        $year = AcademicYear::forSahodaya($sahodaya->id);

        $school = Tenant::create([
            'id' => 'school-dl-gate',
            'parent_id' => $sahodaya->id,
            'name' => 'School DL Gate Test',
            'type' => 'school',
            'status' => 'active',
            'is_active' => true,
            'membership_status' => 'approved',
        ]);

        // Annual Sahodaya membership completed
        Registration::create([
            'school_id' => $school->id,
            'academic_year' => $year,
            'registration_status' => 'completed',
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Download Gating Test Event',
            'event_type' => 'kalotsav',
            'status' => 'registration_open',
            'approval_policy' => 'auto',
            'fee_settings' => [
                'fee_model' => 'per_item',
                'per_item_amount' => 100,
                'require_verified_students' => false,
            ],
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Folk Dance',
            'participant_type' => 'single',
            'is_enabled' => true,
        ]);

        $schoolClass = SchoolClass::create([
            'tenant_id' => $school->id,
            'name' => 'Class 10',
            'class_number' => 10,
        ]);

        $student = Student::create([
            'tenant_id' => $school->id,
            'school_class_id' => $schoolClass->id,
            'name' => 'Jane Student',
            'status' => 'active',
            'verification_status' => 'verified',
            'eligible_kalolsav' => true,
        ]);

        // 1. Create registration (auto-approved)
        $createService = app(FestRegistrationCreateService::class);
        $registration = $createService->createForSchool($event, $item, $school, [$student->id]);

        $this->assertEquals('approved', $registration->status);

        // 2. Test download gate BEFORE payment - should be blocked
        $downloadGate = app(SchoolDocumentDownloadGateService::class);
        $this->assertFalse($downloadGate->festEventFeeCleared($event, $school));

        $payload = $downloadGate->payload($school, $event);
        $this->assertTrue($payload['blocked']);
        $this->assertStringContainsString('Event fee payment is pending', $payload['reason']);

        try {
            $downloadGate->assertFestEventFeeForDownloads($event, $school);
            $this->fail('Expected HttpException 422 was not thrown');
        } catch (HttpException $e) {
            $this->assertEquals(422, $e->getStatusCode());
            $this->assertStringContainsString('Event fee payment is pending', $e->getMessage());
        }

        // 3. Mark event fee as paid and approved
        $fee = FestSchoolEventFee::where('event_id', $event->id)
            ->where('school_id', $school->id)
            ->first();

        if (! $fee) {
            $fee = app(FestSchoolEventFeeService::class)->recalculate($event, $school->id);
        }

        $fee->update([
            'amount_paid' => $fee->total_due,
            'status' => 'approved',
        ]);

        // 4. Test download gate AFTER payment approval - should be unlocked
        $this->assertTrue($downloadGate->festEventFeeCleared($event, $school));

        $payloadAfter = $downloadGate->payload($school, $event);
        $this->assertFalse($payloadAfter['blocked']);
        $this->assertNull($payloadAfter['reason']);

        // Should not throw any exception
        $downloadGate->assertFestEventFeeForDownloads($event, $school);
    }
}
