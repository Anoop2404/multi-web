<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\NewsArticle;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicContentTest extends TestCase
{
    use RefreshDatabase;

    private function createSchoolTenant(string $domain = 'testschool.test'): Tenant
    {
        $tenant = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'school',
            'name'      => 'Test School',
            'domain'    => $domain,
            'is_active' => true,
        ]);

        return $tenant;
    }

    public function test_published_news_article_is_accessible_on_tenant_domain(): void
    {
        $tenant = $this->createSchoolTenant();

        $article = NewsArticle::create([
            'tenant_id'    => $tenant->id,
            'title'        => 'Annual Day Celebration',
            'slug'         => 'annual-day-celebration',
            'body'         => 'Join us for the annual day event.',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->get('http://testschool.test/news/'.$article->slug);

        $response->assertOk();
        $response->assertSee('Annual Day Celebration');
        $response->assertSee('Join us for the annual day event.');
    }

    public function test_unpublished_news_article_returns_not_found(): void
    {
        $tenant = $this->createSchoolTenant();

        NewsArticle::create([
            'tenant_id'    => $tenant->id,
            'title'        => 'Draft Article',
            'slug'         => 'draft-article',
            'body'         => 'Not yet published.',
            'published_at' => null,
        ]);

        $this->get('http://testschool.test/news/draft-article')->assertNotFound();
    }

    public function test_event_detail_page_is_accessible_on_tenant_domain(): void
    {
        $tenant = $this->createSchoolTenant('event-school.test');

        $event = Event::create([
            'tenant_id'   => $tenant->id,
            'title'       => 'Sports Meet 2026',
            'slug'        => 'sports-meet-2026',
            'description' => 'Inter-house athletics competition.',
            'start_date'  => now()->addWeek()->toDateString(),
            'venue'       => 'School Ground',
        ]);

        $response = $this->get('http://event-school.test/events/'.$event->slug);

        $response->assertOk();
        $response->assertSee('Sports Meet 2026');
        $response->assertSee('School Ground');
    }

    public function test_public_pages_include_cache_headers(): void
    {
        $tenant = $this->createSchoolTenant('cache-school.test');

        $response = $this->get('http://cache-school.test/');

        $response->assertOk();
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=3600', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('s-maxage=3600', $response->headers->get('Cache-Control'));
    }

    public function test_news_index_page_lists_published_articles(): void
    {
        $tenant = $this->createSchoolTenant('news-index.test');

        NewsArticle::create([
            'tenant_id'    => $tenant->id,
            'title'        => 'Sports Day',
            'slug'         => 'sports-day',
            'body'         => 'Annual sports day event.',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->get('http://news-index.test/news');

        $response->assertOk();
        $response->assertSee('Sports Day');
    }

    public function test_gallery_album_page_is_accessible(): void
    {
        $tenant = $this->createSchoolTenant('gallery-school.test');

        $album = \App\Models\GalleryAlbum::create([
            'tenant_id' => $tenant->id,
            'title'     => 'Annual Day 2026',
            'slug'      => 'annual-day-2026',
        ]);

        $response = $this->get('http://gallery-school.test/gallery/'.$album->slug);

        $response->assertOk();
        $response->assertSee('Annual Day 2026');
    }
}
