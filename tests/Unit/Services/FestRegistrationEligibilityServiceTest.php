<?php

namespace Tests\Unit\Services;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\Student;
use App\Services\Events\FestRegistrationEligibilityService;
use Tests\TestCase;

class FestRegistrationEligibilityServiceTest extends TestCase
{
    private FestRegistrationEligibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(FestRegistrationEligibilityService::class);
    }

    public function test_sports_registration_requires_dob_and_matching_age(): void
    {
        $event = new FestEvent([
            'event_type' => 'sports',
            'event_start' => '2026-06-01',
        ]);

        $item = new FestEventItem([
            'age_group' => 'u14',
            'gender' => 'male',
        ]);

        $student = new Student([
            'name' => 'Arjun',
            'gender' => 'male',
            'dob' => '2013-06-01',
        ]);

        $this->assertSame([], $this->service->validateStudent($student, $event, $item));
    }

    public function test_sports_registration_rejects_student_too_old_for_item(): void
    {
        $event = new FestEvent([
            'event_type' => 'sports',
            'event_start' => '2026-06-01',
        ]);

        $item = new FestEventItem(['age_group' => 'u14', 'gender' => 'male']);
        $student = new Student(['name' => 'Arjun', 'gender' => 'male', 'dob' => '2012-06-01']);

        $errors = $this->service->validateStudent($student, $event, $item);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('must be under', $errors[0]);
        $this->assertStringContainsString('Under 14', $errors[0]);
    }

    public function test_sports_registration_accepts_younger_student_for_u14_item(): void
    {
        $event = new FestEvent([
            'event_type' => 'sports',
            'event_start' => '2026-06-01',
        ]);

        $item = new FestEventItem(['age_group' => 'u14', 'gender' => 'male']);
        $student = new Student(['name' => 'Kid', 'gender' => 'male', 'dob' => '2018-06-01']);

        $this->assertSame([], $this->service->validateStudent($student, $event, $item));
    }

    public function test_sports_registration_accepts_open_age_item(): void
    {
        $event = new FestEvent(['event_type' => 'sports', 'event_start' => '2026-06-01']);
        $item = new FestEventItem(['age_group' => 'open', 'gender' => 'male']);
        $student = new Student(['name' => 'Arjun', 'gender' => 'male', 'dob' => '2013-06-01']);

        $this->assertSame([], $this->service->validateStudent($student, $event, $item));
    }

    public function test_sports_registration_requires_student_gender(): void
    {
        $event = new FestEvent([
            'event_type' => 'sports',
            'event_start' => '2026-06-01',
        ]);

        $item = new FestEventItem(['age_group' => 'u17', 'gender' => 'female']);
        $student = new Student(['name' => 'Student', 'dob' => '2010-06-01']);

        $errors = $this->service->validateStudent($student, $event, $item);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('gender', strtolower($errors[0]));
    }
}
