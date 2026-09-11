<?php

namespace Tests\Feature\SchoolAdmin;

use App\Models\Alumni;
use App\Models\Download;
use App\Models\GalleryAlbum;
use App\Models\SiteForm;
use App\Models\SiteFormSubmission;
use App\Models\SiteSection;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\WebsiteSite;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
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

    public function test_content_added_in_school_admin_reaches_the_published_website(): void
    {
        $this->publishAlFarooqueTemplate();

        $this->actingAs($this->admin)->post("/school-admin/{$this->school->id}/news", [
            'title' => 'Science Fair Winners',
            'body' => 'Our students presented award-winning projects.',
            'category' => 'Campus',
            'is_featured' => true,
            'published_at' => now()->subMinute()->toDateTimeString(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post("/school-admin/{$this->school->id}/events", [
            'title' => 'Annual Sports Day',
            'description' => 'Athletics and team events.',
            'start_date' => now()->addWeek()->toDateString(),
            'venue' => 'School Ground',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post("/school-admin/{$this->school->id}/staff", [
            'name' => 'Anitha Teacher',
            'designation' => 'Science Teacher',
            'department' => 'Science',
            'qualification' => 'MSc, BEd',
            'type' => 'teaching',
            'display_order' => 1,
            'is_active' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post("/school-admin/{$this->school->id}/achievements", [
            'title' => 'District Quiz Champions',
            'description' => 'First place in the district quiz.',
            'category' => 'academic',
            'level' => 'district',
            'academic_year' => '2026-27',
            'achieved_at' => now()->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post("/school-admin/{$this->school->id}/testimonials", [
            'name' => 'Amina Parent',
            'designation' => 'Class VII Parent',
            'quote' => 'The teachers communicate clearly and care for every child.',
            'rating' => 4,
            'display_order' => 1,
            'is_active' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->get('http://dynamic-school.sahodaya.test/')
            ->assertOk()
            ->assertSee('Science Fair Winners')
            ->assertSee('Anitha Teacher')
            ->assertSee('District Quiz Champions')
            ->assertSee('Amina Parent')
            ->assertSee('The teachers communicate clearly and care for every child.')
            ->assertDontSee('Our child has grown so much in confidence');

        $this->get('http://dynamic-school.sahodaya.test/events')
            ->assertOk()
            ->assertSee('Annual Sports Day')
            ->assertSee('School Ground');

        $this->get('http://dynamic-school.sahodaya.test/faculty')
            ->assertOk()
            ->assertSee('Anitha Teacher')
            ->assertSee('Science Teacher')
            ->assertSee('href="/faculty"', false);

        $this->get('http://dynamic-school.sahodaya.test/about')
            ->assertOk()
            ->assertSee('id="principal-message"', false)
            ->assertSee('id="facilities"', false);

        $testimonial = Testimonial::where('tenant_id', $this->school->id)->firstOrFail();
        $this->actingAs($this->admin)->put("/school-admin/{$this->school->id}/testimonials/{$testimonial->id}", [
            'name' => 'Amina Parent',
            'designation' => 'Class VII Parent',
            'quote' => 'This hidden message must not appear.',
            'rating' => 4,
            'display_order' => 1,
            'is_active' => false,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->get('http://dynamic-school.sahodaya.test/')
            ->assertOk()
            ->assertDontSee('This hidden message must not appear.')
            ->assertDontSee('Our child has grown so much in confidence');
    }

    public function test_school_admin_can_manage_contact_form_fields_and_read_public_submissions(): void
    {
        Mail::fake();
        $this->publishAlFarooqueTemplate();

        $this->actingAs($this->admin)
            ->get("/school-admin/{$this->school->id}/website/forms")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('School/Website/Forms', false)
                ->where('forms.0.slug', 'contact'));

        $form = SiteForm::where('tenant_id', $this->school->id)->where('slug', 'contact')->firstOrFail();

        $this->actingAs($this->admin)->put("/school-admin/{$this->school->id}/website/forms/{$form->id}", [
            'name' => 'Contact our school',
            'notify_email' => 'office@dynamic-school.test',
            'success_message' => 'Your message has reached our school office.',
            'is_active' => true,
            'honeypot_enabled' => true,
            'fields_json' => [
                ['key' => 'name', 'label' => 'Your full name', 'type' => 'text', 'placeholder' => 'Enter your name', 'required' => true],
                ['key' => 'email', 'label' => 'Reply email', 'type' => 'email', 'placeholder' => 'you@example.com', 'required' => true],
                ['key' => 'message', 'label' => 'How can we help?', 'type' => 'textarea', 'placeholder' => 'Write your message', 'required' => true],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->get('http://dynamic-school.sahodaya.test/contact')
            ->assertOk()
            ->assertSee('Your full name')
            ->assertSee('Reply email')
            ->assertSee('How can we help?')
            ->assertSee('Enter your name')
            ->assertDontSee('Secretariat');

        $this->from('http://dynamic-school.sahodaya.test/contact')
            ->post('http://dynamic-school.sahodaya.test/forms/contact', [
                'name' => 'Fathima Parent',
                'email' => 'fathima@example.com',
                'message' => 'Please share the admission visit timings.',
            ])
            ->assertRedirect('http://dynamic-school.sahodaya.test/contact')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Your message has reached our school office.');

        $submission = SiteFormSubmission::where('site_form_id', $form->id)->firstOrFail();
        $this->assertSame('Fathima Parent', $submission->payload_json['name']);
        $this->assertSame('Please share the admission visit timings.', $submission->payload_json['message']);
        $this->assertFalse($submission->is_spam);

        $this->actingAs($this->admin)
            ->get("/school-admin/{$this->school->id}/website/forms/{$form->id}/submissions")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('School/Website/FormSubmissions', false)
                ->where('submissions.0.payload_json.name', 'Fathima Parent')
                ->where('submissions.0.payload_json.message', 'Please share the admission visit timings.'));

    }

    public function test_section_editor_changes_publish_and_sahodaya_only_variants_are_rejected(): void
    {
        $this->publishAlFarooqueTemplate();
        $api = "/school-admin/{$this->school->id}/site-builder/api";

        $academic = SiteSection::query()
            ->where('tenant_id', $this->school->id)
            ->where('section_type', 'academic_programmes')
            ->firstOrFail();

        $config = $academic->config;
        $config['heading'] = 'Learning Pathways 2026';

        $this->actingAs($this->admin)->patchJson("{$api}/sections/{$academic->id}", [
            'site_id' => $this->site->id,
            'config' => $config,
        ])->assertOk()->assertJsonPath('status', 'draft');

        $this->get('http://dynamic-school.sahodaya.test/')
            ->assertOk()
            ->assertDontSee('Learning Pathways 2026');

        $this->actingAs($this->admin)->postJson("{$api}/sections/{$academic->id}/publish", [
            'site_id' => $this->site->id,
        ])->assertOk()->assertJsonPath('status', 'published');

        $this->get('http://dynamic-school.sahodaya.test/')
            ->assertOk()
            ->assertSee('Learning Pathways 2026');

        $this->actingAs($this->admin)->postJson("{$api}/sections", [
            'site_id' => $this->site->id,
            'section_type' => 'hero',
            'variant' => 'gradient-split',
            'config' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('section_type');

        $this->actingAs($this->admin)->postJson("{$api}/sections", [
            'site_id' => $this->site->id,
            'section_type' => 'contact',
            'variant' => 'side-by-side',
            'config' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('section_type');
    }

    public function test_downloads_vacancies_alumni_and_admission_enquiries_work_end_to_end(): void
    {
        Storage::fake('shared');
        config()->set('filesystems.upload_disk', 'shared');
        $api = "/school-admin/{$this->school->id}/site-builder/api";

        $this->addPublishedSection('downloads', 'card-grid', ['heading' => 'Parent Downloads']);
        $this->addPublishedSection('job_vacancies', 'listing', ['heading' => 'Work With Us']);
        $this->addPublishedSection('alumni', 'featured-grid', ['heading' => 'Alumni Stories']);

        $this->actingAs($this->admin)->postJson("{$api}/site-content", [
            'site_id' => $this->site->id,
            'pages' => [
                'downloads' => ['title' => 'School Documents', 'eyebrow' => 'Useful Files', 'subheading' => 'Download current school resources.'],
                'careers' => ['title' => 'Current Openings', 'eyebrow' => 'Careers', 'subheading' => 'Join our teaching team.'],
                'alumni' => ['title' => 'Our Alumni Network', 'eyebrow' => 'Stay Connected', 'subheading' => 'Reconnect with classmates and teachers.'],
            ],
        ])->assertOk()->assertJsonPath('saved', true);

        $this->actingAs($this->admin)->post("/school-admin/{$this->school->id}/downloads", [
            'title' => 'Academic Calendar 2026',
            'category' => 'calendar',
            'academic_year' => '2026-27',
            'is_active' => true,
            'file' => UploadedFile::fake()->create('calendar.pdf', 80, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $download = Download::where('tenant_id', $this->school->id)->firstOrFail();
        Storage::disk('shared')->assertExists($download->file_path);

        $this->get('http://dynamic-school.sahodaya.test/downloads')
            ->assertOk()
            ->assertSee('School Documents')
            ->assertSee('Useful Files')
            ->assertSee('Academic Calendar 2026')
            ->assertSee("/downloads/{$download->id}/file", false);

        $this->get("http://dynamic-school.sahodaya.test/downloads/{$download->id}/file")
            ->assertOk();

        $this->actingAs($this->admin)->post("/school-admin/{$this->school->id}/job-vacancies", [
            'title' => 'Primary English Teacher',
            'description' => 'Lead engaging English lessons.',
            'qualification' => 'BA English, BEd',
            'experience' => 'Two years preferred',
            'last_date' => now()->addMonth()->toDateString(),
            'apply_email' => 'careers@dynamic-school.test',
            'is_active' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->get('http://dynamic-school.sahodaya.test/careers')
            ->assertOk()
            ->assertSee('Current Openings')
            ->assertSee('Primary English Teacher')
            ->assertSee('careers@dynamic-school.test');

        $this->from('http://dynamic-school.sahodaya.test/alumni')
            ->post('http://dynamic-school.sahodaya.test/alumni-register', [
                'name' => 'Nihal Alumnus',
                'batch_year' => 2018,
                'email' => 'nihal@example.com',
                'phone' => '9876543210',
                'current_role' => 'Engineer',
                'message' => 'Happy to reconnect.',
            ])->assertRedirect()->assertSessionHasNoErrors();

        $alumnus = Alumni::where('tenant_id', $this->school->id)->firstOrFail();
        $this->actingAs($this->admin)->patch("/school-admin/{$this->school->id}/alumni/{$alumnus->id}/approve")
            ->assertRedirect();
        $this->actingAs($this->admin)->patch("/school-admin/{$this->school->id}/alumni/{$alumnus->id}/feature")
            ->assertRedirect();

        $this->get('http://dynamic-school.sahodaya.test/alumni')
            ->assertOk()
            ->assertSee('Our Alumni Network')
            ->assertSee('Nihal Alumnus')
            ->assertSee('Engineer');

        $this->from('http://dynamic-school.sahodaya.test/admission-enquiry')
            ->post('http://dynamic-school.sahodaya.test/admission-enquiry', [
                'student_name' => 'Sara Student',
                'dob' => '2018-05-12',
                'class_applying' => '3',
                'parent_name' => 'Fathima Parent',
                'phone' => '9876543210',
                'email' => 'fathima@example.com',
                'address' => 'School Road',
                'message' => 'Please share the next steps.',
            ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->get("/school-admin/{$this->school->id}/enquiries")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('School/Enquiries/Index', false)
                ->where('enquiries.data.0.student_name', 'Sara Student')
                ->where('enquiries.data.0.parent_name', 'Fathima Parent'));
    }

    private function publishAlFarooqueTemplate(): void
    {
        $api = "/school-admin/{$this->school->id}/site-builder/api";

        $this->actingAs($this->admin)->postJson("{$api}/experience/draft", [
            'site_id' => $this->site->id,
            'template_key' => 'al-farooque',
            'mode' => 'full',
        ])->assertOk();

        $this->actingAs($this->admin)->postJson("{$api}/experience/publish", [
            'site_id' => $this->site->id,
        ])->assertOk()->assertJsonPath('published', true);
    }

    /** @param array<string, mixed> $config */
    private function addPublishedSection(string $type, string $variant, array $config): void
    {
        $this->actingAs($this->admin)->postJson("/school-admin/{$this->school->id}/site-builder/api/sections", [
            'site_id' => $this->site->id,
            'section_type' => $type,
            'variant' => $variant,
            'config' => $config,
            'is_active' => true,
            'status' => 'published',
        ])->assertCreated()->assertJsonPath('status', 'published');
    }
}
