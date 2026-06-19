<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Support\TenantDomainSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_domain_syncs_to_domains_table(): void
    {
        $tenant = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'school',
            'name'      => 'Custom Domain School',
            'domain'    => 'www.custom-school.edu.in',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('domains', [
            'tenant_id' => $tenant->id,
            'domain'    => 'www.custom-school.edu.in',
        ]);
    }

    public function test_subdomain_syncs_slug_to_domains_table(): void
    {
        $tenant = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Malappuram Sahodaya',
            'subdomain' => 'malappuram',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('domains', [
            'tenant_id' => $tenant->id,
            'domain'    => 'malappuram',
        ]);
    }

    public function test_inactive_tenant_has_no_domain_entries(): void
    {
        $tenant = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'school',
            'name'      => 'Pending School',
            'subdomain' => 'pending',
            'is_active' => false,
        ]);

        $this->assertDatabaseMissing('domains', [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_activating_tenant_syncs_domains(): void
    {
        $tenant = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'school',
            'name'      => 'Approved School',
            'subdomain' => 'approved',
            'is_active' => false,
        ]);

        $this->assertDatabaseMissing('domains', ['tenant_id' => $tenant->id]);

        $tenant->update(['is_active' => true]);

        $this->assertDatabaseHas('domains', [
            'tenant_id' => $tenant->id,
            'domain'    => 'approved',
        ]);
    }

    public function test_subdomain_public_site_is_reachable(): void
    {
        Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Travancore Sahodaya',
            'subdomain' => 'travancore',
            'is_active' => true,
        ]);

        $response = $this->get('http://travancore.sahodaya.test/');

        $response->assertOk();
        $response->assertSee('Travancore Sahodaya');
    }

    public function test_sahodaya_homepage_with_sections_renders(): void
    {
        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Malappuram Sahodaya',
            'subdomain' => 'malappuram-test',
            'is_active' => true,
        ]);

        \App\Models\SiteSection::create([
            'tenant_id'     => $sahodaya->id,
            'section_type'  => 'sahodaya_home',
            'variant'       => 'dashboard',
            'display_order' => 1,
            'is_active'     => true,
            'config'        => ['heading' => 'Malappuram Sahodaya'],
        ]);

        \App\Models\TenantSetting::create([
            'tenant_id' => $sahodaya->id,
            'key'       => 'nav_config',
            'value'     => ['layout_variant' => 'sahodaya-modern', 'items' => []],
        ]);

        \App\Models\TenantSetting::create([
            'tenant_id' => $sahodaya->id,
            'key'       => 'theme',
            'value'     => ['primary' => '#5b21b6', 'secondary' => '#7c3aed'],
        ]);

        $response = $this->get('http://malappuram-test.sahodaya.test/');

        $response->assertOk();
        $response->assertSee('Malappuram Sahodaya');
    }

    public function test_custom_domain_sahodaya_portal_is_reachable(): void
    {
        Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Malappuram Sahodaya',
            'domain'    => 'malappuramsahodaya.test',
            'is_active' => true,
        ]);

        $response = $this->get('http://malappuramsahodaya.test/');

        $response->assertOk();
        $response->assertSee('Malappuram Sahodaya');
    }

    public function test_superadmin_login_uses_dedicated_ui_on_central_domain(): void
    {
        $response = $this->get('http://superadmin.test/login');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('appName')
            ->where('appName', config('app.name'))
            ->missing('logoUrl')
            ->missing('tenantName')
        );
    }

    public function test_login_page_shows_tenant_logo_on_subdomain(): void
    {
        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Malappuram Sahodaya',
            'subdomain' => 'malappuram',
            'is_active' => true,
        ]);

        $sahodaya->setSetting('logo', '/images/tenants/malappuram-logo.png');

        $response = $this->get('http://malappuram.sahodaya.test/login');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('logoUrl')
            ->where('logoUrl', '/images/tenants/malappuram-logo.png')
            ->where('tenantName', 'Malappuram Sahodaya')
        );
    }

    public function test_custom_domain_public_site_is_reachable(): void
    {
        Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'school',
            'name'      => 'St Marys School',
            'domain'    => 'stmarys.edu.in',
            'is_active' => true,
        ]);

        $response = $this->get('http://stmarys.edu.in/');

        $response->assertOk();
        $response->assertSee('St Marys School');
    }

    public function test_public_url_helper(): void
    {
        $byDomain = Tenant::make([
            'domain'    => 'school.edu.in',
            'subdomain' => null,
        ]);

        $bySubdomain = Tenant::make([
            'domain'    => null,
            'subdomain' => 'myschool',
        ]);

        $this->assertSame('https://school.edu.in', TenantDomainSync::publicUrl($byDomain));
        $this->assertSame('https://myschool.sahodaya.test', TenantDomainSync::publicUrl($bySubdomain));
    }

    public function test_changing_domain_removes_old_domain_entry(): void
    {
        $tenant = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'school',
            'name'      => 'Changing School',
            'domain'    => 'old-school.test',
            'is_active' => true,
        ]);

        $tenant->update(['domain' => 'new-school.test']);

        $this->assertDatabaseMissing('domains', [
            'tenant_id' => $tenant->id,
            'domain'    => 'old-school.test',
        ]);
        $this->assertDatabaseHas('domains', [
            'tenant_id' => $tenant->id,
            'domain'    => 'new-school.test',
        ]);
    }
}
