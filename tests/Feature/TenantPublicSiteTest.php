<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Support\TenantPublicSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantPublicSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['features.website_enabled' => true]);
    }

    public function test_home_shows_registration_portal_when_public_website_disabled(): void
    {
        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Malappuram Sahodaya',
            'subdomain' => 'portalmode',
            'is_active' => true,
        ]);
        TenantPublicSite::setEnabled($sahodaya, false);

        $response = $this->get('http://portalmode.sahodaya.test/');

        $response->assertOk();
        $response->assertSee('School Registration');
        $response->assertSee('School Login');
        $response->assertSee('Register students for Kalotsav');
        $response->assertSee('Malappuram Sahodaya');
    }

    public function test_school_login_page_loads_on_sahodaya_tenant(): void
    {
        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Malappuram Sahodaya',
            'subdomain' => 'schoollogin',
            'is_active' => true,
        ]);

        $this->get('http://schoollogin.sahodaya.test/school-login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tenantName', 'Malappuram Sahodaya')
                ->where('showRegisterLink', true)
            );
    }

    public function test_public_cms_routes_redirect_when_public_website_disabled(): void
    {
        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Malappuram Sahodaya',
            'subdomain' => 'cmsmode',
            'is_active' => true,
        ]);
        TenantPublicSite::setEnabled($sahodaya, false);

        $this->get('http://cmsmode.sahodaya.test/news')
            ->assertRedirect('/');
    }

    public function test_school_register_and_login_remain_available_when_public_website_disabled(): void
    {
        Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Malappuram Sahodaya',
            'subdomain' => 'linkmode',
            'is_active' => true,
        ]);

        $this->get('http://linkmode.sahodaya.test/school-register')->assertOk();
        $this->get('http://linkmode.sahodaya.test/school-login')->assertOk();
        $this->get('http://linkmode.sahodaya.test/login')->assertOk();
    }
}
