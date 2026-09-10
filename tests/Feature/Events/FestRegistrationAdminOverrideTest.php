<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestRegistrationCreateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * FestRegistrationCreateService::createForSchool()/updateForSchool() take an
 * $adminOverride flag (set only by SahodayaAdmin's "Register on behalf") that
 * must ignore every registration-window/phase-lifecycle/lock condition and
 * block solely on the item's own results_published_at -- an admin registering
 * a school after its own window closed, or editing/substituting on an already
 * -submitted roster, must not hit "Registration is closed for this event."
 * (See FestItemRegistrationGate::assertOpen()'s $adminOverride branch and the
 * matching branch in updateForSchool().)
 */
class FestRegistrationAdminOverrideTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, event: FestEvent, item: FestEventItem, school: Tenant} */
    private function fixture(): array
    {
        $sahodaya = Tenant::create([
            'id' => 'override-sahodaya-1', 'name' => 'Override Sahodaya', 'type' => 'sahodaya', 'is_active' => true,
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Override Event', 'event_type' => 'kalotsav',
            'status' => 'ongoing', 'approval_policy' => 'auto',
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Solo Song', 'participant_type' => 'individual', 'is_enabled' => true,
        ]);

        $school = Tenant::create([
            'id' => 'override-school-1', 'name' => 'Override School', 'type' => 'school',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        return compact('sahodaya', 'event', 'item', 'school');
    }

    private function makeStudent(Tenant $school, string $adm): Student
    {
        $class = SchoolClass::firstOrCreate(
            ['tenant_id' => $school->id, 'name' => 'Class 5'],
        );

        return Student::create([
            'tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Student '.$adm,
            'admission_number' => $adm, 'status' => 'active', 'verified_at' => now(),
        ]);
    }

    public function test_admin_override_creates_a_registration_even_though_the_event_registration_window_is_closed(): void
    {
        $f = $this->fixture();

        $f['sahodaya']->run(function () use ($f) {
            $student = $this->makeStudent($f['school'], '2001');

            $registration = app(FestRegistrationCreateService::class)->createForSchool(
                $f['event'], $f['item'], $f['school'], [$student->id], adminOverride: true,
            );

            $this->assertSame('approved', $registration->status);
        });
    }

    public function test_without_admin_override_create_still_blocks_when_the_event_registration_window_is_closed(): void
    {
        $f = $this->fixture();

        $f['sahodaya']->run(function () use ($f) {
            $student = $this->makeStudent($f['school'], '2002');

            $this->expectException(ValidationException::class);

            app(FestRegistrationCreateService::class)->createForSchool(
                $f['event'], $f['item'], $f['school'], [$student->id],
            );
        });
    }

    public function test_admin_override_still_blocks_create_once_the_items_results_are_published(): void
    {
        $f = $this->fixture();
        $f['item']->update(['results_published_at' => now()]);

        $f['sahodaya']->run(function () use ($f) {
            $student = $this->makeStudent($f['school'], '2003');

            $this->expectException(ValidationException::class);
            $this->expectExceptionMessage("results are already published");

            app(FestRegistrationCreateService::class)->createForSchool(
                $f['event'], $f['item'], $f['school'], [$student->id], adminOverride: true,
            );
        });
    }

    public function test_admin_override_updates_an_existing_registration_even_though_the_event_registration_window_is_closed(): void
    {
        $f = $this->fixture();

        $f['sahodaya']->run(function () use ($f) {
            $first = $this->makeStudent($f['school'], '2004');
            $second = $this->makeStudent($f['school'], '2005');

            $registration = app(FestRegistrationCreateService::class)->createForSchool(
                $f['event'], $f['item'], $f['school'], [$first->id], adminOverride: true,
            );

            // createForSchool() routes back through here on its own once it finds the
            // existing submitted/approved registration for this school+item -- same path
            // "Register on behalf" hits when substituting/editing an already-registered item.
            $updated = app(FestRegistrationCreateService::class)->updateForSchool(
                $registration->fresh(), $f['event'], $f['item'], $f['school'], [$second->id],
                adminOverride: true,
            );

            $this->assertSame([$second->id], $updated->participants->pluck('student_id')->all());
        });
    }

    public function test_admin_override_still_blocks_update_once_the_items_results_are_published(): void
    {
        $f = $this->fixture();

        $f['sahodaya']->run(function () use ($f) {
            $first = $this->makeStudent($f['school'], '2006');
            $second = $this->makeStudent($f['school'], '2007');

            $registration = app(FestRegistrationCreateService::class)->createForSchool(
                $f['event'], $f['item'], $f['school'], [$first->id], adminOverride: true,
            );

            $f['item']->update(['results_published_at' => now()]);

            $this->expectException(ValidationException::class);
            $this->expectExceptionMessage("results are already published");

            app(FestRegistrationCreateService::class)->updateForSchool(
                $registration->fresh(), $f['event'], $f['item']->fresh(), $f['school'], [$second->id],
                adminOverride: true,
            );
        });
    }
}
