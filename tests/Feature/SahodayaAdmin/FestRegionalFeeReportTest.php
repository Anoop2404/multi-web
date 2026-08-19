<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestRegionalFeeReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_regional_fee_pdf_report_and_export_include_hub_stored_fees(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya',
            'domain' => 'test-'.Str::random(8).'.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'TEST',
            'student_data_mode' => 'counts_only',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $sahodaya->id,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('sahodaya_admin');

        $hubEvent = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Badminton Hub Event',
            'event_type' => 'sports',
            'status' => 'published',
            'conduct_mode' => 'partitioned',
            'academic_year' => '2026-27',
        ]);

        $region = Region::create([
            'tenant_id' => $sahodaya->id,
            'name' => 'Region 2 (Manjeri)',
            'code' => 'R2',
        ]);

        $regionChildEvent = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'parent_event_id' => $hubEvent->id,
            'region_id' => $region->id,
            'title' => 'BADMINTON (R) 2026-27 — REGION 2 (MANJERI)',
            'event_type' => 'sports',
            'status' => 'published',
            'academic_year' => '2026-27',
        ]);

        $school1 = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Markazul Uloom English School',
            'sahodaya_id' => $sahodaya->id,
        ]);
        $school2 = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Peevees Model School',
            'sahodaya_id' => $sahodaya->id,
        ]);

        // Registrations on the region child event
        FestRegistration::create([
            'event_id' => $regionChildEvent->id,
            'school_id' => $school1->id,
            'status' => 'approved',
        ]);
        FestRegistration::create([
            'event_id' => $regionChildEvent->id,
            'school_id' => $school2->id,
            'status' => 'approved',
        ]);

        // Fees stored under the HUB event_id as designed by FestSchoolEventFeeService::feeOwnerEvent
        FestSchoolEventFee::create([
            'event_id' => $hubEvent->id,
            'school_id' => $school1->id,
            'school_registration_fee' => 0,
            'participation_fee' => 2000,
            'total_due' => 2000,
            'amount_paid' => 2000,
            'status' => 'approved',
            'participation_item_count' => 1,
        ]);
        FestSchoolEventFee::create([
            'event_id' => $hubEvent->id,
            'school_id' => $school2->id,
            'school_registration_fee' => 0,
            'participation_fee' => 2000,
            'total_due' => 2000,
            'amount_paid' => 0,
            'status' => 'pending',
            'participation_item_count' => 1,
        ]);

        // Request PDF report for region child event
        $response = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$regionChildEvent->id}/fees/pdf?preview=1");

        $response->assertOk();

        // Request CSV export for region child event
        $exportResponse = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$regionChildEvent->id}/fees/export");

        $exportResponse->assertOk();
        $exportContent = $exportResponse->streamedContent();
        $this->assertStringContainsString(strtoupper('Markazul Uloom English School'), $exportContent);
        $this->assertStringContainsString(strtoupper('Peevees Model School'), $exportContent);
    }
}
