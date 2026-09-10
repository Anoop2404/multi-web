<?php

namespace Tests\Feature\SchoolAdmin;

use App\Models\GalleryAlbum;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebsiteSite;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DynamicPublicWebsiteContentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $school;

    private User $admin;

    private WebsiteSite $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Dynamic School',
            'subdomain' => 'dynamic-school',
            'membership_status' => 'approved',
            'is_active' => true,
        ]);
        $this->school->setSetting('public_website_enabled', ['enabled' => true]);

        $this->admin = User::factory()->create([
            'tenant_id' => $this->school->id,
            'email_verified_at' => now(),
        ]);
        $this->admin->assignRole('school_admin');

        $template = config('school_website_templates.al-farooque');
        $this->site = WebsiteSite::create([
            'tenant_id' => $this->school->id,
            'name' => 'Main website',
            'slug' => 'main',
            'is_primary' => true,
            'is_active' => true,
            'template_key' => 'al-farooque',
            'template_version' => $template['version'],
            'experience_version' => 'v2',
            'design_json' => $template['design'],
        ]);
    }

    public function test_admin_settings_drive_public_theme_navigation_footer_and_page_text(): void
    {
        $api = "/school-admin/{$this->school->id}/site-builder/api";

        $this->actingAs($this->admin)->postJson("{$api}/design", [
            'site_id' => $this->site->id,
            'primary' => '#087F5B',
            'secondary' => '#A61E4D',
            'accent_color' => '#D9480F',
            'text_color' => '#212529',
            'page_background' => '#FFFDF8',
            'muted_surface' => '#F3FAF7',
            'hero_background' => '#163A2F',
            'navbar_background' => '#FFFFFF',
            'footer_background' => '#12372A',
            'footer_text_color' => '#E6FCF5',
            'display_font' => 'Manrope',
            'body_font' => 'Inter',
            'type_scale' => 'balanced',
            'density' => 'comfortable',
            'surface' => 'soft',
            'corners' => 'soft',
            'buttons' => 'solid',
            'images' => 'documentary',
            'motion' => 'restrained',
            'navigation' => 'logo-left',
            'footer' => 'three-column',
        ])->assertOk()->assertJsonPath('saved', true);

        $this->actingAs($this->admin)->postJson("{$api}/nav", [
            'layout_variant' => 'logo-left',
            'items' => [
                [
                    'label' => 'Discover',
                    'url' => '/about',
                    'external' => false,
                    'children' => [
                        ['label' => 'Our Story', 'url' => '/about', 'external' => false],
                    ],
                ],
            ],
            'portal_cta' => [
                'cbse_btn' => true,
                'cbse_label' => 'CBSE Details',
                'cbse_url' => '/disclosure',
                'contact_btn' => true,
                'contact_label' => 'Talk to Us',
                'contact_url' => '/contact',
                'show_in_navbar' => false,
                'show_in_menu' => false,
            ],
        ])->assertOk()->assertJsonPath('saved', true);

        $this->actingAs($this->admin)->postJson("{$api}/footer", [
            'layout_variant' => 'three-column',
            'tagline' => 'Learning with purpose.',
            'copyright' => 'Dynamic School copyright',
            'phone' => '0493-3000000',
            'email' => 'office@dynamic-school.test',
            'address' => 'Dynamic School Campus',
            'quick_links_heading' => 'Explore',
            'contact_heading' => 'Reach Us',
            'quick_links' => [
                ['label' => 'Admissions Desk', 'url' => '/admissions'],
            ],
            'include_portal_links' => false,
        ])->assertOk()->assertJsonPath('saved', true);

        $this->actingAs($this->admin)->postJson("{$api}/site-content", [
            'site_id' => $this->site->id,
            'branding' => ['subtitle' => 'A CBSE Learning Community'],
            'common' => ['back_to_home' => 'Return home'],
            'pages' => [
                'gallery' => [
                    'title' => 'Campus Stories',
                    'empty_title' => 'New stories are on the way',
                    'empty_description' => 'Please visit again soon.',
                    'empty_album_message' => 'Photos will be added soon.',
                    'photo_singular' => 'picture',
                    'photo_plural' => 'pictures',
                    'seo_title' => 'Campus Stories | {{school_name}}',
                    'seo_description' => 'Life at {{ school_name }}.',
                ],
                'admin_login' => [
                    'intro' => 'Manage Dynamic School website content from one secure dashboard.',
                    'submit_label' => 'Open Dynamic School dashboard',
                ],
                'portal_landing' => [
                    'title' => 'Dynamic School Website Access',
                    'action_label' => 'School Administration',
                    'action_description' => 'Manage the complete public website',
                ],
            ],
        ])->assertOk()->assertJsonPath('saved', true);

        $this->site->refresh();
        $this->assertSame('#087F5B', $this->site->design_json['primary']);
        $this->assertSame('Discover', $this->school->fresh()->getSetting('nav_config')['items'][0]['label']);
        $this->assertSame('Reach Us', $this->school->fresh()->getSetting('footer_config')['contact_heading']);
        $this->assertSame('Campus Stories', $this->school->fresh()->getSetting('site_content')['pages']['gallery']['title']);
        $this->assertSame('Open Dynamic School dashboard', $this->school->fresh()->getSetting('site_content')['pages']['admin_login']['submit_label']);

        $this->get('http://dynamic-school.sahodaya.test/gallery')
            ->assertOk()
            ->assertSee('<title>Campus Stories | DYNAMIC SCHOOL</title>', false)
            ->assertSee('--color-primary: #087F5B', false)
            ->assertSee('--site-footer-bg: #12372A', false)
            ->assertSee('A CBSE Learning Community')
            ->assertSee('Discover')
            ->assertSee('Our Story')
            ->assertSee('CBSE Details')
            ->assertSee('Talk to Us')
            ->assertSee('Campus Stories')
            ->assertSee('New stories are on the way')
            ->assertSee('Learning with purpose.')
            ->assertSee('Explore')
            ->assertSee('Reach Us')
            ->assertSee('office@dynamic-school.test')
            ->assertSee('Dynamic School Campus');

        $this->get('http://dynamic-school.sahodaya.test/school-login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/SchoolLogin', false)
                ->where('standalone', true)
                ->where('content.intro', 'Manage Dynamic School website content from one secure dashboard.')
                ->where('content.submit_label', 'Open Dynamic School dashboard')
                ->where('primaryColor', '#087F5B')
                ->where('secondaryColor', '#A61E4D')
                ->where('accentColor', '#D9480F'));

        $this->get('http://dynamic-school.sahodaya.test/portal')
            ->assertOk()
            ->assertSee('Dynamic School Website Access')
            ->assertSee('School Administration')
            ->assertSee('Manage the complete public website')
            ->assertDontSee('Sahodaya');
    }

    public function test_album_and_uploaded_cover_created_in_admin_are_visible_on_the_public_gallery(): void
    {
        Storage::fake('shared');
        config()->set('filesystems.upload_disk', 'shared');

        $cover = UploadedFile::fake()->image('campus-day.jpg', 1200, 800);

        $this->actingAs($this->admin)->post(
            "/school-admin/{$this->school->id}/gallery/albums",
            [
                'title' => 'Campus Day',
                'description' => 'Learning, celebration and community moments.',
                'cover_image' => $cover,
            ],
        )->assertRedirect()->assertSessionHasNoErrors();

        $album = GalleryAlbum::where('tenant_id', $this->school->id)->firstOrFail();
        Storage::disk('shared')->assertExists($album->cover_image);

        $this->get('http://dynamic-school.sahodaya.test/gallery')
            ->assertOk()
            ->assertSee('Campus Day')
            ->assertSee('Learning, celebration and community moments.')
            ->assertSee("/gallery/albums/{$album->id}/cover", false);

        $this->get("http://dynamic-school.sahodaya.test/gallery/albums/{$album->id}/cover")
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');
    }

    public function test_school_admin_can_replace_and_clear_public_contact_widget_and_seo_values(): void
    {
        $this->school->setSetting('widgets', [
            'whatsapp_number' => '+919876543210',
            'cbse_affiliation_number' => '1130123',
            'cbse_badge_show' => true,
            'social_links' => [
                'facebook' => 'https://facebook.com/placeholder',
                'youtube' => 'https://youtube.com/placeholder',
            ],
        ]);

        $this->actingAs($this->admin)->post("/school-admin/{$this->school->id}/settings", [
            'phone' => '0493-3242081',
            'email' => 'info@dynamic-school.test',
            'address' => 'School Campus, Kerala',
            'address_city' => '',
            'facebook' => '',
            'youtube' => '',
            'instagram' => '',
            'whatsapp_number' => '',
            'cbse_affiliation_number' => '930222',
            'cbse_badge_show' => true,
            'seo_title' => 'Dynamic School',
            'seo_description' => '',
            'seo_keywords' => '',
            'seo_tagline' => 'Learning with purpose.',
            'locale' => 'en',
            'public_website_enabled' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $school = $this->school->fresh();

        $this->assertSame([
            'phone' => '0493-3242081',
            'email' => 'info@dynamic-school.test',
            'address' => 'School Campus, Kerala',
        ], $school->getSetting('contact'));
        $this->assertNull($school->getSetting('address_city'));
        $this->assertNull($school->getWidgets()['whatsapp_number']);
        $this->assertSame('930222', $school->getWidgets()['cbse_affiliation_number']);
        $this->assertNull($school->getWidgets()['social_links']['facebook']);
        $this->assertNull($school->getSetting('seo')['description']);

        $this->actingAs($this->admin)
            ->get("/school-admin/{$this->school->id}/settings")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('settings.contact.phone', '0493-3242081')
                ->where('settings.contact.email', 'info@dynamic-school.test')
                ->where('settings.widgets.whatsapp_number', null)
                ->where('settings.widgets.cbse_affiliation_number', '930222')
                ->where('settings.seo.description', null));
    }
}
