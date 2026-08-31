<?php

namespace Tests\Feature;

use App\Models\SiteSection;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebsiteSite;
use App\Models\FestEvent;
use App\Services\Website\SahodayaContentReadiness;
use App\Services\Website\SahodayaHomepageModeResolver;
use App\Support\SahodayaWebsiteTemplateCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SahodayaWebsiteV2Test extends TestCase
{
    use RefreshDatabase;

    private Tenant $sahodaya;
    private User $admin;
    private User $superadmin;
    private WebsiteSite $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'V2 Sahodaya',
            'subdomain' => 'v2-site', 'is_active' => true,
        ]);
        $this->sahodaya->setSetting('logo', '/images/test-logo.png');
        $this->sahodaya->setSetting('footer_config', ['email' => 'office@example.test']);
        $this->admin = User::factory()->create(['tenant_id' => $this->sahodaya->id]);
        $this->admin->assignRole('sahodaya_admin');
        // Applying/publishing/restoring a template is platform-assigned (superadmin
        // only) — see SiteBuilderApiController::assertSuperAdmin().
        $this->superadmin = User::factory()->create(['tenant_id' => null, 'email_verified_at' => now()]);
        $this->superadmin->assignRole('superadmin');
        $this->site = WebsiteSite::ensurePrimary($this->sahodaya->id);

        SiteSection::create([
            'tenant_id' => $this->sahodaya->id, 'site_id' => $this->site->id,
            'section_type' => 'about_sahodaya', 'variant' => 'single-column',
            'display_order' => 1, 'is_active' => true, 'status' => SiteSection::STATUS_PUBLISHED,
            'config' => ['heading' => 'ORIGINAL LIVE WEBSITE', 'content' => 'Published content'],
            'published_config' => ['heading' => 'ORIGINAL LIVE WEBSITE', 'content' => 'Published content'],
            'published_at' => now(),
        ]);
    }

    public function test_all_experience_manifests_are_valid(): void
    {
        $templates = SahodayaWebsiteTemplateCatalog::summaries();

        $this->assertCount(6, $templates);
        $this->assertEqualsCanonicalizing(
            ['network-directory', 'events-results-live', 'academic-resources', 'confederation-governance', 'sahodaya-premium', 'heritage-institutional'],
            collect($templates)->pluck('key')->all(),
        );
    }

    public function test_heritage_institutional_template_applies_and_renders_its_brand_colors(): void
    {
        $this->actingAs($this->superadmin)->postJson($this->api('/experience/draft'), [
            'site_id' => $this->site->id, 'template_key' => 'heritage-institutional',
        ])->assertOk()->assertJsonPath('draft.template_key', 'heritage-institutional');

        $this->actingAs($this->superadmin)->postJson($this->api('/experience/publish'), ['site_id' => $this->site->id])
            ->assertOk()->assertJsonPath('site.experience_version', 'v2');

        $site = $this->site->fresh();
        $this->assertSame('heritage-institutional', $site->template_key);
        $this->assertCount(13, $site->sectionQuery()->get());

        $this->get('http://v2-site.sahodaya.test/')
            ->assertOk()
            ->assertSee('--color-primary: #7A0D11', false)
            ->assertSee('--color-accent: #E09A00', false)
            ->assertSee('Our network at a glance')
            ->assertSee('Frequently asked questions');
    }

    public function test_classic_site_does_not_receive_v2_section_width_constraints(): void
    {
        $this->get('http://v2-site.sahodaya.test/')
            ->assertOk()
            ->assertSee('legacy-site-section', false)
            ->assertDontSee('class="site-section-frame', false);
    }

    public function test_experience_is_previewable_as_a_draft_without_changing_live_content(): void
    {
        $this->actingAs($this->superadmin)->postJson($this->api('/experience/draft'), [
            'site_id' => $this->site->id,
            'template_key' => 'events-results-live',
            'mode' => 'full',
        ])->assertOk()->assertJsonPath('draft.template_key', 'events-results-live');

        $this->assertDatabaseHas('site_sections', ['site_id' => $this->site->id, 'section_type' => 'about_sahodaya']);
        $this->get('http://v2-site.sahodaya.test/')->assertSee('ORIGINAL LIVE WEBSITE')->assertDontSee('What do you need today?');
        $this->actingAs($this->admin)->get('http://v2-site.sahodaya.test/preview-site?site_id='.$this->site->id)
            ->assertOk()->assertSee('What do you need today?')->assertDontSee('ORIGINAL LIVE WEBSITE');
    }

    public function test_draft_can_be_cancelled_without_touching_live_site(): void
    {
        $this->actingAs($this->superadmin)->postJson($this->api('/experience/draft'), [
            'site_id' => $this->site->id, 'template_key' => 'network-directory',
        ])->assertOk();

        $this->actingAs($this->superadmin)->postJson($this->api('/experience/cancel'), ['site_id' => $this->site->id])->assertOk();

        $this->assertNull($this->site->fresh()->draft_template_json);
        $this->get('http://v2-site.sahodaya.test/')->assertSee('ORIGINAL LIVE WEBSITE');
    }

    public function test_style_only_publish_preserves_section_identity_and_unpublished_content(): void
    {
        $section = $this->site->sectionQuery()->firstOrFail();
        $section->update(['config' => ['heading' => 'UNPUBLISHED EDIT'], 'status' => SiteSection::STATUS_DRAFT]);

        $this->actingAs($this->superadmin)->postJson($this->api('/experience/draft'), [
            'site_id' => $this->site->id, 'template_key' => 'academic-resources', 'mode' => 'style',
        ])->assertOk();
        $this->actingAs($this->superadmin)->postJson($this->api('/experience/publish'), ['site_id' => $this->site->id])->assertOk();

        $sameSection = SiteSection::findOrFail($section->id);
        $this->assertSame('UNPUBLISHED EDIT', $sameSection->config['heading']);
        $this->assertSame('ORIGINAL LIVE WEBSITE', $sameSection->publicConfig()['heading']);
        $this->get('http://v2-site.sahodaya.test/')->assertSee('ORIGINAL LIVE WEBSITE')->assertDontSee('UNPUBLISHED EDIT');
    }

    public function test_publishing_a_ready_draft_is_atomic_and_creates_a_restore_point(): void
    {
        $this->actingAs($this->superadmin)->postJson($this->api('/experience/draft'), [
            'site_id' => $this->site->id, 'template_key' => 'events-results-live',
        ])->assertOk();

        $this->actingAs($this->superadmin)->postJson($this->api('/experience/publish'), ['site_id' => $this->site->id])
            ->assertOk()->assertJsonPath('site.experience_version', 'v2');

        $site = $this->site->fresh();
        $this->assertSame('events-results-live', $site->template_key);
        $this->assertNull($site->draft_template_json);
        $this->assertCount(8, $site->sectionQuery()->get());
        $this->assertDatabaseCount('website_site_versions', 1);
        $this->get('http://v2-site.sahodaya.test/')->assertOk()->assertSee('What do you need today?')->assertSee('site-section-surface-canvas', false)->assertDontSee('ORIGINAL LIVE WEBSITE');
    }

    public function test_restore_point_recovers_content_and_layout_together(): void
    {
        $this->actingAs($this->superadmin)->postJson($this->api('/experience/draft'), [
            'site_id' => $this->site->id, 'template_key' => 'network-directory',
        ])->assertOk();
        $this->actingAs($this->superadmin)->postJson($this->api('/experience/publish'), ['site_id' => $this->site->id])->assertOk();

        $versionId = $this->site->versions()->firstOrFail()->id;
        $this->actingAs($this->superadmin)->postJson($this->api("/experience/versions/{$versionId}/restore"), ['site_id' => $this->site->id])
            ->assertOk()->assertJsonPath('site.experience_version', 'v1');

        $restored = $this->site->fresh()->sectionQuery()->firstOrFail();
        $this->assertSame('ORIGINAL LIVE WEBSITE', $restored->publicConfig()['heading']);
        $this->assertSame([], $restored->publicLayout());
        $this->get('http://v2-site.sahodaya.test/')->assertSee('ORIGINAL LIVE WEBSITE');
    }

    public function test_homepage_mode_follows_event_lifecycle_without_a_live_override(): void
    {
        FestEvent::create([
            'tenant_id' => $this->sahodaya->id, 'title' => 'Arts Festival', 'event_type' => 'kalolsavam',
            'status' => 'registration_open', 'event_start' => now()->addWeek(),
            'results_published' => false,
        ]);

        $this->assertSame('registration_open', app(SahodayaHomepageModeResolver::class)->resolve($this->site->fresh()));

        $this->site->update(['homepage_mode' => 'event_live', 'homepage_mode_override_until' => now()->addDay()]);
        $this->assertSame('event_live', app(SahodayaHomepageModeResolver::class)->resolve($this->site->fresh()));
    }

    public function test_readiness_blocks_placeholder_content(): void
    {
        $section = $this->site->sectionQuery()->firstOrFail();
        $section->update(['config' => ['heading' => 'Your school name', 'content' => 'Lorem ipsum']]);

        $report = app(SahodayaContentReadiness::class)->inspect($this->sahodaya, $this->site->fresh());

        $this->assertFalse($report['ready']);
        $this->assertStringContainsString('placeholder', implode(' ', $report['errors']));
    }

    private function api(string $path): string
    {
        return "/sahodaya-admin/{$this->sahodaya->id}/site-builder/api{$path}";
    }
}
