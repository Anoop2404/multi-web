<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestParticipationPolicy;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestEventRegistrationService;
use App\Services\Events\FestIdCardService;
use App\Services\Events\FestMandatoryItemService;
use App\Services\Events\FestParticipationLimitService;
use App\Services\Events\FestReportService;
use App\Services\Events\FestRegistrationBulkService;
use App\Services\Events\FestRegistrationCreateService;
use App\Services\Events\FestSchoolEventFeeService;
use Database\Seeders\SahodayaMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class FestSportsCompositeFeeTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, school: Tenant, event: FestEvent, students: list<Student>} */
    private function sportsContext(): array
    {
        $this->seed(SahodayaMasterDataSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Sports Sahodaya',
            'domain' => 'sports-sahodaya.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'SP',
            'student_data_mode' => 'full_records',
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Sports School',
            'parent_id' => $sahodaya->id,
            'school_prefix' => 'SS',
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $class = SchoolClass::create([
            'tenant_id' => $school->id,
            'name' => 'Class X',
            'sort_order' => 1,
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Sports Meet 2026',
            'event_type' => 'sports',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
            'require_event_registration' => true,
            'school_registration_fee' => 2000,
            'student_registration_fee' => 300,
            'included_items_per_student' => 2,
            'default_item_fee' => 150,
            'extra_item_fee' => 100,
        ]);

        $students = collect(range(1, 2))->map(function (int $i) use ($school, $class) {
            return Student::create([
                'tenant_id' => $school->id,
                'school_class_id' => $class->id,
                'name' => "Athlete {$i}",
                'reg_no' => "A{$i}",
                'gender' => 'male',
                'dob' => now()->subYears(12)->toDateString(),
                'status' => 'active',
                'verified_at' => now(),
            ]);
        })->all();

        return compact('sahodaya', 'school', 'event', 'students');
    }

    public function test_sports_composite_fee_breakdown(): void
    {
        ['school' => $school, 'event' => $event, 'students' => $students] = $this->sportsContext();

        $items = collect(range(1, 3))->map(function (int $i) use ($event) {
            return FestEventItem::create([
                'event_id' => $event->id,
                'title' => "Item {$i}",
                'participant_type' => 'individual',
                'class_group' => 'open',
                'age_group' => 'u14',
                'is_enabled' => true,
                'fee_amount' => 100,
                'quota_eligible' => true,
            ]);
        });

        $regService = app(FestEventRegistrationService::class);
        foreach ($students as $student) {
            $regService->registerStudent($event, $student, $school);
        }

        foreach ($students as $student) {
            foreach ($items as $item) {
                $registration = FestRegistration::create([
                    'event_id' => $event->id,
                    'item_id' => $item->id,
                    'school_id' => $school->id,
                    'status' => 'approved',
                    'submitted_at' => now(),
                ]);
                FestParticipant::create([
                    'registration_id' => $registration->id,
                    'student_id' => $student->id,
                    'participant_type' => 'student',
                    'participant_role' => 'performer',
                ]);
            }
        }

        $fee = app(FestSchoolEventFeeService::class)->recalculate($event, $school->id);

        $this->assertEquals(2000.0, (float) $fee->school_registration_fee);
        $this->assertEquals(600.0, (float) $fee->student_registration_fee);
        $this->assertEquals(200.0, (float) $fee->participation_fee);
        $this->assertEquals(0.0, (float) $fee->extra_item_fee);
        $this->assertEquals(2800.0, (float) $fee->total_due);
        $this->assertCount(8, $fee->fresh('lines')->lines);
    }

    public function test_sports_composite_zero_included_quota_charges_every_item(): void
    {
        ['school' => $school, 'event' => $event, 'students' => $students] = $this->sportsContext();

        $event->update([
            'included_items_per_student' => 0,
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id,
            'title' => '100m',
            'participant_type' => 'individual',
            'class_group' => 'open',
            'age_group' => 'u14',
            'is_enabled' => true,
            'fee_amount' => 100,
            'quota_eligible' => true,
        ]);

        $regService = app(FestEventRegistrationService::class);
        foreach ($students as $student) {
            $regService->registerStudent($event, $student, $school);
            $registration = FestRegistration::create([
                'event_id' => $event->id,
                'item_id' => $item->id,
                'school_id' => $school->id,
                'status' => 'approved',
                'submitted_at' => now(),
            ]);
            FestParticipant::create([
                'registration_id' => $registration->id,
                'student_id' => $student->id,
                'participant_type' => 'student',
                'participant_role' => 'performer',
            ]);
        }

        $fee = app(FestSchoolEventFeeService::class)->recalculate($event, $school->id);

        $this->assertEquals(2000.0, (float) $fee->school_registration_fee);
        $this->assertEquals(600.0, (float) $fee->student_registration_fee);
        $this->assertEquals(300.0, (float) $fee->participation_fee);
        $this->assertEquals(0.0, (float) $fee->extra_item_fee);
        $this->assertEquals(2900.0, (float) $fee->total_due);

        $itemLines = $fee->fresh('lines')->lines->where('line_type', 'item_fee');
        $this->assertCount(2, $itemLines);
    }

    public function test_event_registration_required_before_items(): void
    {
        ['school' => $school, 'event' => $event, 'students' => $students] = $this->sportsContext();
        $item = FestEventItem::create([
            'event_id' => $event->id,
            'title' => '100m',
            'participant_type' => 'individual',
            'is_enabled' => true,
        ]);

        $this->expectException(HttpExceptionInterface::class);

        app(FestRegistrationCreateService::class)->createForSchool(
            $event,
            $item,
            $school,
            [$students[0]->id],
        );
    }

    public function test_partition_child_inherits_hub_limits_and_bills_registrations_to_hub(): void
    {
        ['school' => $school, 'event' => $hub, 'students' => $students] = $this->sportsContext();

        $hub->update([
            'event_type' => 'english_fest',
            'conduct_mode' => 'partitioned',
            'partition_role' => null,
            'fee_settings' => [
                'fee_model' => 'sports_composite',
                'school_registration_flat' => 2500,
                'per_student_amount' => 300,
                'included_items_per_student' => 1,
                'extra_item_fee' => 50,
            ],
        ]);

        $region = FestEvent::create([
            'tenant_id' => $hub->tenant_id,
            'title' => 'Region 1',
            'event_type' => 'english_fest',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
            'parent_event_id' => $hub->id,
            'conduct_mode' => 'standard',
            'partition_role' => 'region',
            'partition_key' => 'region-1',
        ]);

        FestParticipationPolicy::create([
            'tenant_id' => $hub->tenant_id,
            'scope' => 'event',
            'event_id' => $hub->id,
            'level_round' => 'sahodaya',
            'max_total_per_student' => 2,
            'one_entry_per_item_per_school' => true,
            'count_submitted_registrations' => true,
            'exclude_standbys_from_limits' => true,
            'require_fee_before_approval' => false,
            'is_active' => true,
        ]);

        $items = collect(range(1, 3))->map(function (int $number) use ($hub, $region) {
            $source = FestEventItem::create([
                'event_id' => $hub->id,
                'title' => "English Item {$number}",
                'item_code' => "ENG-{$number}",
                'participant_type' => 'individual',
                'stage_type' => 'on_stage',
                'class_group' => 'open',
                'is_enabled' => true,
                'fee_amount' => 50,
            ]);

            return FestEventItem::create([
                'event_id' => $region->id,
                'title' => $source->title,
                'item_code' => $source->item_code,
                'participant_type' => 'individual',
                'stage_type' => 'on_stage',
                'class_group' => 'open',
                'is_enabled' => true,
                'fee_amount' => 50,
                'inherited_from_item_id' => $source->id,
            ]);
        });

        foreach ($items->take(2) as $item) {
            $registration = FestRegistration::create([
                'event_id' => $region->id,
                'item_id' => $item->id,
                'school_id' => $school->id,
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);
            FestParticipant::create([
                'registration_id' => $registration->id,
                'student_id' => $students[0]->id,
                'participant_type' => 'student',
                'participant_role' => 'performer',
            ]);
        }

        $errors = (new FestParticipationLimitService($region))
            ->validateRegistration($items->last(), $school->id, [$students[0]->id]);

        $this->assertContains('Athlete 1 exceeds max 2 total items.', $errors);

        $fee = app(FestSchoolEventFeeService::class)->recalculate($region, $school->id);

        $this->assertSame($hub->id, $fee->event_id);
        $this->assertSame(2500.0, (float) $fee->school_registration_fee);
        $this->assertSame(300.0, (float) $fee->student_registration_fee);
        $this->assertSame(50.0, (float) $fee->extra_item_fee);
        $this->assertSame(2850.0, (float) $fee->total_due);

        $firstRegionalItem = $items->first();
        $firstSourceItemId = (int) $firstRegionalItem->inherited_from_item_id;
        $this->assertSame(
            1,
            app(FestIdCardService::class)
                ->itemRegistrationCounts($hub->fresh(), $school->id, true)[$firstSourceItemId] ?? 0,
        );
        $this->assertCount(2, (new FestReportService($hub->fresh()))->activeRegistrations());
        $this->assertCount(
            1,
            (new FestReportService($hub->fresh()))->participantsFlat($firstSourceItemId),
        );

        FestEventItem::whereKey($firstSourceItemId)->update(['is_mandatory' => true]);
        $this->assertTrue(
            app(FestMandatoryItemService::class)
                ->missingForSchool($hub->fresh(), $school->id)
                ->isEmpty(),
        );

        Role::findOrCreate('school_admin', 'web');
        Role::findOrCreate('school_staff', 'web');
        $bulkResult = app(FestRegistrationBulkService::class)->approveMany(
            $hub->fresh(),
            FestRegistration::where('event_id', $region->id)->pluck('id')->all(),
        );
        $this->assertSame(2, $bulkResult['approved']);
        $this->assertSame(
            2,
            FestRegistration::where('event_id', $region->id)->where('status', 'approved')->count(),
        );
    }
}
