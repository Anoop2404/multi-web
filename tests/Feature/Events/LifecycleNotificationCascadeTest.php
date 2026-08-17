<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestRegistration;
use App\Models\FestStateProgram;
use App\Models\FestStateSubmissionOutbox;
use App\Models\InAppNotification;
use App\Models\NotificationTemplate;
use App\Models\SahodayaProfile;
use App\Models\StateDomain;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\FestEventNotifier;
use App\Services\Events\FestRegistrationCreateService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression tests for LIFE-08/09/10/11/12 (functional audit, 2026-08-11/12) — see
 * the fix comments in FestResultsController::unpublish(), FestEventNotifier,
 * FestRegistrationCreateService, FestRegistrationController::store(),
 * SubmitStateQualifiersJob, and ProcessStateSubmissionOutbox for what each of these
 * previously did NOT do.
 */
class LifecycleNotificationCascadeTest extends TestCase
{
    use RefreshDatabase;

    private function makeSahodayaAndSchool(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Lifecycle Notify Sahodaya',
            'domain'    => 'lifecycle-notify.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'         => $sahodaya->id,
            'prefix'            => 'LN',
            'student_data_mode' => 'counts_only',
        ]);

        $school = Tenant::create([
            'id'                 => (string) Str::uuid(),
            'type'               => 'school',
            'name'               => 'Lifecycle Notify School',
            'parent_id'          => $sahodaya->id,
            'membership_status'  => 'approved',
            'is_active'          => true,
        ]);

        return compact('sahodaya', 'school');
    }

    // ------------------------------------------------------------------
    // LIFE-08/09: unpublish() cascade + notification
    // ------------------------------------------------------------------

    public function test_unpublish_cascades_to_region_and_finale_children_and_notifies_schools(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool();

        NotificationTemplate::updateOrCreate(['slug' => 'fest.results.unpublished'], [
            'title'         => 'Results unpublished',
            'body_template' => 'Results for {{event_title}} were unpublished.',
            'channels_json' => ['in_app'],
            'is_active'     => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $schoolAdmin = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $schoolAdmin->assignRole('school_admin');

        $hub = FestEvent::create([
            'tenant_id'          => $sahodaya->id,
            'title'              => 'Grand Kalotsavam',
            'event_type'         => 'kalolsavam',
            'conduct_mode'       => 'partitioned',
            'level_round'        => 'sahodaya',
            'status'             => 'completed',
            'results_published'  => true,
        ]);

        $regionChild = FestEvent::create([
            'tenant_id'          => $sahodaya->id,
            'title'              => 'Region Leg',
            'event_type'         => 'kalolsavam',
            'parent_event_id'    => $hub->id,
            'partition_key'      => 'region-a',
            'partition_role'     => 'region',
            'level_round'        => 'sahodaya',
            'status'             => 'completed',
            'results_published'  => true,
        ]);

        $finaleChild = FestEvent::create([
            'tenant_id'          => $sahodaya->id,
            'title'              => 'Grand Finale',
            'event_type'         => 'kalolsavam',
            'parent_event_id'    => $hub->id,
            'partition_key'      => 'finale',
            'partition_role'     => 'finale',
            'level_round'        => 'sahodaya',
            'status'             => 'completed',
            'results_published'  => true,
        ]);

        $item = FestEventItem::create([
            'event_id'  => $hub->id,
            'title'     => 'Solo Song',
            'category'  => 'music',
            'item_code' => 'SS01',
        ]);

        FestRegistration::create([
            'event_id'  => $hub->id,
            'item_id'   => $item->id,
            'school_id' => $school->id,
            'status'    => 'approved',
        ]);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.results.unpublish', [
            'tenantId' => $sahodaya->id,
            'event'    => $hub->id,
        ]));

        $response->assertSessionDoesntHaveErrors();

        $hub->refresh();
        $this->assertFalse((bool) $hub->results_published);
        $this->assertSame('ongoing', $hub->status);

        // LIFE-08: region AND finale children both moved with the hub, not just region.
        $regionChild->refresh();
        $finaleChild->refresh();
        $this->assertFalse((bool) $regionChild->results_published);
        $this->assertSame('ongoing', $regionChild->status);
        $this->assertFalse((bool) $finaleChild->results_published);
        $this->assertSame('ongoing', $finaleChild->status);

        // LIFE-09: the school that had a registration on this event was notified.
        $this->assertTrue(
            InAppNotification::where('user_id', $schoolAdmin->id)
                ->where('title', 'Results unpublished')
                ->exists()
        );
    }

    // ------------------------------------------------------------------
    // LIFE-10: roster-edit-triggered approved -> submitted regression
    // ------------------------------------------------------------------

    public function test_roster_edit_regression_notifies_school_and_admin_when_previously_approved(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool();

        NotificationTemplate::updateOrCreate(['slug' => 'fest.registration.needs_reapproval'], [
            'title'         => 'Needs re-approval',
            'body_template' => 'Roster changed for {{event_title}}.',
            'channels_json' => ['in_app'],
            'is_active'     => true,
        ]);
        NotificationTemplate::updateOrCreate(['slug' => 'fest.registration.needs_reapproval_admin'], [
            'title'         => 'Registration needs re-approval (admin)',
            'body_template' => 'A school changed its roster for {{event_title}}.',
            'channels_json' => ['in_app'],
            'is_active'     => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $schoolAdmin = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $schoolAdmin->assignRole('school_admin');

        $event = FestEvent::create([
            'tenant_id'   => $sahodaya->id,
            'title'       => 'Teacher Meet',
            'event_type'  => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status'      => 'ongoing',
        ]);

        $item = FestEventItem::create([
            'event_id'  => $event->id,
            'title'     => 'Elocution',
            'category'  => 'general',
            'item_code' => 'EL01',
        ]);

        $registration = FestRegistration::create([
            'event_id'  => $event->id,
            'item_id'   => $item->id,
            'school_id' => $school->id,
            'status'    => 'submitted',
        ]);

        $service = app(FestRegistrationCreateService::class);
        $method = new \ReflectionMethod($service, 'notifyIfRosterEditRevokedApproval');
        $method->setAccessible(true);

        // $registration is already 'submitted' (simulating the roster edit having
        // already run its own status update) — wasApproved=true tells the helper it
        // WAS 'approved' before that edit. This is the exact regression LIFE-10
        // covers: both sides should hear about the dropped approval.
        $method->invoke($service, true, $registration->fresh(['event', 'item']));

        $this->assertTrue(
            InAppNotification::where('user_id', $schoolAdmin->id)->where('title', 'Needs re-approval')->exists()
        );
        $this->assertTrue(
            InAppNotification::where('user_id', $admin->id)->where('title', 'Registration needs re-approval (admin)')->exists()
        );
    }

    public function test_roster_edit_does_not_notify_when_registration_was_not_previously_approved(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool();

        NotificationTemplate::updateOrCreate(['slug' => 'fest.registration.needs_reapproval'], [
            'title'         => 'Needs re-approval',
            'body_template' => 'Roster changed for {{event_title}}.',
            'channels_json' => ['in_app'],
            'is_active'     => true,
        ]);

        $schoolAdmin = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $schoolAdmin->assignRole('school_admin');

        $event = FestEvent::create([
            'tenant_id'   => $sahodaya->id,
            'title'       => 'Teacher Meet',
            'event_type'  => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status'      => 'ongoing',
        ]);

        $item = FestEventItem::create([
            'event_id'  => $event->id,
            'title'     => 'Elocution',
            'category'  => 'general',
            'item_code' => 'EL01',
        ]);

        $registration = FestRegistration::create([
            'event_id'  => $event->id,
            'item_id'   => $item->id,
            'school_id' => $school->id,
            'status'    => 'submitted',
        ]);

        $service = app(FestRegistrationCreateService::class);
        $method = new \ReflectionMethod($service, 'notifyIfRosterEditRevokedApproval');
        $method->setAccessible(true);

        // wasApproved = false (it was already 'submitted', not 'approved', before the
        // edit) — no re-approval was actually revoked, so no notification should fire.
        $method->invoke($service, false, $registration->fresh(['event', 'item']));

        $this->assertFalse(
            InAppNotification::where('user_id', $schoolAdmin->id)->where('title', 'Needs re-approval')->exists()
        );
    }

    // ------------------------------------------------------------------
    // LIFE-11: initial registration submission -> admin notification
    // ------------------------------------------------------------------

    public function test_registration_submitted_admin_notifies_for_statuses_needing_review(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool();

        NotificationTemplate::updateOrCreate(['slug' => 'fest.registration.submitted_admin'], [
            'title'         => 'New registration submitted',
            'body_template' => 'A new registration for {{event_title}} needs review.',
            'channels_json' => ['in_app'],
            'is_active'     => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id'   => $sahodaya->id,
            'title'       => 'New Submissions Event',
            'event_type'  => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status'      => 'published',
        ]);

        $item = FestEventItem::create([
            'event_id'  => $event->id,
            'title'     => 'Group Dance',
            'category'  => 'dance',
            'item_code' => 'GD01',
        ]);

        $submitted = FestRegistration::create([
            'event_id'  => $event->id,
            'item_id'   => $item->id,
            'school_id' => $school->id,
            'status'    => 'submitted',
        ]);

        $waitlisted = FestRegistration::create([
            'event_id'  => $event->id,
            'item_id'   => $item->id,
            'school_id' => $school->id,
            'status'    => 'waitlisted',
        ]);

        $notifier = app(FestEventNotifier::class);
        $notifier->registrationSubmittedAdmin($submitted->fresh(['event', 'item']));
        $notifier->registrationSubmittedAdmin($waitlisted->fresh(['event', 'item']));

        // Only the 'submitted' registration needs a review — waitlisted has nothing
        // for an admin to act on yet, so it must not add a second notification.
        $this->assertSame(
            1,
            InAppNotification::where('user_id', $admin->id)->where('title', 'New registration submitted')->count()
        );
    }

    // ------------------------------------------------------------------
    // LIFE-12: state-submission outbox retry cap
    // ------------------------------------------------------------------

    public function test_process_state_outbox_skips_rows_over_the_attempt_cap(): void
    {
        $domain = StateDomain::create([
            'id'            => (string) Str::uuid(),
            'name'          => 'State Body',
            'domain'        => 'state.test',
            'api_base_url'  => 'https://state.example.test',
            'status'        => 'active',
        ]);

        $program = FestStateProgram::create([
            'id'              => (string) Str::uuid(),
            'title'           => 'State Program',
            'event_type'      => 'kalolsavam',
            'status'          => 'published',
            'state_domain_id' => $domain->id,
        ]);

        $stuckRow = FestStateSubmissionOutbox::create([
            'id'               => (string) Str::uuid(),
            'state_program_id' => $program->id,
            'source_event_id'  => 1,
            'idempotency_key'  => 'stuck-'.Str::uuid(),
            'payload'          => ['x' => 1],
            'status'           => 'failed',
            'attempts'         => 10, // at the cap
        ]);

        $freshRow = FestStateSubmissionOutbox::create([
            'id'               => (string) Str::uuid(),
            'state_program_id' => $program->id,
            'source_event_id'  => 2,
            'idempotency_key'  => 'fresh-'.Str::uuid(),
            'payload'          => ['x' => 2],
            'status'           => 'pending',
            'attempts'         => 0,
        ]);

        Http::fake([
            'state.example.test/*' => Http::response(['intake_id' => 'abc123'], 200),
        ]);

        Artisan::call('fest:process-state-outbox');

        // The stuck row must not have been touched at all (still 10 attempts, still
        // 'failed') — proof the cap actually skipped it rather than retrying forever.
        $this->assertSame(10, $stuckRow->fresh()->attempts);
        $this->assertSame('failed', $stuckRow->fresh()->status);

        // The fresh row, unaffected by the cap, was processed normally.
        $this->assertSame(1, $freshRow->fresh()->attempts);
        $this->assertSame('completed', $freshRow->fresh()->status);

        Http::assertSentCount(1);
    }
}
