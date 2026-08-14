<?php

namespace Tests\Feature;

use App\Models\SiteSection;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebsiteSite;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SahodayaWebsiteSiteScopeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $sahodaya;

    private User $admin;

    private WebsiteSite $primary;

    private WebsiteSite $microsite;

    protected function setUp(): void
    {
        parent::setUp();

        config(['features.website_enabled' => true]);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Scoped Sahodaya',
            'subdomain' => 'scoped-site',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create(['tenant_id' => $this->sahodaya->id]);
        $this->admin->assignRole('sahodaya_admin');

        $this->primary = WebsiteSite::ensurePrimary($this->sahodaya->id);
        $this->microsite = WebsiteSite::create([
            'tenant_id' => $this->sahodaya->id,
            'name' => 'Innovation Expo',
            'slug' => 'innovation-expo',
            'is_primary' => false,
            'is_active' => true,
            'seo_json' => [],
        ]);
    }

    public function test_primary_homepage_and_microsite_render_only_their_own_sections(): void
    {
        $this->createPublishedSection($this->primary, 'PRIMARY WEBSITE MARKER');
        $this->createPublishedSection($this->microsite, 'MICROSITE MARKER');

        $this->get('http://scoped-site.sahodaya.test/')
            ->assertOk()
            ->assertSee('PRIMARY WEBSITE MARKER')
            ->assertDontSee('MICROSITE MARKER');

        $this->get('http://scoped-site.sahodaya.test/m/innovation-expo')
            ->assertOk()
            ->assertSee('MICROSITE MARKER')
            ->assertDontSee('PRIMARY WEBSITE MARKER');
    }

    public function test_builder_lists_only_the_selected_sites_sections(): void
    {
        $primarySection = $this->createPublishedSection($this->primary, 'PRIMARY WEBSITE MARKER');
        $micrositeSection = $this->createPublishedSection($this->microsite, 'MICROSITE MARKER');

        $this->actingAs($this->admin)
            ->getJson("/sahodaya-admin/{$this->sahodaya->id}/site-builder/api/sections?site_id={$this->microsite->id}")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $micrositeSection->id)
            ->assertJsonMissing(['id' => $primarySection->id]);
    }

    public function test_section_mutation_cannot_cross_the_selected_site_boundary(): void
    {
        $primarySection = $this->createPublishedSection($this->primary, 'PRIMARY WEBSITE MARKER');

        $this->actingAs($this->admin)
            ->patchJson(
                "/sahodaya-admin/{$this->sahodaya->id}/site-builder/api/sections/{$primarySection->id}",
                [
                    'site_id' => $this->microsite->id,
                    'config' => ['heading' => 'ILLEGAL CROSS-SITE CHANGE'],
                ],
            )
            ->assertNotFound();

        $this->assertSame(
            'PRIMARY WEBSITE MARKER',
            $primarySection->fresh()->config['heading'],
        );
    }

    public function test_reorder_rejects_ids_from_another_site_without_partial_updates(): void
    {
        $primarySection = $this->createPublishedSection($this->primary, 'PRIMARY WEBSITE MARKER', 7);
        $micrositeSection = $this->createPublishedSection($this->microsite, 'MICROSITE MARKER', 9);

        $this->actingAs($this->admin)
            ->postJson(
                "/sahodaya-admin/{$this->sahodaya->id}/site-builder/api/sections/reorder",
                [
                    'site_id' => $this->primary->id,
                    'ids' => [$primarySection->id, $micrositeSection->id],
                ],
            )
            ->assertStatus(422);

        $this->assertSame(7, $primarySection->fresh()->display_order);
        $this->assertSame(9, $micrositeSection->fresh()->display_order);
    }

    public function test_legacy_cksc_replacement_preserves_microsite_sections(): void
    {
        $oldPrimarySection = $this->createPublishedSection($this->primary, 'OLD PRIMARY WEBSITE MARKER');
        $micrositeSection = $this->createPublishedSection($this->microsite, 'MICROSITE MARKER');

        $this->actingAs($this->admin)
            ->postJson(
                "/sahodaya-admin/{$this->sahodaya->id}/site-builder/api/apply-cksc-template",
                ['site_id' => $this->primary->id, 'replace_sections' => true],
            )
            ->assertOk();

        $this->assertDatabaseHas('site_sections', [
            'id' => $micrositeSection->id,
            'tenant_id' => $this->sahodaya->id,
            'site_id' => $this->microsite->id,
        ]);
        $this->assertDatabaseMissing('site_sections', ['id' => $oldPrimarySection->id]);
    }

    public function test_legacy_cksc_template_is_rejected_for_a_microsite(): void
    {
        $micrositeSection = $this->createPublishedSection($this->microsite, 'MICROSITE MARKER');

        $this->actingAs($this->admin)
            ->postJson(
                "/sahodaya-admin/{$this->sahodaya->id}/site-builder/api/apply-cksc-template",
                ['site_id' => $this->microsite->id, 'replace_sections' => true],
            )
            ->assertStatus(422);

        $this->assertDatabaseHas('site_sections', ['id' => $micrositeSection->id]);
    }

    private function createPublishedSection(WebsiteSite $site, string $heading, int $order = 1): SiteSection
    {
        return SiteSection::create([
            'tenant_id' => $this->sahodaya->id,
            'site_id' => $site->id,
            'section_type' => 'about_sahodaya',
            'variant' => 'single-column',
            'display_order' => $order,
            'is_active' => true,
            'status' => SiteSection::STATUS_PUBLISHED,
            'config' => ['heading' => $heading, 'content' => 'Scoped website content.'],
            'published_config' => ['heading' => $heading, 'content' => 'Scoped website content.'],
            'published_at' => now(),
        ]);
    }
}
