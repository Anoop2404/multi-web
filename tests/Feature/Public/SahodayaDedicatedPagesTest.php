<?php

namespace Tests\Feature\Public;

use App\Models\GalleryAlbum;
use App\Models\OfficeBearers;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SahodayaDedicatedPagesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $sahodaya;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Dedicated Pages Sahodaya',
            'subdomain' => 'dedicated-pages',
            'is_active' => true,
        ]);
    }

    public function test_member_schools_index_lists_active_child_schools(): void
    {
        Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $this->sahodaya->id,
            'name' => 'Alpha Public School', 'subdomain' => 'alpha-school', 'is_active' => true,
        ]);
        Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $this->sahodaya->id,
            'name' => 'Inactive School', 'subdomain' => 'inactive-school', 'is_active' => false,
        ]);

        // Tenant::getNameAttribute() uppercases school names on read (see app/Models/Tenant.php).
        $this->get('http://dedicated-pages.sahodaya.test/member-schools')
            ->assertOk()
            ->assertSee('ALPHA PUBLIC SCHOOL')
            ->assertDontSee('Inactive School');
    }

    public function test_member_schools_index_shows_empty_state_with_no_schools(): void
    {
        $this->get('http://dedicated-pages.sahodaya.test/member-schools')
            ->assertOk()
            ->assertSee('will be listed here once added');
    }

    public function test_office_bearers_index_lists_active_bearers(): void
    {
        OfficeBearers::create([
            'tenant_id' => $this->sahodaya->id, 'name' => 'Jane Principal', 'role' => 'President',
            'is_active' => true, 'display_order' => 1,
        ]);
        OfficeBearers::create([
            'tenant_id' => $this->sahodaya->id, 'name' => 'Retired Bearer', 'role' => 'President',
            'is_active' => false, 'display_order' => 2,
        ]);

        $this->get('http://dedicated-pages.sahodaya.test/office-bearers')
            ->assertOk()
            ->assertSee('Jane Principal')
            ->assertDontSee('Retired Bearer');
    }

    public function test_office_bearers_index_shows_empty_state_with_no_bearers(): void
    {
        $this->get('http://dedicated-pages.sahodaya.test/office-bearers')
            ->assertOk()
            ->assertSee('will be listed here once added');
    }

    public function test_gallery_index_lists_albums_and_links_to_detail_page(): void
    {
        GalleryAlbum::create([
            'tenant_id' => $this->sahodaya->id, 'title' => 'Annual Day 2026', 'slug' => 'annual-day-2026',
        ]);

        $this->get('http://dedicated-pages.sahodaya.test/gallery')
            ->assertOk()
            ->assertSee('Annual Day 2026')
            ->assertSee('/gallery/annual-day-2026', false);
    }

    public function test_gallery_index_shows_empty_state_with_no_albums(): void
    {
        $this->get('http://dedicated-pages.sahodaya.test/gallery')
            ->assertOk()
            ->assertSee('will appear here once added');
    }
}
