<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestCompetitionType;
use App\Models\FestTaxonomyMaster;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Services\Events\FestCompetitionTypeRegistry;
use App\Services\Events\FestTaxonomyRegistry;
use App\Support\FestCatalogSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestCompetitionTypeRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('fest_competition_types')) {
            $this->markTestSkipped('fest_competition_types not migrated.');
        }
    }

    private function sahodaya(): Tenant
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Competition Type Sahodaya',
            'domain' => 'comp-types.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $tenant->id,
            'prefix' => 'CTS',
            'student_data_mode' => 'full_records',
        ]);

        return $tenant;
    }

    public function test_ensure_defaults_seeds_system_types(): void
    {
        $tenant = $this->sahodaya();
        $registry = app(FestCompetitionTypeRegistry::class)->forTenant($tenant->id);
        $registry->ensureDefaults();

        $keys = $registry->activeKeys();
        $this->assertContains('kalolsavam', $keys);
        $this->assertContains('sports', $keys);
        $this->assertContains('custom', $keys);
        $this->assertContains('english_fest', $keys);
        $this->assertTrue(
            FestCompetitionType::where('tenant_id', $tenant->id)->where('type_key', 'sports')->value('is_system')
        );
    }

    public function test_singleton_keys_exclude_custom(): void
    {
        $tenant = $this->sahodaya();
        $singletons = app(FestCompetitionTypeRegistry::class)->forTenant($tenant->id)->singletonKeys();

        $this->assertContains('sports', $singletons);
        $this->assertNotContains('custom', $singletons);
    }

    public function test_validation_rule_accepts_active_custom_type(): void
    {
        $tenant = $this->sahodaya();
        $registry = app(FestCompetitionTypeRegistry::class)->forTenant($tenant->id);
        $registry->ensureDefaults();

        FestCompetitionType::create([
            'tenant_id' => $tenant->id,
            'type_key' => 'robotics',
            'label' => 'Robotics',
            'nav_slug' => 'robotics',
            'is_singleton' => false,
            'is_system' => false,
            'sort_order' => 200,
            'is_active' => true,
        ]);

        $this->assertContains('robotics', $registry->activeKeys());
    }

    public function test_catalog_sections_seed_from_config_into_taxonomy(): void
    {
        if (! Schema::hasTable('fest_taxonomy_masters')) {
            $this->markTestSkipped('fest_taxonomy_masters not migrated.');
        }

        $tenant = $this->sahodaya();
        $taxonomy = app(FestTaxonomyRegistry::class)->forTenant($tenant->id);
        $taxonomy->ensureCatalogSectionDefaults();

        $this->assertTrue(
            FestTaxonomyMaster::where('tenant_id', $tenant->id)
                ->where('dimension', 'catalog_section')
                ->where('entry_key', 'sports.track')
                ->exists()
        );

        $sections = FestCatalogSections::forEventType('sports', $tenant->id);
        $slugs = array_column($sections, 'slug');
        $this->assertContains('track', $slugs);
        $this->assertContains('field', $slugs);
    }

    public function test_programs_for_nav_includes_custom_type_under_programs_prefix(): void
    {
        $tenant = $this->sahodaya();
        $registry = app(FestCompetitionTypeRegistry::class)->forTenant($tenant->id);
        $registry->ensureDefaults();

        FestCompetitionType::create([
            'tenant_id' => $tenant->id,
            'type_key' => 'robotics',
            'label' => 'Robotics Meet',
            'nav_slug' => 'robotics',
            'route_prefix' => 'robotics',
            'is_singleton' => true,
            'is_system' => false,
            'sort_order' => 200,
            'is_active' => true,
        ]);

        $programs = $registry->programsForNav();
        $this->assertArrayHasKey('robotics', $programs);
        $this->assertSame('programs/robotics', $programs['robotics']['prefix']);
        $this->assertSame('robotics', $programs['robotics']['eventType']);
        $this->assertSame('sports', $programs['sports-meet']['prefix']);
    }

    public function test_multi_person_types_include_pair_and_trio(): void
    {
        $this->assertTrue(\App\Support\FestTeamSquadRules::isMultiPerson('pair'));
        $this->assertTrue(\App\Support\FestTeamSquadRules::isMultiPerson('trio'));
        $this->assertSame(2, \App\Support\FestTeamSquadRules::defaultSizeFor('pair'));
        $this->assertSame(3, \App\Support\FestTeamSquadRules::defaultSizeFor('trio'));
    }
}
