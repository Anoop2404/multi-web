<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventStaff;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantUserCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * An event_admin only ever sees the events list to pick which of their assigned
 * events to work on. With exactly one, there's nothing to pick — landing there just
 * added an extra click to reach the event's own dashboard every single time.
 */
class EventAdminSingleEventRedirectTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $sahodaya;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Single Event Redirect Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $this->sahodaya->id,
            'prefix' => 'SE',
            'student_data_mode' => 'counts_only',
            'active_academic_year' => '2025-26',
        ]);
    }

    public function test_event_admin_assigned_to_exactly_one_event_is_redirected_straight_to_it(): void
    {
        $onlyEvent = FestEvent::create([
            'tenant_id' => $this->sahodaya->id,
            'title' => 'The Only Event',
            'event_type' => 'kalolsavam',
            'status' => 'registration_open',
        ]);

        $admin = User::factory()->create(['tenant_id' => $this->sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('event_admin');
        $admin->givePermissionTo(TenantUserCatalog::defaultPermissionsForRole('event_admin'));
        FestEventStaff::create(['event_id' => $onlyEvent->id, 'user_id' => $admin->id, 'duty' => 'event_admin']);

        $this->actingAs($admin)
            ->get(route('sahodaya.events.index', ['tenantId' => $this->sahodaya->id]))
            ->assertRedirect(route('sahodaya.events.show', ['tenantId' => $this->sahodaya->id, 'event' => $onlyEvent->id]));
    }

    public function test_event_admin_assigned_to_two_events_still_sees_the_list(): void
    {
        $eventA = FestEvent::create(['tenant_id' => $this->sahodaya->id, 'title' => 'Event A', 'event_type' => 'kalolsavam', 'status' => 'registration_open']);
        $eventB = FestEvent::create(['tenant_id' => $this->sahodaya->id, 'title' => 'Event B', 'event_type' => 'kalolsavam', 'status' => 'registration_open']);

        $admin = User::factory()->create(['tenant_id' => $this->sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('event_admin');
        $admin->givePermissionTo(TenantUserCatalog::defaultPermissionsForRole('event_admin'));
        FestEventStaff::create(['event_id' => $eventA->id, 'user_id' => $admin->id, 'duty' => 'event_admin']);
        FestEventStaff::create(['event_id' => $eventB->id, 'user_id' => $admin->id, 'duty' => 'event_admin']);

        $this->actingAs($admin)
            ->get(route('sahodaya.events.index', ['tenantId' => $this->sahodaya->id]))
            ->assertOk();
    }

    public function test_full_sahodaya_admin_is_not_redirected_even_if_the_tenant_has_only_one_event(): void
    {
        FestEvent::create([
            'tenant_id' => $this->sahodaya->id,
            'title' => 'The Only Event In The Tenant',
            'event_type' => 'kalolsavam',
            'status' => 'registration_open',
        ]);

        $fullAdmin = User::factory()->create(['tenant_id' => $this->sahodaya->id, 'email_verified_at' => now()]);
        $fullAdmin->assignRole('sahodaya_admin');

        $this->actingAs($fullAdmin)
            ->get(route('sahodaya.events.index', ['tenantId' => $this->sahodaya->id]))
            ->assertOk();
    }
}
