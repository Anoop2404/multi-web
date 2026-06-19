<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Support\SahodayaPublicData;
use App\Support\SahodayaSiteTemplate;
use Database\Seeders\SahodayaMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SahodayaPublicSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_sahodaya_site_template_seeds_modern_layout(): void
    {
        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Malappuram Sahodaya',
            'domain'    => 'malappuram-sahodaya.test',
            'is_active' => true,
        ]);

        \App\Models\SahodayaProfile::create(['tenant_id' => $sahodaya->id]);
        SahodayaSiteTemplate::apply($sahodaya);

        $this->assertDatabaseHas('site_sections', [
            'tenant_id'    => $sahodaya->id,
            'section_type' => 'sahodaya_home',
            'variant'      => 'dashboard',
        ]);

        $nav = $sahodaya->settings()->where('key', 'nav_config')->first()?->value;
        $this->assertSame('sahodaya-modern', $nav['layout_variant'] ?? null);
        $this->assertGreaterThan(0, $sahodaya->officeBearers()->count());
    }

    public function test_academic_years_returns_default_structure(): void
    {
        $years = SahodayaPublicData::academicYears([]);

        $this->assertNotEmpty($years);
        $this->assertArrayHasKey('year', $years[0]);
        $this->assertNotEmpty($years[0]['links']);
        $this->assertCount(3, $years);
    }

    public function test_programmes_and_motto_defaults(): void
    {
        $programmes = SahodayaPublicData::programmes([]);
        $this->assertGreaterThanOrEqual(6, count($programmes));
        $this->assertSame('Kalotsav', $programmes[0]['label']);

        $motto = SahodayaPublicData::motto([]);
        $this->assertStringContainsString('Caring and Sharing', $motto);

        $links = SahodayaPublicData::usefulLinks([]);
        $labels = array_column($links, 'label');
        $this->assertContains('CKSC Confederation', $labels);
    }
}
