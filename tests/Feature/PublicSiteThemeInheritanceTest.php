<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\WebsiteSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicSiteThemeInheritanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_gallery_inherits_the_primary_school_experience_and_widget_policy(): void
    {
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Responsive Theme School',
            'subdomain' => 'responsive-theme-school',
            'is_active' => true,
        ]);

        $template = config('school_website_templates.al-farooque');

        WebsiteSite::create([
            'tenant_id' => $school->id,
            'name' => 'Main website',
            'slug' => 'main',
            'is_primary' => true,
            'is_active' => true,
            'template_key' => 'al-farooque',
            'template_version' => $template['version'],
            'experience_version' => 'v2',
            'design_json' => $template['design'],
        ]);

        $response = $this->get('http://responsive-theme-school.sahodaya.test/gallery');

        $response->assertOk();
        $response->assertSee('data-experience="al-farooque"', false);
        $response->assertSee('--color-primary: #04906D', false);
        $response->assertSee('--color-accent: #DC3545', false);
        $response->assertSee('site-desktop-navigation hidden xl:flex', false);
        $response->assertSee('site-mobile-toggle xl:hidden', false);
        $response->assertSee('site-mobile-navigation', false);
        $response->assertSee('max-h-[calc(100vh-64px)]', false);
        $response->assertDontSee('bg-gray-900 text-gray-300 text-xs py-1.5 px-4', false);
    }

    public function test_every_school_public_page_renders_with_the_shared_dynamic_layout(): void
    {
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Dynamic Page School',
            'subdomain' => 'dynamic-page-school',
            'is_active' => true,
        ]);

        $template = config('school_website_templates.al-farooque');

        WebsiteSite::create([
            'tenant_id' => $school->id,
            'name' => 'Main website',
            'slug' => 'main',
            'is_primary' => true,
            'is_active' => true,
            'template_key' => 'al-farooque',
            'template_version' => $template['version'],
            'experience_version' => 'v2',
            'design_json' => $template['design'],
        ]);

        foreach (['/', '/about', '/academics', '/admissions', '/disclosure', '/contact', '/news', '/events', '/gallery', '/results', '/admission-enquiry'] as $path) {
            $this->get("http://dynamic-page-school.sahodaya.test{$path}")
                ->assertOk();
        }
    }
}
