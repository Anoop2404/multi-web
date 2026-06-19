<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Support\TenantStorage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantStorageTest extends TestCase
{
    public function test_resolves_tenant_suffixed_public_storage_path(): void
    {
        $school = new Tenant(['id' => (string) Str::uuid()]);
        $relative = "payments/{$school->id}/proof.png";

        $tenantDir = base_path('storage/tenant'.$school->id.'/app/public/'.$relative);
        @mkdir(dirname($tenantDir), 0777, true);
        file_put_contents($tenantDir, 'proof');

        $this->assertSame($tenantDir, TenantStorage::publicFilePath($school, $relative));

        @unlink($tenantDir);
    }

    public function test_resolves_shared_storage_path(): void
    {
        $school = new Tenant(['id' => (string) Str::uuid()]);
        $relative = "payments/{$school->id}/proof.png";

        $sharedPath = base_path('storage/app/shared/'.$relative);
        @mkdir(dirname($sharedPath), 0777, true);
        file_put_contents($sharedPath, 'proof');

        $this->assertSame($sharedPath, TenantStorage::publicFilePath($school, $relative));

        @unlink($sharedPath);
    }

    public function test_resolves_tenant_file_while_sahodaya_tenancy_is_active(): void
    {
        $sahodaya = new Tenant(['id' => (string) Str::uuid(), 'type' => 'sahodaya']);
        $school = new Tenant(['id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id]);
        $relative = "payments/{$school->id}/proof.png";

        $tenantDir = base_path('storage/tenant'.$school->id.'/app/public/'.$relative);
        @mkdir(dirname($tenantDir), 0777, true);
        file_put_contents($tenantDir, 'proof');

        if (function_exists('tenancy') && class_exists(\Stancl\Tenancy\Tenancy::class)) {
            tenancy()->initialize($sahodaya);
        }

        $this->assertSame($tenantDir, TenantStorage::publicFilePath($school, $relative));

        @unlink($tenantDir);
    }
}
