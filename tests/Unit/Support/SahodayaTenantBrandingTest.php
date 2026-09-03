<?php

namespace Tests\Unit\Support;

use App\Models\Tenant;
use App\Support\SahodayaTenantBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SahodayaTenantBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_region_label_keeps_every_word_before_sahodaya_not_just_the_last_one(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Kochi Metro Sahodaya',
            'subdomain' => 'region-label-test', 'is_active' => true,
        ]);

        // Regression: the region regex used to capture only the single word
        // immediately before "Sahodaya", so "Kochi Metro Sahodaya" produced a
        // region of just "Metro" — dropping "Kochi" from every {{region}}
        // placeholder and hero tagline platform-wide for any multi-word name.
        $this->assertSame('Kochi Metro', SahodayaTenantBranding::context($tenant)['region']);
    }

    public function test_region_label_still_works_for_a_single_word_name(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Malappuram Sahodaya',
            'subdomain' => 'region-label-single', 'is_active' => true,
        ]);

        $this->assertSame('Malappuram', SahodayaTenantBranding::context($tenant)['region']);
    }
}
