<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Same bug as Team Managers, same fix: opening Unique Participant Counts directly on one
 * phase+region leg (the normal way an admin reaches it from that leg's own event context)
 * used to silently combine every other leg's students in too, because
 * regionAwareTargetEvent() detaches ANY leaf from its parent even with no ?region_id= at
 * all -- which then fools FestReportController::reportScope()'s own mode fallback into
 * 'combined'. A bare visit to one leg must mean exactly that leg.
 */
class FestUniqueParticipantsLeafScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_visiting_one_leaf_directly_shows_only_that_leafs_own_students(): void
    {
        $f = $this->twoLegFixture();

        $response = $this->actingAs($f['admin'])->get(
            "/sahodaya-admin/{$f['sahodaya']->id}/events/{$f['leafA']->id}/reports/unique-participants"
        );

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Sahodaya/Events/Reports/UniqueParticipants', false)
            ->where('totals.total_unique_participants', 1)
        );
    }

    public function test_explicit_region_id_still_resolves_to_the_right_leaf(): void
    {
        $f = $this->twoLegFixture();

        $response = $this->actingAs($f['admin'])->get(
            "/sahodaya-admin/{$f['sahodaya']->id}/events/{$f['root']->id}/reports/unique-participants?region_id={$f['regionA']->id}"
        );

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Sahodaya/Events/Reports/UniqueParticipants', false)
            ->where('totals.total_unique_participants', 1)
        );
    }

    public function test_explicit_combined_scope_on_the_root_still_combines_every_leg(): void
    {
        $f = $this->twoLegFixture();

        $response = $this->actingAs($f['admin'])->get(
            "/sahodaya-admin/{$f['sahodaya']->id}/events/{$f['root']->id}/reports/unique-participants"
        );

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Sahodaya/Events/Reports/UniqueParticipants', false)
            ->where('totals.total_unique_participants', 2)
        );
    }

    /** @return array{sahodaya: Tenant, admin: User, root: FestEvent, leafA: FestEvent, leafB: FestEvent, regionA: Region} */
    private function twoLegFixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Leaf Scope Sahodaya',
            'domain' => 'leaf-scope-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'LFS', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Leaf Scope School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);
        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $regionA = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region A', 'code' => 'RGA']);
        $regionB = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region B', 'code' => 'RGB']);

        $root = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Leaf Scope Kalotsav', 'event_type' => 'kalolsavam',
            'workflow_mode' => 'phased_regional_billing', 'status' => 'published',
        ]);
        $phase = FestEventPhase::create(['event_id' => $root->id, 'name' => 'Off Stage', 'code' => 'off-stage', 'is_regional' => true]);

        $leafA = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'parent_event_id' => $root->id, 'root_event_id' => $root->id,
            'source_phase_id' => $phase->id, 'region_id' => $regionA->id,
            'title' => 'Off Stage — Region A', 'event_type' => 'kalolsavam',
            'workflow_mode' => 'phased_regional_billing', 'status' => 'published',
        ]);
        $leafB = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'parent_event_id' => $root->id, 'root_event_id' => $root->id,
            'source_phase_id' => $phase->id, 'region_id' => $regionB->id,
            'title' => 'Off Stage — Region B', 'event_type' => 'kalolsavam',
            'workflow_mode' => 'phased_regional_billing', 'status' => 'published',
        ]);

        foreach ([['leaf' => $leafA, 'name' => 'Region A Student'], ['leaf' => $leafB, 'name' => 'Region B Student']] as $row) {
            $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => $row['name'], 'status' => 'active']);
            $item = FestEventItem::create(['event_id' => $row['leaf']->id, 'title' => 'Item for '.$row['name'], 'participant_type' => 'individual', 'is_enabled' => true]);
            $reg = FestRegistration::create(['event_id' => $row['leaf']->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
            FestParticipant::create(['registration_id' => $reg->id, 'student_id' => $student->id, 'participant_type' => 'student', 'participant_role' => 'performer', 'chest_no' => 1]);
        }

        return compact('sahodaya', 'admin', 'root', 'leafA', 'leafB', 'regionA');
    }
}
