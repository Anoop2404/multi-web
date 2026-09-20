<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A Sahodaya's preferred set of bulk report checkboxes (e.g. "Judge Sheets" + "Digital
 * Sum Sheet") is remembered across events/sessions -- scoped to the tenant, not to any
 * one event, so there's exactly one saved combo per Sahodaya.
 */
class FestBulkReportComboTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: FestEvent, 2: User} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Combo Sahodaya',
            'domain' => 'combo-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'CB', 'student_data_mode' => 'counts_only']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Combo Fest', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        return [$sahodaya, $event, $admin];
    }

    public function test_saves_the_selected_report_types_for_the_sahodaya(): void
    {
        [$sahodaya, $event, $admin] = $this->fixture();

        $response = $this->actingAs($admin)->post(
            "/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/bulk-report-combo",
            ['report_types' => ['judge_sheet', 'sum_sheet']]
        );

        $response->assertRedirect();
        $this->assertSame(['judge_sheet', 'sum_sheet'], SahodayaProfile::where('tenant_id', $sahodaya->id)->first()->bulk_report_combo);
    }

    public function test_an_unknown_report_type_is_rejected(): void
    {
        [$sahodaya, $event, $admin] = $this->fixture();

        $response = $this->actingAs($admin)->post(
            "/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/bulk-report-combo",
            ['report_types' => ['not_a_real_type']]
        );

        $response->assertSessionHasErrors('report_types.0');
    }

    public function test_an_empty_selection_clears_the_saved_combo(): void
    {
        [$sahodaya, $event, $admin] = $this->fixture();

        SahodayaProfile::where('tenant_id', $sahodaya->id)->update(['bulk_report_combo' => ['judge_sheet']]);

        $response = $this->actingAs($admin)->post(
            "/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/bulk-report-combo",
            ['report_types' => []]
        );

        $response->assertRedirect();
        $this->assertSame([], SahodayaProfile::where('tenant_id', $sahodaya->id)->first()->bulk_report_combo);
    }
}
