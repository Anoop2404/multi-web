<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchedule;
use App\Models\FestStage;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\FestScheduleConflictService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression + edge-case coverage for FestScheduleConflictService.
 *
 * Regression: detectAll() previously never set a `student_id` key on its returned clash
 * entries (only `student_name`), which silently broke the `isset($c['student_id'])` filter
 * in GroupAdminController::festClashes() — every clash was discarded on that portal page
 * regardless of real data. The fix adds `student_id` as the first key of the clash array.
 * Test 5 below (and the assertions threaded through every other test) directly cover that.
 */
class FestScheduleConflictServiceTest extends TestCase
{
    use RefreshDatabase;

    private function baseDay(string $time): Carbon
    {
        return Carbon::parse('2026-02-10 '.$time);
    }

    /** @return array{sahodaya: Tenant, school: Tenant, event: FestEvent} */
    private function makeContext(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Clash Test Sahodaya',
            'domain' => 'clash-test-'.Str::random(8).'.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'CT',
            'student_data_mode' => 'counts_only',
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Clash Test School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Clash Test Fest',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'ongoing',
            'schedule_published' => true,
        ]);

        return compact('sahodaya', 'school', 'event');
    }

    private function makeItem(FestEvent $event, string $title, ?int $durationMinutes = 60): FestEventItem
    {
        return FestEventItem::create([
            'event_id' => $event->id,
            'title' => $title,
            'owner_level' => 'sahodaya',
            'is_enabled' => true,
            'duration_minutes' => $durationMinutes,
        ]);
    }

    private function makeStudent(Tenant $school, string $name): Student
    {
        $class = SchoolClass::firstOrCreate(
            ['tenant_id' => $school->id, 'name' => '10'],
        );

        return Student::create([
            'tenant_id' => $school->id,
            'school_class_id' => $class->id,
            'name' => $name,
            'gender' => 'male',
        ]);
    }

    /** Registers a student for an item and returns the FestParticipant row (for participant-level schedules). */
    private function registerParticipant(
        FestEvent $event,
        FestEventItem $item,
        Tenant $school,
        Student $student,
        string $status = 'approved'
    ): FestParticipant {
        $registration = FestRegistration::create([
            'event_id' => $event->id,
            'item_id' => $item->id,
            'school_id' => $school->id,
            'status' => $status,
            'submitted_at' => now(),
        ]);

        return FestParticipant::create([
            'registration_id' => $registration->id,
            'student_id' => $student->id,
            'participant_type' => 'student',
        ]);
    }

    private function schedule(
        FestEvent $event,
        FestEventItem $item,
        ?FestParticipant $participant,
        Carbon $at,
        ?int $stageId = null,
        ?string $stage = null
    ): FestSchedule {
        return FestSchedule::create([
            'event_id' => $event->id,
            'item_id' => $item->id,
            'participant_id' => $participant?->id,
            'scheduled_at' => $at,
            'stage_id' => $stageId,
            'stage' => $stage,
        ]);
    }

    // ------------------------------------------------------------------
    // detectAll(): student clashes
    // ------------------------------------------------------------------

    public function test_overlapping_schedules_for_same_student_produce_one_clash(): void
    {
        ['school' => $school, 'event' => $event] = $this->makeContext();

        $itemA = $this->makeItem($event, 'Item A', 60);
        $itemB = $this->makeItem($event, 'Item B', 60);
        $student = $this->makeStudent($school, 'Overlap Student');

        $participantA = $this->registerParticipant($event, $itemA, $school, $student);
        $participantB = $this->registerParticipant($event, $itemB, $school, $student);

        // Item A: 10:00 - 11:00. Item B: 10:30 - 11:30. Overlap 10:30-11:00.
        $this->schedule($event, $itemA, $participantA, $this->baseDay('10:00'));
        $this->schedule($event, $itemB, $participantB, $this->baseDay('10:30'));

        $clashes = (new FestScheduleConflictService($event))->detectAll();

        $this->assertCount(1, $clashes);
        $clash = $clashes[0];
        $this->assertSame($student->id, $clash['student_id']);
        $this->assertEqualsCanonicalizing([$itemA->id, $itemB->id], [$clash['item1_id'], $clash['item2_id']]);
    }

    public function test_back_to_back_schedules_are_not_a_clash(): void
    {
        ['school' => $school, 'event' => $event] = $this->makeContext();

        $itemA = $this->makeItem($event, 'Item A', 60);
        $itemB = $this->makeItem($event, 'Item B', 60);
        $student = $this->makeStudent($school, 'Back To Back Student');

        $participantA = $this->registerParticipant($event, $itemA, $school, $student);
        $participantB = $this->registerParticipant($event, $itemB, $school, $student);

        // Item A: 10:00 - 11:00. Item B starts exactly when Item A ends: 11:00 - 12:00.
        $this->schedule($event, $itemA, $participantA, $this->baseDay('10:00'));
        $this->schedule($event, $itemB, $participantB, $this->baseDay('11:00'));

        $clashes = (new FestScheduleConflictService($event))->detectAll();

        $this->assertSame([], $clashes, 'touching boundaries (start2 == end1) must not be flagged as a clash');
    }

    public function test_null_duration_defaults_to_sixty_minutes_and_is_detected(): void
    {
        ['school' => $school, 'event' => $event] = $this->makeContext();

        // Item A has no duration_minutes set at all -> must default to 60.
        $itemA = $this->makeItem($event, 'Item A', null);
        $itemB = $this->makeItem($event, 'Item B', 30);
        $student = $this->makeStudent($school, 'Default Duration Student');

        $participantA = $this->registerParticipant($event, $itemA, $school, $student);
        $participantB = $this->registerParticipant($event, $itemB, $school, $student);

        // Item A: 10:00 -> (60 min default) 11:00. Item B: 10:45 -> 11:15.
        // These only overlap because Item A's window is 60 minutes wide; if the
        // default were e.g. 40 minutes (end 10:40), there would be no overlap.
        $this->schedule($event, $itemA, $participantA, $this->baseDay('10:00'));
        $this->schedule($event, $itemB, $participantB, $this->baseDay('10:45'));

        $clashes = (new FestScheduleConflictService($event))->detectAll();

        $this->assertCount(1, $clashes);
        $this->assertSame($student->id, $clashes[0]['student_id']);
    }

    public function test_item_level_schedule_clashes_with_participant_level_schedule_for_an_approved_participant(): void
    {
        ['school' => $school, 'event' => $event] = $this->makeContext();

        $itemX = $this->makeItem($event, 'Item X (whole-item slot)', 60);
        $itemY = $this->makeItem($event, 'Item Y', 60);
        $student = $this->makeStudent($school, 'Item Level Student');

        // Student is an approved participant of item X, but the schedule for item X
        // is an item-level slot (no participant_id) — representing "all of item X's
        // approved participants perform in this window".
        $this->registerParticipant($event, $itemX, $school, $student, status: 'approved');
        $itemLevelSchedule = $this->schedule($event, $itemX, null, $this->baseDay('10:00'));

        // Student also has a specific participant-level schedule for item Y, overlapping.
        $participantY = $this->registerParticipant($event, $itemY, $school, $student);
        $this->schedule($event, $itemY, $participantY, $this->baseDay('10:30'));

        $clashes = (new FestScheduleConflictService($event))->detectAll();

        $this->assertCount(1, $clashes);
        $this->assertSame($student->id, $clashes[0]['student_id']);
        $this->assertEqualsCanonicalizing([$itemX->id, $itemY->id], [$clashes[0]['item1_id'], $clashes[0]['item2_id']]);
        $this->assertNotNull($itemLevelSchedule->id);
    }

    public function test_item_level_schedule_ignores_a_participant_whose_registration_is_not_approved(): void
    {
        ['school' => $school, 'event' => $event] = $this->makeContext();

        $itemX = $this->makeItem($event, 'Item X (whole-item slot)', 60);
        $itemZ = $this->makeItem($event, 'Item Z', 60);
        $pendingStudent = $this->makeStudent($school, 'Pending Student');

        // Registered for item X but only "pending_approval" — must NOT be counted
        // in the item-level student set for item X's schedule.
        $this->registerParticipant($event, $itemX, $school, $pendingStudent, status: 'pending_approval');
        $this->schedule($event, $itemX, null, $this->baseDay('10:00'));

        // Give the same student an overlapping participant-level schedule elsewhere.
        // If (and only if) the pending registration were wrongly counted, this would
        // surface as a clash.
        $participantZ = $this->registerParticipant($event, $itemZ, $school, $pendingStudent);
        $this->schedule($event, $itemZ, $participantZ, $this->baseDay('10:30'));

        $clashes = (new FestScheduleConflictService($event))->detectAll();

        $this->assertSame(
            [],
            array_filter($clashes, fn ($c) => $c['student_id'] === $pendingStudent->id),
            'a pending (non-approved) registration must not be pulled into the item-level student set'
        );
    }

    public function test_every_clash_entry_carries_the_correct_student_id_key(): void
    {
        ['school' => $school, 'event' => $event] = $this->makeContext();

        $itemA = $this->makeItem($event, 'Item A', 60);
        $itemB = $this->makeItem($event, 'Item B', 60);
        $studentOne = $this->makeStudent($school, 'Student One');
        $studentTwo = $this->makeStudent($school, 'Student Two');

        foreach ([$studentOne, $studentTwo] as $student) {
            $participantA = $this->registerParticipant($event, $itemA, $school, $student);
            $participantB = $this->registerParticipant($event, $itemB, $school, $student);
            $this->schedule($event, $itemA, $participantA, $this->baseDay('09:00'));
            $this->schedule($event, $itemB, $participantB, $this->baseDay('09:30'));
        }

        $clashes = (new FestScheduleConflictService($event))->detectAll();

        $this->assertCount(2, $clashes);
        $seenStudentIds = [];
        foreach ($clashes as $clash) {
            $this->assertArrayHasKey('student_id', $clash);
            $this->assertNotNull($clash['student_id']);
            $seenStudentIds[] = $clash['student_id'];
        }
        $this->assertEqualsCanonicalizing([$studentOne->id, $studentTwo->id], $seenStudentIds);
    }

    // ------------------------------------------------------------------
    // detectStageConflicts()
    // ------------------------------------------------------------------

    public function test_same_stage_id_overlapping_times_is_one_stage_conflict(): void
    {
        ['event' => $event] = $this->makeContext();

        $stage = FestStage::create(['event_id' => $event->id, 'name' => 'Main Stage']);
        $itemA = $this->makeItem($event, 'Item A', 60);
        $itemB = $this->makeItem($event, 'Item B', 60);

        $this->schedule($event, $itemA, null, $this->baseDay('12:00'), stageId: $stage->id);
        $this->schedule($event, $itemB, null, $this->baseDay('12:30'), stageId: $stage->id);

        $conflicts = (new FestScheduleConflictService($event))->detectStageConflicts();

        $this->assertCount(1, $conflicts);
        $this->assertEqualsCanonicalizing([$itemA->id, $itemB->id], [$conflicts[0]['item1_id'], $conflicts[0]['item2_id']]);
    }

    public function test_free_text_stage_name_is_normalized_case_and_whitespace_insensitive(): void
    {
        ['event' => $event] = $this->makeContext();

        $itemA = $this->makeItem($event, 'Item A', 60);
        $itemB = $this->makeItem($event, 'Item B', 60);

        $this->schedule($event, $itemA, null, $this->baseDay('13:00'), stage: 'Main Hall');
        $this->schedule($event, $itemB, null, $this->baseDay('13:30'), stage: ' main hall ');

        $conflicts = (new FestScheduleConflictService($event))->detectStageConflicts();

        $this->assertCount(1, $conflicts, 'differently-cased/whitespaced free-text stage names must be treated as the same stage');
    }

    public function test_genuinely_different_stages_overlapping_are_not_a_stage_conflict(): void
    {
        ['event' => $event] = $this->makeContext();

        $itemA = $this->makeItem($event, 'Item A', 60);
        $itemB = $this->makeItem($event, 'Item B', 60);

        $this->schedule($event, $itemA, null, $this->baseDay('14:00'), stage: 'Stage One');
        $this->schedule($event, $itemB, null, $this->baseDay('14:30'), stage: 'Stage Two');

        $conflicts = (new FestScheduleConflictService($event))->detectStageConflicts();

        $this->assertSame([], $conflicts);
    }

    // ------------------------------------------------------------------
    // allConflicts()
    // ------------------------------------------------------------------

    public function test_all_conflicts_is_the_concatenation_of_both_detectors(): void
    {
        ['school' => $school, 'event' => $event] = $this->makeContext();

        // A student clash (participant-level, no stage involved).
        $itemA = $this->makeItem($event, 'Item A', 60);
        $itemB = $this->makeItem($event, 'Item B', 60);
        $student = $this->makeStudent($school, 'Combined Student');
        $participantA = $this->registerParticipant($event, $itemA, $school, $student);
        $participantB = $this->registerParticipant($event, $itemB, $school, $student);
        $this->schedule($event, $itemA, $participantA, $this->baseDay('09:00'));
        $this->schedule($event, $itemB, $participantB, $this->baseDay('09:30'));

        // A separate, independent stage conflict (different items, no student overlap,
        // both left as item-level slots with no participant).
        $itemC = $this->makeItem($event, 'Item C', 60);
        $itemD = $this->makeItem($event, 'Item D', 60);
        $stage = FestStage::create(['event_id' => $event->id, 'name' => 'Second Stage']);
        $this->schedule($event, $itemC, null, $this->baseDay('15:00'), stageId: $stage->id);
        $this->schedule($event, $itemD, null, $this->baseDay('15:30'), stageId: $stage->id);

        $service = new FestScheduleConflictService($event);
        $studentClashes = $service->detectAll();
        $stageConflicts = $service->detectStageConflicts();
        $all = $service->allConflicts();

        $this->assertCount(1, $studentClashes);
        $this->assertCount(1, $stageConflicts);
        $this->assertSame(array_merge($studentClashes, $stageConflicts), $all);
    }

    // ------------------------------------------------------------------
    // End-to-end: GroupAdminController::festClashes (the bug's real consumer)
    // ------------------------------------------------------------------

    /**
     * NOTE ON SCOPE: the spec for this test asked for a group_admin/teacher whose
     * `assignedClassIds()` (via `$user->group_classes`) restricts them to one class.
     * That column only exists on the tenant `users` table added by
     * database/migrations/tenant/2026_07_06_170001_tenant_users_and_permissions.php,
     * which no-ops entirely when `config('tenancy.database_per_sahodaya')` is false —
     * exactly the mode this test suite runs in (phpunit.xml sets
     * TENANCY_DATABASE_PER_SAHODAYA=false for a fast shared sqlite schema). So in this
     * environment `group_classes` is not a column at all, and a group_admin's
     * assignedClassIds() can never be populated to exercise the class-filter branch.
     * This is a test-environment limitation, not a production bug: real deployments
     * default `database_per_sahodaya` to true (config/tenancy.php), where the column
     * exists.
     *
     * To still prove the real end-to-end fix (GroupAdminController::festClashes'
     * `isset($c['student_id'])` filter, previously always false, discarding every
     * clash), this uses a `school_admin` actor instead — EnsureGroupAdmin explicitly
     * allows it, and `assignedClassIds()` returns null for it (see all active students
     * in the school), which the controller then intersects with a real Student
     * `active()` scope. A `transferred` (inactive) student stands in for "outside the
     * viewer's scope" in place of the untestable class assignment.
     */
    public function test_group_admin_fest_clashes_page_shows_clash_for_a_student_in_scope_and_hides_one_outside_it(): void
    {
        ['school' => $school, 'event' => $event] = $this->makeContext();

        $itemA = $this->makeItem($event, 'Item A', 60);
        $itemB = $this->makeItem($event, 'Item B', 60);

        $class = SchoolClass::firstOrCreate(['tenant_id' => $school->id, 'name' => '10']);

        $inScopeStudent = Student::create([
            'tenant_id' => $school->id,
            'school_class_id' => $class->id,
            'name' => 'In Scope Student',
            'gender' => 'male',
            'status' => 'active',
        ]);
        $outOfScopeStudent = Student::create([
            'tenant_id' => $school->id,
            'school_class_id' => $class->id,
            'name' => 'Out Of Scope Student',
            'gender' => 'male',
            'status' => 'transferred',
        ]);

        // Give BOTH students a genuine, overlapping clash.
        foreach ([$inScopeStudent, $outOfScopeStudent] as $student) {
            $pA = $this->registerParticipant($event, $itemA, $school, $student);
            $pB = $this->registerParticipant($event, $itemB, $school, $student);
            $this->schedule($event, $itemA, $pA, $this->baseDay('10:00'));
            $this->schedule($event, $itemB, $pB, $this->baseDay('10:30'));
        }

        $admin = User::factory()->create([
            'tenant_id' => $school->id,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('school_admin');

        $response = $this->actingAs($admin)->get(route('portal.group.fest.clashes', ['tenantId' => $school->id]));

        // Before the fix, detectAll() never set a `student_id` key, so the controller's
        // `isset($c['student_id'])` filter discarded every clash and this list was always
        // empty — regardless of which student the clash belonged to. Asserting exactly
        // one clash, for the in-scope student specifically, is the true regression check.
        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('Portal/Group/FestClashes', false)
            ->has('clashes', 1)
            ->where('clashes.0.student_id', $inScopeStudent->id)
        );
    }
}
