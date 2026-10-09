<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ClassCategory;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Audit\FestEventActivityService;
use App\Services\Events\FestRegistrationCreateService;
use App\Services\Events\FestRegistrationService;
use App\Support\FestPageActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestEventActivityAuditTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $sahodaya;

    private Tenant $school;

    private FestEvent $event;

    private FestEventItem $item;

    private Student $student1;

    private Student $student2;

    private Student $studentStandby;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Kottayam Sahodaya',
            'is_active' => true,
        ]);

        $this->school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'St. Thomas School',
            'parent_id' => $this->sahodaya->id,
            'is_active' => true,
            'membership_status' => 'approved',
        ]);

        $this->event = FestEvent::create([
            'tenant_id' => $this->sahodaya->id,
            'title' => 'Kalotsav 2026',
            'event_type' => 'kalotsav',
            'status' => 'published',
            'competition_mode' => 'standard',
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(7),
        ]);

        $this->item = FestEventItem::create([
            'tenant_id' => $this->sahodaya->id,
            'event_id' => $this->event->id,
            'title' => 'Mohiniyattam',
            'class_group' => 'category_3',
            'participant_type' => 'individual',
            'min_participants' => 1,
            'max_participants' => 1,
            'max_standby' => 1,
            'is_enabled' => true,
        ]);

        $category = ClassCategory::create([
            'code' => 'category_3',
            'label' => 'Category 3',
            'min_class' => 8,
            'max_class' => 10,
            'sort_order' => 1,
        ]);

        $class = SchoolClass::create([
            'tenant_id' => $this->school->id,
            'class_category_id' => $category->id,
            'name' => '10',
            'display_order' => 10,
        ]);

        $this->student1 = Student::create([
            'tenant_id' => $this->school->id,
            'school_class_id' => $class->id,
            'name' => 'Anoop Student 1',
            'reg_no' => 'REG001',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);

        $this->student2 = Student::create([
            'tenant_id' => $this->school->id,
            'school_class_id' => $class->id,
            'name' => 'Benny Student 2',
            'reg_no' => 'REG002',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);

        $this->studentStandby = Student::create([
            'tenant_id' => $this->school->id,
            'school_class_id' => $class->id,
            'name' => 'Standby Student',
            'reg_no' => 'REG003',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);
    }

    public function test_registration_creation_logs_audit_and_activity_service_enriches_it(): void
    {
        $createService = app(FestRegistrationCreateService::class);
        $registration = $createService->createForSchool(
            $this->event,
            $this->item,
            $this->school,
            [$this->student1->id],
            [$this->studentStandby->id],
            adminOverride: true
        );

        $activityService = app(FestEventActivityService::class);
        $result = $activityService->forEvent($this->event, limit: 10);

        $this->assertNotEmpty($result['logs']);
        $log = $result['logs']->first();

        $this->assertStringContainsString('Mohiniyattam', $log['description']);
        $this->assertStringContainsString(strtoupper('St. Thomas School'), $log['description']);
        $this->assertEquals($this->school->id, $log['school_id']);
        $this->assertEquals(strtoupper('St. Thomas School'), $log['school']);
        $this->assertEquals('Mohiniyattam', $log['item_title']);
        $this->assertEquals(FestPageActivity::REGISTRATIONS, $log['page']);
    }

    public function test_cancellation_logs_reason_and_audit(): void
    {
        $createService = app(FestRegistrationCreateService::class);
        $registration = $createService->createForSchool(
            $this->event,
            $this->item,
            $this->school,
            [$this->student1->id],
            [],
            adminOverride: true
        );

        $regService = app(FestRegistrationService::class);
        $regService->cancel($registration, $this->event, reason: 'Medical emergency');

        $activityService = app(FestEventActivityService::class);
        $result = $activityService->forEvent($this->event, limit: 10);

        $cancelLog = $result['logs']->firstWhere('action', 'fest.registration.cancelled');
        $this->assertNotNull($cancelLog);
        $this->assertStringContainsString('Medical emergency', $cancelLog['description']);
        $this->assertEquals('Medical emergency', $cancelLog['reason']);
        $this->assertEquals($this->school->id, $cancelLog['school_id']);
    }

    public function test_substitutions_and_standby_promotions_are_logged(): void
    {
        $createService = app(FestRegistrationCreateService::class);
        $registration = $createService->createForSchool(
            $this->event,
            $this->item,
            $this->school,
            [$this->student1->id],
            [$this->studentStandby->id],
            adminOverride: true
        );

        $performer = $registration->participants()->where('participant_role', 'performer')->firstOrFail();
        $standby = $registration->participants()->where('participant_role', 'standby')->firstOrFail();

        $regService = app(FestRegistrationService::class);
        $regService->substitutePerformer($performer, $standby);

        $activityService = app(FestEventActivityService::class);
        $result = $activityService->forEvent($this->event, limit: 10);

        $subLog = $result['logs']->firstWhere('action', 'fest.registration.participant_substituted');
        $this->assertNotNull($subLog);
        $this->assertStringContainsString('Anoop Student 1', $subLog['description']);
        $this->assertStringContainsString('Standby Student', $subLog['description']);
    }

    public function test_legacy_logs_without_properties_are_enriched_by_activity_service(): void
    {
        $createService = app(FestRegistrationCreateService::class);
        $registration = $createService->createForSchool(
            $this->event,
            $this->item,
            $this->school,
            [$this->student1->id],
            [],
            adminOverride: true
        );

        // Insert a bare legacy log
        AuditLog::create([
            'tenant_id' => $this->sahodaya->id,
            'action' => 'fest.registration.submitted',
            'description' => "Fest registration #{$registration->id} submitted",
            'subject_type' => (new FestRegistration)->getMorphClass(),
            'subject_id' => (string) $registration->id,
            'properties' => [
                'event_id' => $this->event->id,
            ],
            'created_at' => now(),
        ]);

        $activityService = app(FestEventActivityService::class);
        $result = $activityService->forEvent($this->event, limit: 10);

        $legacyLog = $result['logs']->first(fn ($l) => str_starts_with($l['description'], "Fest registration #{$registration->id} submitted"));
        $this->assertNotNull($legacyLog);
        $this->assertEquals(strtoupper('St. Thomas School'), $legacyLog['school']);
        $this->assertEquals('Mohiniyattam', $legacyLog['item_title']);
        $this->assertStringContainsString('Mohiniyattam', $legacyLog['description']);
        $this->assertStringContainsString(strtoupper('St. Thomas School'), $legacyLog['description']);
    }

    public function test_teacher_registration_writes_approval_audit(): void
    {
        $this->event->update(['event_type' => 'teacher_fest']);
        $teacher = \App\Models\Teacher::create(['tenant_id' => $this->school->id, 'name' => 'Teacher Performer']);
        $registration = app(FestRegistrationCreateService::class)->createForSchool(
            $this->event, $this->item, $this->school, [$teacher->id], [], adminOverride: true
        );
        $this->assertTrue(AuditLog::where('subject_type', (new FestRegistration)->getMorphClass())
            ->where('subject_id', (string) $registration->id)
            ->where('action', 'fest.registration.approved')->exists());
    }

    public function test_activity_does_not_match_another_tenants_identical_event_id(): void
    {
        $other = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Other Sahodaya', 'is_active' => true]);
        AuditLog::create([
            'tenant_id' => $other->id,
            'action' => 'fest.registration.submitted',
            'description' => 'Other tenant private activity',
            'subject_type' => (new FestEvent)->getMorphClass(),
            'subject_id' => (string) $this->event->id,
            'properties' => ['event_id' => $this->event->id, 'page' => FestPageActivity::REGISTRATIONS],
        ]);
        $result = app(FestEventActivityService::class)->forEvent($this->event, limit: 10);
        $this->assertCount(0, $result['logs']);
    }

    public function test_backfill_command_creates_missing_logs(): void
    {
        // Create registration directly without service to simulate missing audit log
        $registration = FestRegistration::create([
            'event_id' => $this->event->id,
            'item_id' => $this->item->id,
            'school_id' => $this->school->id,
            'status' => 'submitted',
            'submitted_at' => now()->subDay(),
        ]);

        FestParticipant::create([
            'registration_id' => $registration->id,
            'event_id' => $this->event->id,
            'student_id' => $this->student1->id,
            'participant_role' => 'performer',
        ]);

        // Clean any audit logs
        AuditLog::query()->delete();

        $this->artisan('fest:backfill-registration-logs', ['event' => $this->event->id])
            ->assertExitCode(0);

        $this->assertTrue(AuditLog::where('subject_type', (new FestRegistration)->getMorphClass())
            ->where('subject_id', (string) $registration->id)
            ->exists());

        $activityService = app(FestEventActivityService::class);
        $result = $activityService->forEvent($this->event, limit: 10);
        $this->assertCount(1, $result['logs']);
        $this->assertEquals('Mohiniyattam', $result['logs']->first()['item_title']);
    }
}
