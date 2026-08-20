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
