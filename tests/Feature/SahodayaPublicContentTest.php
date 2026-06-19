<?php

namespace Tests\Feature;

use App\Models\SahodayaProfile;
use App\Models\SiteSection;
use App\Models\Tenant;
use App\Support\SahodayaHomepageContent;
use App\Support\SectionFieldRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SahodayaPublicContentTest extends TestCase
{
    use RefreshDatabase;

    private function sahodaya(): Tenant
    {
        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Test Sahodaya',
            'domain'    => 'test-sahodaya.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create(['tenant_id' => $sahodaya->id]);

        return $sahodaya;
    }

    public function test_homepage_content_creates_section_on_first_access(): void
    {
        $sahodaya = $this->sahodaya();

        $content = SahodayaHomepageContent::get($sahodaya);

        $this->assertSame('Test Sahodaya', $content['heading']);
        $this->assertDatabaseHas('site_sections', [
            'tenant_id'    => $sahodaya->id,
            'section_type' => 'sahodaya_home',
            'variant'      => 'dashboard',
        ]);
    }

    public function test_homepage_content_update_persists_announcements_programmes_and_years(): void
    {
        $sahodaya = $this->sahodaya();

        SahodayaHomepageContent::update($sahodaya, [
            'phone'         => '9876543210',
            'email'         => 'office@test.org',
            'address'       => 'Sahodaya Office, Kerala',
            'announcements' => [
                ['title' => 'Kalotsav 2025', 'url' => '/events', 'date' => 'Jun 2025', 'badge' => 'News'],
            ],
            'programmes' => [
                ['label' => 'Kalotsav', 'description' => 'Arts fest', 'url' => '#academic', 'icon' => '🏆'],
            ],
            'years' => [
                [
                    'year'  => '2025-26',
                    'links' => [
                        ['label' => 'Kids Fest', 'url' => 'https://example.com', 'icon' => '🎨'],
                    ],
                ],
            ],
            'links' => [
                ['label' => 'CBSE', 'url' => 'https://cbse.gov.in', 'icon' => '🏛️'],
            ],
        ]);

        $section = SiteSection::where('tenant_id', $sahodaya->id)->first();
        $config  = $section->config;

        $this->assertSame('9876543210', $config['phone']);
        $this->assertCount(1, $config['announcements']);
        $this->assertSame('Kalotsav 2025', $config['announcements'][0]['title']);
        $this->assertCount(1, $config['programmes']);
        $this->assertSame('2025-26', $config['years'][0]['year']);
        $this->assertSame('Kids Fest', $config['years'][0]['links'][0]['label']);

        $profile = SahodayaProfile::where('tenant_id', $sahodaya->id)->first();
        $this->assertSame('9876543210', $profile->contact_phone);
        $this->assertSame('office@test.org', $profile->contact_email);
    }

    public function test_section_field_registry_returns_sahodaya_home_fields(): void
    {
        $fields = SectionFieldRegistry::fields('sahodaya_home', 'dashboard');
        $keys   = array_column($fields, 'key');

        $this->assertContains('announcements', $keys);
        $this->assertContains('programmes', $keys);
        $this->assertContains('years', $keys);
        $this->assertContains('phone', $keys);
    }
}
