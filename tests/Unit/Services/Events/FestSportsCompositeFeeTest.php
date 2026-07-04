<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestLevelRegistration;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestEventRegistrationService;
use App\Services\Events\FestSchoolEventFeeService;
use Database\Seeders\SahodayaMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
            'fee_settings' => [
                'fee_model' => 'sports_composite',
                'school_registration_flat' => 2000,
                'per_student_amount' => 300,
                'included_items_per_student' => 2,
                'default_item_fee' => 150,
            ],
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
        $this->assertEquals(200.0, (float) $fee->extra_item_fee);
        $this->assertEquals(2800.0, (float) $fee->total_due);
        $this->assertCount(4, $fee->fresh('lines')->lines);
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

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface::class);

        app(\App\Services\Events\FestRegistrationCreateService::class)->createForSchool(
            $event,
            $item,
            $school,
            [$students[0]->id],
        );
    }
}
