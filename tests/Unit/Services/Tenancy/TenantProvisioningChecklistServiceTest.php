<?php

namespace Tests\Unit\Services\Tenancy;

use App\Models\Tenant;
use App\Services\Tenancy\TenantProvisioningChecklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantProvisioningChecklistServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(string $type): Tenant
    {
        return Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'name' => 'Checklist Test '.$type,
            'is_active' => true,
        ]);
    }

    public function test_steps_for_school_excludes_database_steps(): void
    {
        $school = $this->makeTenant('school');

        $steps = TenantProvisioningChecklistService::stepsFor($school);

        $this->assertSame(
            ['tenant_created', 'portal_admin_created', 'logo_uploaded'],
            array_keys($steps)
        );
    }

    public function test_steps_for_sahodaya_includes_database_steps(): void
    {
        $sahodaya = $this->makeTenant('sahodaya');

        $steps = TenantProvisioningChecklistService::stepsFor($sahodaya);

        $this->assertSame(
            ['tenant_created', 'database_configured', 'database_migrated', 'portal_admin_created', 'logo_uploaded'],
            array_keys($steps)
        );
    }

    public function test_status_for_a_fresh_tenant_has_no_completed_steps(): void
    {
        $school = $this->makeTenant('school');

        $status = (new TenantProvisioningChecklistService)->statusFor($school);

        $this->assertFalse($status['complete']);
        $this->assertSame(3, $status['pending_count']);
        $this->assertFalse($status['steps']['tenant_created']['completed']);
        $this->assertNull($status['steps']['tenant_created']['completed_at']);
    }

    public function test_mark_complete_and_status_for_round_trip(): void
    {
        $school = $this->makeTenant('school');
        $service = new TenantProvisioningChecklistService;

        $service->markComplete($school, 'tenant_created', 42);
        $status = $service->statusFor($school);

        $this->assertTrue($status['steps']['tenant_created']['completed']);
        $this->assertNotNull($status['steps']['tenant_created']['completed_at']);
        $this->assertFalse($status['steps']['portal_admin_created']['completed']);
        $this->assertFalse($status['complete']);
        $this->assertSame(2, $status['pending_count']);
    }

    public function test_status_for_reports_complete_once_every_applicable_step_is_done(): void
    {
        $school = $this->makeTenant('school');
        $service = new TenantProvisioningChecklistService;

        foreach (array_keys(TenantProvisioningChecklistService::stepsFor($school)) as $step) {
            $service->markComplete($school, $step);
        }

        $status = $service->statusFor($school);

        $this->assertTrue($status['complete']);
        $this->assertSame(0, $status['pending_count']);
    }

    public function test_mark_complete_is_idempotent_per_step(): void
    {
        $school = $this->makeTenant('school');
        $service = new TenantProvisioningChecklistService;

        $service->markComplete($school, 'logo_uploaded', 1);
        $service->markComplete($school, 'logo_uploaded', 2);

        $this->assertSame(
            1,
            \App\Models\TenantProvisioningChecklist::where('tenant_id', $school->id)
                ->where('step_key', 'logo_uploaded')
                ->count()
        );
    }

    public function test_checklists_are_isolated_per_tenant(): void
    {
        $schoolA = $this->makeTenant('school');
        $schoolB = $this->makeTenant('school');
        $service = new TenantProvisioningChecklistService;

        $service->markComplete($schoolA, 'tenant_created');

        $this->assertTrue($service->statusFor($schoolA)['steps']['tenant_created']['completed']);
        $this->assertFalse($service->statusFor($schoolB)['steps']['tenant_created']['completed']);
    }
}
