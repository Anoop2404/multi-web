<?php

namespace Tests\Feature\Admin;

use App\Models\PlatformDashboardSnapshot;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function actingSuperadmin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $superadmin = User::factory()->create([
            'tenant_id' => null,
            'email_verified_at' => now(),
        ]);
        $superadmin->assignRole('superadmin');

        return $superadmin;
    }

    public function test_dashboard_has_no_snapshot_prop_when_none_computed_yet(): void
    {
        $superadmin = $this->actingSuperadmin();

        $this->actingAs($superadmin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard', false)
                ->where('snapshot', null)
            );
    }

    public function test_dashboard_exposes_the_latest_computed_snapshot(): void
    {
        $superadmin = $this->actingSuperadmin();

        PlatformDashboardSnapshot::create([
            'total_students' => 150, 'total_teachers' => 12,
            'revenue_this_month_inr' => 45000, 'sahodayas_included' => 3, 'sahodayas_total' => 3,
            'computed_at' => now(),
        ]);

        $this->actingAs($superadmin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard', false)
                ->where('snapshot.total_students', 150)
                ->where('snapshot.total_teachers', 12)
            );
    }
}
