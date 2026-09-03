<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventStaff;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * TenantUserCatalog::writePermissionForPath() only ever gated write (POST/PUT/DELETE)
 * requests — a staff member with only fest.marks checked could still GET the
 * Registrations/Finance/Settings/etc. pages for an event by typing the URL directly, even
 * though the nav (staffCanSeeNavItem() in sahodayaEventNavPermissions.js) already hides
 * those links for them. viewPermissionsForPath() + SahodayaAdminController's constructor
 * now enforce the same rule the nav implies for GET requests too.
 */
class StaffViewPermissionScopeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $sahodaya;

    private FestEvent $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'View Scope Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $this->sahodaya->id,
            'prefix' => 'VS',
            'student_data_mode' => 'counts_only',
            'active_academic_year' => '2025-26',
        ]);

        $this->event = FestEvent::create([
            'tenant_id' => $this->sahodaya->id,
            'title' => 'View Scope Kalotsav',
            'event_type' => 'kalolsavam',
            'status' => 'registration_open',
        ]);
    }

    public function test_marks_only_staff_cannot_view_registrations_or_results_but_can_view_marks_and_the_dashboard(): void
    {
        $marksOnlyAdmin = User::factory()->create(['tenant_id' => $this->sahodaya->id, 'email_verified_at' => now()]);
        $marksOnlyAdmin->assignRole('event_admin');
        $marksOnlyAdmin->givePermissionTo(['fest.view', 'fest.marks']);
        FestEventStaff::create([
            'event_id' => $this->event->id, 'user_id' => $marksOnlyAdmin->id, 'duty' => 'event_admin',
        ]);

        $this->actingAs($marksOnlyAdmin)
            ->get(route('sahodaya.events.registrations.index', ['tenantId' => $this->sahodaya->id, 'event' => $this->event->id]))
            ->assertForbidden();

        $this->actingAs($marksOnlyAdmin)
            ->get(route('sahodaya.events.results.show', ['tenantId' => $this->sahodaya->id, 'event' => $this->event->id]))
            ->assertForbidden();

        $this->actingAs($marksOnlyAdmin)
            ->get(route('sahodaya.events.marks.index', ['tenantId' => $this->sahodaya->id, 'event' => $this->event->id]))
            ->assertOk();

        // No dedicated permission of its own — falls into the "any fest.* permission"
        // catch-all, so a marks-only staff member can still reach the event's own
        // dashboard to navigate to the area they actually have.
        $this->actingAs($marksOnlyAdmin)
            ->get(route('sahodaya.events.show', ['tenantId' => $this->sahodaya->id, 'event' => $this->event->id]))
            ->assertOk();
    }

    public function test_full_event_admin_with_every_default_permission_is_unaffected(): void
    {
        $eventAdmin = User::factory()->create(['tenant_id' => $this->sahodaya->id, 'email_verified_at' => now()]);
        $eventAdmin->assignRole('event_admin');
        $eventAdmin->givePermissionTo(\App\Support\TenantUserCatalog::defaultPermissionsForRole('event_admin'));
        FestEventStaff::create([
            'event_id' => $this->event->id, 'user_id' => $eventAdmin->id, 'duty' => 'event_admin',
        ]);

        $this->actingAs($eventAdmin)
            ->get(route('sahodaya.events.registrations.index', ['tenantId' => $this->sahodaya->id, 'event' => $this->event->id]))
            ->assertOk();

        $this->actingAs($eventAdmin)
            ->get(route('sahodaya.events.results.show', ['tenantId' => $this->sahodaya->id, 'event' => $this->event->id]))
            ->assertOk();
    }

    public function test_region_admins_default_permission_set_can_view_registrations_but_not_schedule_or_settings(): void
    {
        // Region/phase admin's own event+region scope matching (ResolvesSahodayaAdminScope)
        // is a separate, pre-existing mechanism this test isn't exercising — assigned via
        // event_admin duty instead so only the permission gate under test is in play, using
        // region_admin's exact default permission set (fest.manage but no fest.schedule).
        $staff = User::factory()->create(['tenant_id' => $this->sahodaya->id, 'email_verified_at' => now()]);
        $staff->assignRole('event_admin');
        $staff->givePermissionTo(\App\Support\TenantUserCatalog::defaultPermissionsForRole('region_admin'));
        FestEventStaff::create([
            'event_id' => $this->event->id, 'user_id' => $staff->id, 'duty' => 'event_admin',
        ]);

        $this->actingAs($staff)
            ->get(route('sahodaya.events.registrations.index', ['tenantId' => $this->sahodaya->id, 'event' => $this->event->id]))
            ->assertOk();

        // region_admin holds fest.manage by default (for unrelated reasons — id-cards/
        // food-menu/food-billing have no dedicated permission), but is deliberately
        // excluded from Schedule/Settings — matching the nav's FEST_SCHEDULE/FEST_SETTINGS
        // groups, which don't accept fest.manage as a fallback.
        $this->actingAs($staff)
            ->get(route('sahodaya.events.schedule.index', ['tenantId' => $this->sahodaya->id, 'event' => $this->event->id]))
            ->assertForbidden();
    }
}
