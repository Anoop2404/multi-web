<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventStaff;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression coverage for the Portal Users "Region admin — event & region" form, which is
 * documented in the UI as "locked to this one event and this one region only" but whose
 * backend (TenantUserController::syncRegionAdminAssignment()) used to only ever
 * firstOrCreate() the submitted pair and never remove a previous one — so re-editing a
 * user and picking a different event silently accumulated a second FestEventStaff row
 * instead of replacing the first. Symptom reported live: a region_admin assigned to a
 * region-partition child event, then re-assigned to the child's parent hub event, ended up
 * with BOTH assignments simultaneously, and the edit form kept reloading the stale one.
 */
class TenantUserRegionAdminAssignmentTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, admin: User, target: User, regionA: Region, regionB: Region, hub: FestEvent, child: FestEvent, otherEvent: FestEvent} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Region Admin Assignment Test Sahodaya',
            'domain' => 'region-admin-assignment-test.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'RAT',
            'student_data_mode' => 'counts_only',
            'active_academic_year' => '2025-26',
        ]);

        $regionA = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region A', 'code' => 'RAA', 'is_active' => true]);
        $regionB = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region B', 'code' => 'RAB', 'is_active' => true]);

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'English Fest 2026-27',
            'event_type' => 'english_fest',
            'conduct_mode' => 'partitioned',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        $child = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'English Fest 2026-27 — Region A',
            'event_type' => 'english_fest',
            'parent_event_id' => $hub->id,
            'partition_key' => 'region-a',
            'partition_role' => 'region',
            'region_id' => $regionA->id,
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        $otherEvent = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Kalotsav 2026-27',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id]);
        $admin->assignRole('sahodaya_admin');

        $target = User::factory()->create(['tenant_id' => $sahodaya->id]);
        $target->assignRole('region_admin');

        return compact('sahodaya', 'admin', 'target', 'regionA', 'regionB', 'hub', 'child', 'otherEvent');
    }

    private function updatePayload(User $target, array $overrides = []): array
    {
        return array_merge([
            'name' => $target->name,
            'email' => $target->email,
            'username' => $target->username,
            'roles' => ['region_admin'],
        ], $overrides);
    }

    public function test_editing_the_event_updates_the_existing_row_instead_of_adding_a_second_one(): void
    {
        $f = $this->fixture();

        $existing = FestEventStaff::create([
            'event_id' => $f['child']->id,
            'user_id' => $f['target']->id,
            'duty' => 'region_admin',
            'region_id' => $f['regionA']->id,
        ]);

        $this->actingAs($f['admin'])
            ->put(route('sahodaya.users.update', ['tenantId' => $f['sahodaya']->id, 'user' => $f['target']->id]), $this->updatePayload($f['target'], [
                'region_admin_event_id' => $f['hub']->id,
                'region_admin_region_id' => $f['regionA']->id,
                'region_admin_assignment_id' => $existing->id,
            ]))
            ->assertSessionDoesntHaveErrors();

        $rows = FestEventStaff::where('user_id', $f['target']->id)->where('duty', 'region_admin')->get();

        $this->assertCount(1, $rows, 'Re-picking a different event must update the tracked row, not add a second one.');
        $this->assertSame($existing->id, $rows->first()->id);
        $this->assertSame($f['hub']->id, $rows->first()->event_id);
        $this->assertSame($f['regionA']->id, $rows->first()->region_id);
    }

    public function test_editing_without_a_prior_assignment_creates_exactly_one_row(): void
    {
        $f = $this->fixture();

        $this->actingAs($f['admin'])
            ->put(route('sahodaya.users.update', ['tenantId' => $f['sahodaya']->id, 'user' => $f['target']->id]), $this->updatePayload($f['target'], [
                'region_admin_event_id' => $f['child']->id,
                'region_admin_region_id' => $f['regionA']->id,
            ]))
            ->assertSessionDoesntHaveErrors();

        $rows = FestEventStaff::where('user_id', $f['target']->id)->where('duty', 'region_admin')->get();

        $this->assertCount(1, $rows);
        $this->assertSame($f['child']->id, $rows->first()->event_id);
    }

    /**
     * A user can separately hold a region_admin row on a different event, granted via that
     * event's own Staff tab (FestEventStaffController) rather than this form. Saving this
     * form must only ever touch the one row it is tracking — never that other grant.
     */
    public function test_saving_does_not_touch_a_region_admin_row_from_a_different_event(): void
    {
        $f = $this->fixture();

        $tracked = FestEventStaff::create([
            'event_id' => $f['child']->id,
            'user_id' => $f['target']->id,
            'duty' => 'region_admin',
            'region_id' => $f['regionA']->id,
        ]);

        $fromEventStaffPage = FestEventStaff::create([
            'event_id' => $f['otherEvent']->id,
            'user_id' => $f['target']->id,
            'duty' => 'region_admin',
            'region_id' => $f['regionB']->id,
        ]);

        $this->actingAs($f['admin'])
            ->put(route('sahodaya.users.update', ['tenantId' => $f['sahodaya']->id, 'user' => $f['target']->id]), $this->updatePayload($f['target'], [
                'region_admin_event_id' => $f['hub']->id,
                'region_admin_region_id' => $f['regionA']->id,
                'region_admin_assignment_id' => $tracked->id,
            ]))
            ->assertSessionDoesntHaveErrors();

        $rows = FestEventStaff::where('user_id', $f['target']->id)->where('duty', 'region_admin')->get()->keyBy('id');

        $this->assertCount(2, $rows);
        $this->assertSame($f['hub']->id, $rows->get($tracked->id)->event_id);
        $this->assertSame($f['otherEvent']->id, $rows->get($fromEventStaffPage->id)->event_id, 'The other event\'s grant must be untouched.');
        $this->assertSame($f['regionB']->id, $rows->get($fromEventStaffPage->id)->region_id);
    }

    public function test_an_assignment_id_belonging_to_another_user_is_rejected(): void
    {
        $f = $this->fixture();

        $otherUser = User::factory()->create(['tenant_id' => $f['sahodaya']->id]);
        $otherUser->assignRole('region_admin');
        $foreignRow = FestEventStaff::create([
            'event_id' => $f['child']->id,
            'user_id' => $otherUser->id,
            'duty' => 'region_admin',
            'region_id' => $f['regionA']->id,
        ]);

        $this->actingAs($f['admin'])
            ->put(route('sahodaya.users.update', ['tenantId' => $f['sahodaya']->id, 'user' => $f['target']->id]), $this->updatePayload($f['target'], [
                'region_admin_event_id' => $f['hub']->id,
                'region_admin_region_id' => $f['regionA']->id,
                'region_admin_assignment_id' => $foreignRow->id,
            ]))
            ->assertSessionHasErrors('region_admin_assignment_id');
    }

    /**
     * Per-assignment removal (Portal Users listing) — lets an admin clear a single stale
     * FestEventStaff row (e.g. a leftover duplicate from the accumulation bug above) without
     * deleting the whole user account.
     */
    public function test_removing_one_assignment_leaves_the_others_intact(): void
    {
        $f = $this->fixture();

        $toRemove = FestEventStaff::create([
            'event_id' => $f['child']->id,
            'user_id' => $f['target']->id,
            'duty' => 'region_admin',
            'region_id' => $f['regionA']->id,
        ]);

        $toKeep = FestEventStaff::create([
            'event_id' => $f['hub']->id,
            'user_id' => $f['target']->id,
            'duty' => 'region_admin',
            'region_id' => $f['regionA']->id,
        ]);

        $this->actingAs($f['admin'])
            ->delete(route('sahodaya.users.fest-assignments.destroy', [
                'tenantId' => $f['sahodaya']->id,
                'user' => $f['target']->id,
                'assignment' => $toRemove->id,
            ]))
            ->assertSessionDoesntHaveErrors();

        $this->assertModelMissing($toRemove);
        $this->assertModelExists($toKeep);
    }

    public function test_removing_an_assignment_belonging_to_another_user_is_rejected(): void
    {
        $f = $this->fixture();

        $otherUser = User::factory()->create(['tenant_id' => $f['sahodaya']->id]);
        $otherUser->assignRole('region_admin');
        $foreignRow = FestEventStaff::create([
            'event_id' => $f['child']->id,
            'user_id' => $otherUser->id,
            'duty' => 'region_admin',
            'region_id' => $f['regionA']->id,
        ]);

        $this->actingAs($f['admin'])
            ->delete(route('sahodaya.users.fest-assignments.destroy', [
                'tenantId' => $f['sahodaya']->id,
                'user' => $f['target']->id,
                'assignment' => $foreignRow->id,
            ]))
            ->assertNotFound();

        $this->assertModelExists($foreignRow);
    }
}
