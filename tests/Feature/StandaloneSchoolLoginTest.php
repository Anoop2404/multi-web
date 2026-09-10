<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WebsiteSite;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StandaloneSchoolLoginTest extends TestCase
{
    use RefreshDatabase;

    private function createSchool(string $subdomain = 'standalone-school'): Tenant
    {
        return Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Standalone School',
            'subdomain' => $subdomain,
            'parent_id' => null,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);
    }

    public function test_standalone_school_has_its_own_admin_login_with_admin_managed_branding(): void
    {
        $school = $this->createSchool();
        $school->setSetting('contact', [
            'phone' => '0493-3000000',
            'email' => 'office@standalone.test',
        ]);
        $school->setSetting('seo', ['tagline' => 'Learning with purpose']);
        $school->setSetting('site_content', [
            'pages' => [
                'admin_login' => [
                    'intro' => 'Manage every published school update from one secure dashboard.',
                    'submit_label' => 'Open school dashboard',
                ],
            ],
        ]);

        WebsiteSite::create([
            'tenant_id' => $school->id,
            'name' => 'Main website',
            'slug' => 'main',
            'is_primary' => true,
            'is_active' => true,
            'design_json' => [
                'primary' => '#087F5B',
                'secondary' => '#046C4E',
                'accent_color' => '#C92A2A',
            ],
        ]);

        $this->get('http://standalone-school.sahodaya.test/login')
            ->assertRedirect('/school-login');

        $this->get('http://standalone-school.sahodaya.test/school-login?session=expired')
            ->assertOk()
            ->assertSee('content="STANDALONE SCHOOL Administration"', false)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/SchoolLogin', false)
                ->where('tenantName', 'STANDALONE SCHOOL')
                ->where('standalone', true)
                ->where('showRegisterLink', false)
                ->where('phone', '0493-3000000')
                ->where('email', 'office@standalone.test')
                ->where('motto', 'Learning with purpose')
                ->where('content.intro', 'Manage every published school update from one secure dashboard.')
                ->where('content.submit_label', 'Open school dashboard')
                ->where('primaryColor', '#087F5B')
                ->where('secondaryColor', '#046C4E')
                ->where('accentColor', '#C92A2A')
                ->where('sessionExpired', true));
    }

    public function test_standalone_school_portal_landing_has_no_sahodaya_or_event_registration_copy(): void
    {
        $school = $this->createSchool('standalone-landing');
        $school->setSetting('site_content', [
            'branding' => ['subtitle' => 'A CBSE Learning Community'],
            'pages' => [
                'portal_landing' => [
                    'title' => 'Website Management',
                    'intro' => 'Authorised school staff can manage the public website here.',
                    'action_label' => 'Open Administration Login',
                    'action_description' => 'Manage all published school information',
                ],
            ],
        ]);

        $this->get('http://standalone-landing.sahodaya.test/portal')
            ->assertOk()
            ->assertSee('A CBSE Learning Community')
            ->assertSee('Website Management')
            ->assertSee('Authorised school staff can manage the public website here.')
            ->assertSee('Open Administration Login')
            ->assertSee('Manage all published school information')
            ->assertSee('href="/school-login"', false)
            ->assertDontSee('Sahodaya')
            ->assertDontSee('Kalotsav')
            ->assertDontSee('href="/portal/login"', false);
    }

    public function test_standalone_school_admin_can_sign_in_from_the_dedicated_page(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $school = $this->createSchool('standalone-auth');
        $admin = User::factory()->create([
            'tenant_id' => $school->id,
            'email' => 'admin@standalone.test',
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('school_admin');

        $this->withHeader('referer', 'http://standalone-auth.sahodaya.test/school-login')
            ->post('http://standalone-auth.sahodaya.test/login', [
                'email' => 'admin@standalone.test',
                'password' => 'password',
            ])
            ->assertRedirect("/school-admin/{$school->id}");

        $this->assertAuthenticatedAs($admin);
    }
}
