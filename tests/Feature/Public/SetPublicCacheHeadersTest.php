<?php

namespace Tests\Feature\Public;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * GET /fest/{event} (the event's own item-finder page, resources/views/public/fest/
 * show.blade.php) shows each item's live publish state — a "Results" button vs "Not yet
 * published" — the same as the dedicated /fest/{event}/results page, which already gets
 * a short 30s/60s cache for exactly that reason. The event page instead fell into the
 * generic 1-hour "static content" bucket, so unpublishing an item's results left visitors
 * (and any CDN) seeing a stale "Results" button for up to an hour even though
 * FestEventItem.results_published_at/results_hidden were updated immediately.
 */
class SetPublicCacheHeadersTest extends TestCase
{
    use RefreshDatabase;

    private FestEvent $event;

    protected function setUp(): void
    {
        parent::setUp();

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Cache Headers Sahodaya',
            'domain' => 'cache-headers-test.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'CH', 'student_data_mode' => 'counts_only']);

        $this->event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Cache Headers Fest', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing', 'schedule_published' => true,
        ]);

        // Satisfies hasPublishedItems() so results()/tv() render (200) instead of
        // aborting 403 for an event with nothing published — a 403 short-circuits this
        // middleware's isSuccessful() check, which would otherwise mask the real
        // Cache-Control value under test with Symfony's own unrelated 403 default.
        FestEventItem::create([
            'event_id' => $this->event->id, 'title' => 'Cache Test Item', 'participant_type' => 'individual',
            'is_enabled' => true, 'results_published_at' => now(), 'results_hidden' => false,
        ]);
    }

    /** Cache-Control directive order isn't semantically meaningful — compare the directive set, not the raw string. */
    private function assertCacheControl(string $expected, string $path): void
    {
        $response = $this->get("http://cache-headers-test.test/{$path}");
        $response->assertSuccessful();

        $actual = $response->headers->get('Cache-Control');
        $this->assertNotNull($actual, "No Cache-Control header on {$path}");

        $normalize = fn (string $v) => collect(explode(',', $v))->map(fn ($p) => trim($p))->sort()->values()->all();
        $this->assertSame($normalize($expected), $normalize($actual), "Cache-Control mismatch for {$path}");
    }

    public function test_the_event_show_page_gets_the_short_lived_cache_like_results_does(): void
    {
        $this->assertCacheControl(
            'public, max-age=30, s-maxage=60, stale-while-revalidate=120',
            "fest/{$this->event->id}",
        );
    }

    public function test_the_dedicated_results_page_still_gets_the_short_lived_cache(): void
    {
        $this->assertCacheControl(
            'public, max-age=30, s-maxage=60, stale-while-revalidate=120',
            "fest/{$this->event->id}/results",
        );
    }

    public function test_deeper_event_subpages_are_unaffected_and_keep_the_generic_hour_long_cache(): void
    {
        $this->assertCacheControl(
            'public, max-age=3600, s-maxage=3600, stale-while-revalidate=86400',
            "fest/{$this->event->id}/schedule",
        );
    }

    public function test_the_tv_screen_still_gets_the_no_cache_treatment(): void
    {
        // tv()'s admin-preview check touches the session, which Laravel's own session
        // middleware marks by appending 'private' to Cache-Control — pre-existing,
        // unrelated to this middleware's own directive string.
        $this->assertCacheControl(
            'no-cache, max-age=0, must-revalidate, private',
            "fest/{$this->event->id}/tv",
        );
    }
}
