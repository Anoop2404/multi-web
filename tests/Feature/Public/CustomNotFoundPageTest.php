<?php

namespace Tests\Feature\Public;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomNotFoundPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_public_event_shows_tenant_branded_event_recovery_page(): void
    {
        Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Malappuram Sahodaya',
            'domain' => 'not-found.test',
            'is_active' => true,
        ]);

        $this->get('http://not-found.test/fest/999999')
            ->assertNotFound()
            ->assertSee('Malappuram Sahodaya')
            ->assertSee('Event page not found')
            ->assertSee('View all events')
            ->assertSee('http://not-found.test/fest', false)
            ->assertSee('noindex, nofollow', false)
            ->assertDontSee('No query results for model');
    }

    /**
     * A non-numeric event id (bots, mangled links) used to reach FestPortalController's
     * int $eventId and 500 with a TypeError; a non-numeric item id a Postgres bigint cast
     * error. Both are now a plain 404.
     */
    public function test_non_numeric_public_fest_ids_are_not_found_not_a_server_error(): void
    {
        Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Malappuram Sahodaya',
            'domain' => 'bad-fest-id.test',
            'is_active' => true,
        ]);

        $this->get('http://bad-fest-id.test/fest/wp-login.php')->assertNotFound();
        $this->get('http://bad-fest-id.test/fest/abc/results')->assertNotFound();
        $this->get('http://bad-fest-id.test/fest/12/items/abc/results')->assertNotFound();
    }

    public function test_unknown_page_shows_generic_recovery_page_without_event_action(): void
    {
        Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Kochi Sahodaya',
            'domain' => 'generic-not-found.test',
            'is_active' => true,
        ]);

        $this->get('http://generic-not-found.test/a-page-that-does-not-exist')
            ->assertNotFound()
            ->assertSee('Kochi Sahodaya')
            ->assertSee('Page not found')
            ->assertSee('Back to home')
            ->assertDontSee('View all events');
    }
}
