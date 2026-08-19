<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestGradeConfig;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end HTTP coverage for the grade-taxonomy generalization: an admin can create a
 * grade band with a custom (non-A+/A/B/C) label through the real Settings > Grades
 * endpoint, and that becomes a real, persisted grade — the exact path that was
 * previously blocked by a hardcoded 'in:A_plus,A,B,C' validation rule and, before this
 * migration, a database CHECK constraint that would have rejected the insert anyway.
 */
class FestGradeCustomLabelControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_grade_band_with_a_custom_label(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Custom Grade Test Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'CG', 'student_data_mode' => 'counts_only']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Custom Grade Event', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        $response = $this->actingAs($admin)->post(
            route('sahodaya.events.grade-configs.store', ['tenantId' => $sahodaya->id, 'event' => $event->id]),
            ['grade' => 'Excellent', 'min_score' => 80, 'max_score' => 100],
        );

        $response->assertSessionHas('success');
        $response->assertSessionDoesntHaveErrors();

        $this->assertSame('Excellent', FestGradeConfig::where('event_id', $event->id)->value('grade'));
    }

    public function test_legacy_four_grades_still_work_unchanged(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Legacy Grade Test Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'LG', 'student_data_mode' => 'counts_only']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Legacy Grade Event', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        $response = $this->actingAs($admin)->post(
            route('sahodaya.events.grade-configs.store', ['tenantId' => $sahodaya->id, 'event' => $event->id]),
            ['grade' => 'A_plus', 'min_score' => 90, 'max_score' => 100],
        );

        $response->assertSessionHas('success');
        $this->assertSame('A_plus', FestGradeConfig::where('event_id', $event->id)->value('grade'));
    }
}
