<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchoolFeeSlabSelection;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestRegistrationCreateService;
use App\Services\Events\FestSchoolEventFeeService;
use App\Services\Events\FestSchoolFeeSlabSelectionService;
use Database\Seeders\SahodayaMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class FestSchoolFeeSlabSelectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private const SLABS = [
        ['min_count' => 0, 'max_count' => 499, 'amount' => 2000],
        ['min_count' => 500, 'max_count' => 999, 'amount' => 3000],
        ['min_count' => 1000, 'max_count' => 1499, 'amount' => 4000],
        ['min_count' => 1500, 'max_count' => null, 'amount' => 5000],
    ];

    /** @return array{sahodaya: Tenant, school: Tenant, event: FestEvent} */
    private function kollamContext(array $feeSettingsOverrides = []): array
    {
        $this->seed(SahodayaMasterDataSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Kollam Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'KLM',
            'student_data_mode' => 'full_records',
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Kollam School',
            'parent_id' => $sahodaya->id,
            'school_prefix' => 'KS',
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Kollam Kalotsavam 2026',
            'event_type' => 'kalotsavam',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
            'fee_settings' => array_merge([
                'fee_model' => 'kalolsavam_composite',
                'school_fee_mode' => 'student_count_slab',
                'student_count_slabs' => self::SLABS,
                'per_student_amount' => 500,
                'included_items_per_student' => 3,
                'extra_item_fee' => 100,
                'group_item_flat_fee' => 2500,
                'group_item_per_participant_rate' => 0,
            ], $feeSettingsOverrides),
        ]);

        return compact('sahodaya', 'school', 'event');
    }

    private function makeStudent(Tenant $school, string $name = 'Student'): Student
    {
        $class = SchoolClass::where('tenant_id', $school->id)->first()
            ?? SchoolClass::create(['tenant_id' => $school->id, 'name' => '10', 'is_active' => true]);

        return Student::create([
            'tenant_id' => $school->id,
            'school_class_id' => $class->id,
            'name' => $name,
            'gender' => 'male',
            'dob' => '2012-01-01',
            'status' => 'active',
            'verified_at' => now(),
        ]);
    }

    public function test_first_selection_locks_immediately_and_recalculates_fee(): void
    {
        ['school' => $school, 'event' => $event] = $this->kollamContext();

        $selection = app(FestSchoolFeeSlabSelectionService::class)->select(
            $event, $school->id, 500, 999,
        );

        $this->assertNotNull($selection->locked_at);
        $this->assertSame(3000.0, (float) $selection->amount);

        $fee = app(FestSchoolEventFeeService::class)->resolveSchedule($event);
        $this->assertSame('student_count_slab', $fee['school_fee_mode']);

        // The school fee band is selected, but the school hasn't registered anything yet —
        // consistent with every other billing model here, the school registration fee only
        // shows once the school has actually registered (see recalculate()'s
        // $hasEventRegistration/$studentCount/$extraLines guard).
        $schoolFee = \App\Models\FestSchoolEventFee::where('event_id', $event->id)
            ->where('school_id', $school->id)->first();
        $this->assertNotNull($schoolFee);
        $this->assertSame(0.0, (float) $schoolFee->school_registration_fee);

        $item = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Solo Song',
            'participant_type' => 'individual',
            'is_enabled' => true,
        ]);
        app(FestRegistrationCreateService::class)->createForSchool($event, $item, $school, [$this->makeStudent($school)->id]);

        $schoolFee = app(FestSchoolEventFeeService::class)->recalculate($event, $school->id);
        $this->assertSame(3000.0, (float) $schoolFee->school_registration_fee);
    }

    public function test_second_selection_without_override_is_rejected_once_locked(): void
    {
        ['school' => $school, 'event' => $event] = $this->kollamContext();
        $service = app(FestSchoolFeeSlabSelectionService::class);
        $service->select($event, $school->id, 500, 999);

        $this->expectException(ValidationException::class);
        $service->select($event, $school->id, 1000, 1499);
    }

    public function test_override_with_reason_succeeds_and_updates_snapshot(): void
    {
        ['school' => $school, 'event' => $event] = $this->kollamContext();
        $service = app(FestSchoolFeeSlabSelectionService::class);
        $service->select($event, $school->id, 500, 999);

        $updated = $service->select(
            $event, $school->id, 1000, 1499,
            actorId: null, override: true, reason: 'Corrected reported strength',
        );

        $this->assertSame(1000, $updated->min_count);
        $this->assertSame(4000.0, (float) $updated->amount);
        $this->assertNotNull($updated->changed_at);
        $this->assertSame('Corrected reported strength', $updated->change_reason);

        $this->assertSame(1, FestSchoolFeeSlabSelection::where('event_id', $event->id)
            ->where('school_id', $school->id)->count());
    }

    public function test_selecting_a_band_not_in_the_configured_table_is_rejected(): void
    {
        ['school' => $school, 'event' => $event] = $this->kollamContext();

        $this->expectException(HttpExceptionInterface::class);
        app(FestSchoolFeeSlabSelectionService::class)->select($event, $school->id, 5000, 6000);
    }

    public function test_registration_blocked_until_school_selects_fee_band(): void
    {
        ['school' => $school, 'event' => $event] = $this->kollamContext();
        $item = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Solo Song',
            'participant_type' => 'individual',
            'is_enabled' => true,
        ]);
        $student = $this->makeStudent($school);

        $this->expectException(ValidationException::class);
        app(FestRegistrationCreateService::class)->createForSchool($event, $item, $school, [$student->id]);
    }

    public function test_registration_no_orphan_left_behind_when_selection_missing(): void
    {
        ['school' => $school, 'event' => $event] = $this->kollamContext();
        $item = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Solo Song',
            'participant_type' => 'individual',
            'is_enabled' => true,
        ]);
        $student = $this->makeStudent($school);

        try {
            app(FestRegistrationCreateService::class)->createForSchool($event, $item, $school, [$student->id]);
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame(0, FestRegistration::where('event_id', $event->id)->where('school_id', $school->id)->count());
    }

    public function test_registration_succeeds_once_school_has_selected_a_band(): void
    {
        ['school' => $school, 'event' => $event] = $this->kollamContext();
        app(FestSchoolFeeSlabSelectionService::class)->select($event, $school->id, 500, 999);

        $item = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Solo Song',
            'participant_type' => 'individual',
            'is_enabled' => true,
        ]);
        $student = $this->makeStudent($school);

        $registration = app(FestRegistrationCreateService::class)->createForSchool($event, $item, $school, [$student->id]);
        $this->assertNotNull($registration->id);
    }

    public function test_events_without_slab_mode_are_unaffected(): void
    {
        ['school' => $school, 'event' => $event] = $this->kollamContext([
            'school_fee_mode' => 'class_tier',
        ]);
        $item = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Solo Song',
            'participant_type' => 'individual',
            'is_enabled' => true,
        ]);
        $student = $this->makeStudent($school);

        $registration = app(FestRegistrationCreateService::class)->createForSchool($event, $item, $school, [$student->id]);
        $this->assertNotNull($registration->id);
        $this->assertSame(0, FestSchoolFeeSlabSelection::where('event_id', $event->id)->count());
    }

    public function test_full_kollam_policy_calculation_slab_school_fee_item_quota_and_group_flat_fee(): void
    {
        ['school' => $school, 'event' => $event] = $this->kollamContext();
        app(FestSchoolFeeSlabSelectionService::class)->select($event, $school->id, 500, 999);

        $items = collect(range(1, 4))->map(fn (int $i) => FestEventItem::create([
            'event_id' => $event->id,
            'title' => "Solo Item {$i}",
            'participant_type' => 'individual',
            'is_enabled' => true,
        ]));
        $bandDisplay = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Band Display',
            'participant_type' => 'group',
            'min_group_size' => 1,
            'max_group_size' => 40,
            'is_enabled' => true,
        ]);

        $student = $this->makeStudent($school);
        $createService = app(FestRegistrationCreateService::class);
        foreach ($items as $item) {
            $createService->createForSchool($event, $item, $school, [$student->id]);
        }
        $createService->createForSchool($event, $bandDisplay, $school, [$student->id]);

        $fee = app(FestSchoolEventFeeService::class)->recalculate($event, $school->id);

        // School fee driven entirely by the self-selected 500-999 band, not a computed count.
        $this->assertSame(3000.0, (float) $fee->school_registration_fee);
        // First 3 individual items covered by the ₹500 participation fee; the 4th item and
        // the group item both fall beyond the quota (positions 4 and 5).
        $this->assertSame(500.0, (float) $fee->student_registration_fee);
        $this->assertSame(2600.0, (float) $fee->extra_item_fee); // 100 (extra solo item) + 2500 (Band Display flat)
        $this->assertSame(6100.0, (float) $fee->total_due);
    }
}
