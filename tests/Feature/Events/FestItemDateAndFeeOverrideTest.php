<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestRegistrationCreateService;
use App\Services\Events\FestSchoolEventFeeService;
use Database\Seeders\SahodayaMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Confirms an item's own reg_start/reg_end and fee_amount actually take effect on the
 * live registration path (FestRegistrationCreateService::createForSchool()), not just in
 * the isolated FestItemWindowResolver/FestSchoolEventFeeService unit tests — an item-level
 * date window must gate registration even while the event itself is open, and an
 * item-level fee override must be what's actually billed.
 */
class FestItemDateAndFeeOverrideTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, school: Tenant, event: FestEvent, student: Student} */
    private function context(): array
    {
        $this->seed(SahodayaMasterDataSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Item Window Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'IWS',
            'student_data_mode' => 'full_records',
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Item Window School',
            'parent_id' => $sahodaya->id,
            'school_prefix' => 'IW',
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Item Window Kalotsav',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
            'fee_type' => 'none',
            'fee_settings' => null,
        ]);

        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10', 'is_active' => true]);
        $student = Student::create([
            'tenant_id' => $school->id,
            'school_class_id' => $class->id,
            'name' => 'Student A',
            'gender' => 'male',
            'dob' => '2012-01-01',
            'status' => 'active',
            'verified_at' => now(),
        ]);

        return compact('sahodaya', 'school', 'event', 'student');
    }

    public function test_item_reg_start_in_future_blocks_registration_even_though_event_is_open(): void
    {
        ['school' => $school, 'event' => $event, 'student' => $student] = $this->context();

        $item = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Solo Song',
            'participant_type' => 'individual',
            'is_enabled' => true,
            'reg_start' => now()->addWeek()->toDateString(),
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/Registration is closed for this item/');

        app(FestRegistrationCreateService::class)->createForSchool($event, $item, $school, [$student->id]);
    }

    public function test_item_reg_end_in_past_blocks_registration_even_though_event_is_open(): void
    {
        ['school' => $school, 'event' => $event, 'student' => $student] = $this->context();

        $item = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Solo Song',
            'participant_type' => 'individual',
            'is_enabled' => true,
            'reg_start' => now()->subMonth()->toDateString(),
            'reg_end' => now()->subWeek()->toDateString(),
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/Registration is closed for this item/');

        app(FestRegistrationCreateService::class)->createForSchool($event, $item, $school, [$student->id]);
    }

    public function test_registration_no_orphan_left_behind_when_item_window_closed(): void
    {
        ['school' => $school, 'event' => $event, 'student' => $student] = $this->context();

        $item = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Solo Song',
            'participant_type' => 'individual',
            'is_enabled' => true,
            'reg_start' => now()->addWeek()->toDateString(),
        ]);

        try {
            app(FestRegistrationCreateService::class)->createForSchool($event, $item, $school, [$student->id]);
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame(0, \App\Models\FestRegistration::where('event_id', $event->id)->where('school_id', $school->id)->count());
    }

    public function test_registration_succeeds_within_the_items_own_window(): void
    {
        ['school' => $school, 'event' => $event, 'student' => $student] = $this->context();

        $item = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Solo Song',
            'participant_type' => 'individual',
            'is_enabled' => true,
            'reg_start' => now()->subDay()->toDateString(),
            'reg_end' => now()->addWeek()->toDateString(),
        ]);

        $registration = app(FestRegistrationCreateService::class)->createForSchool($event, $item, $school, [$student->id]);

        $this->assertNotNull($registration->id);
    }

    public function test_item_own_window_overrides_a_wider_head_window(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'event' => $event, 'student' => $student] = $this->context();

        $head = \App\Models\FestItemHead::create([
            'tenant_id' => $sahodaya->id,
            'event_id' => $event->id,
            'event_type' => 'kalolsavam',
            'name' => 'Stage',
            'slug' => 'stage',
            'sort_order' => 1,
            'reg_start' => now()->subMonth()->toDateString(),
            'reg_end' => now()->addMonth()->toDateString(),
        ]);

        // Head window is wide open, but this item narrows its own window to a past range —
        // the item's own dates must win over the (otherwise still-open) head window.
        $item = FestEventItem::create([
            'event_id' => $event->id,
            'head_id' => $head->id,
            'title' => 'Solo Song',
            'participant_type' => 'individual',
            'is_enabled' => true,
            'reg_start' => now()->subMonth()->toDateString(),
            'reg_end' => now()->subWeek()->toDateString(),
        ]);

        $this->expectException(ValidationException::class);
        app(FestRegistrationCreateService::class)->createForSchool($event, $item, $school, [$student->id]);
    }

    public function test_item_fee_amount_override_is_billed_end_to_end_within_its_own_window(): void
    {
        ['school' => $school, 'event' => $event, 'student' => $student] = $this->context();

        $item = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Solo Song',
            'participant_type' => 'individual',
            'is_enabled' => true,
            'fee_amount' => 350,
            'reg_start' => now()->subDay()->toDateString(),
            'reg_end' => now()->addWeek()->toDateString(),
        ]);

        app(FestRegistrationCreateService::class)->createForSchool($event, $item, $school, [$student->id]);

        $fee = app(FestSchoolEventFeeService::class)->recalculate($event->fresh(), $school->id);

        $this->assertSame(350.0, (float) $fee->total_due);
    }
}
