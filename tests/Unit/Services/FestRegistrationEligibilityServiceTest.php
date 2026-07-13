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

    public function test_custom_event_enforces_class_group_on_item(): void
    {
        $event = new FestEvent(['event_type' => 'custom']);
        $item = new FestEventItem(['class_group' => 'up', 'gender' => 'open']);
        $student = new Student(['name' => 'Meera', 'gender' => 'female']);
        $student->setRelation('schoolClass', (object) ['name' => 'Class 8']);

        $errors = $this->service->validateStudent($student, $event, $item);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('belongs to', $errors[0]);
    }

    public function test_custom_event_accepts_matching_class_group(): void
    {
        $event = new FestEvent(['event_type' => 'custom']);
        $item = new FestEventItem(['class_group' => 'up', 'gender' => 'open']);
        $student = new Student(['name' => 'Meera', 'gender' => 'female']);
        $student->setRelation('schoolClass', (object) ['name' => 'Class 6']);

        $this->assertSame([], $this->service->validateStudent($student, $event, $item));
    }

    public function test_custom_event_ignores_class_group_when_item_is_open(): void
    {
        $event = new FestEvent(['event_type' => 'custom']);
        $item = new FestEventItem(['class_group' => 'open', 'gender' => 'open']);
        $student = new Student(['name' => 'Meera', 'gender' => 'female']);
        $student->setRelation('schoolClass', (object) ['name' => 'Class 12']);

        $this->assertSame([], $this->service->validateStudent($student, $event, $item));
    }

    public function test_event_head_verified_only_policy_blocks_unverified_student(): void
    {
        $event = new FestEvent([
            'event_type' => 'sports',
            'event_start' => '2026-06-01',
            'fee_settings' => ['require_verified_students' => false],
        ]);
        $head = new \App\Models\FestItemHead([
            'name' => 'Athletics',
            'verification_policy' => 'verified_only',
        ]);
        $item = new FestEventItem(['age_group' => 'open', 'gender' => 'male']);
        $item->setRelation('head', $head);
        $student = new Student(['name' => 'Arjun', 'gender' => 'male', 'dob' => '2013-06-01']);

        $errors = $this->service->validateStudent($student, $event, $item);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Sahodaya-verified', $errors[0]);
    }
}
