<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestAuditRegionConversionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_branch_a_when_event_has_no_data(): void
    {
        [$sahodaya, $root] = $this->fixture();

        $this->artisan('fest:audit-region-conversion-readiness', [
            '--sahodaya' => $sahodaya->id,
            '--event' => $root->id,
        ])
            ->expectsOutputToContain('BRANCH A')
            ->assertExitCode(0);
    }

    public function test_reports_branch_b_when_registrations_exist_on_a_region_child(): void
    {
        [$sahodaya, $root] = $this->fixture();

        $child = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Tirur Region',
            'event_type' => 'kalolsavam',
            'parent_event_id' => $root->id,
            'partition_role' => 'region',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
            'fee_type' => 'none',
        ]);
        FestRegistration::create([
            'event_id' => $child->id,
            'item_id' => null,
            'school_id' => (string) Str::uuid(),
            'status' => 'approved',
            'submitted_at' => now(),
        ]);

        $this->artisan('fest:audit-region-conversion-readiness', [
            '--sahodaya' => $sahodaya->id,
            '--event' => $root->id,
        ])
            ->expectsOutputToContain('BRANCH B REQUIRED')
            ->assertExitCode(0);
    }

    /** @return array{0: Tenant, 1: FestEvent} */
    private function fixture(): array
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Readiness Test Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'RDY',
            'student_data_mode' => 'counts_only',
        ]);
        $root = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Readiness Test Kalotsav',
            'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
            'fee_type' => 'none',
        ]);

        return [$sahodaya, $root];
    }
}
