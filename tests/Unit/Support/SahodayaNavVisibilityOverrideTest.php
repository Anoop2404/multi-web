<?php

namespace Tests\Unit\Support;

use App\Support\SahodayaNavVisibility;
use Tests\TestCase;

class SahodayaNavVisibilityOverrideTest extends TestCase
{
    public function test_platform_override_force_hides_menu(): void
    {
        $visibility = SahodayaNavVisibility::defaults();
        $visibility['menus']['mcq'] = true;
        $visibility['menus']['training'] = true;

        $overrides = ['menus' => ['mcq' => false], 'programs' => []];

        $effective = SahodayaNavVisibility::applyOverride($visibility, $overrides);

        $this->assertFalse($effective['menus']['mcq']);
        $this->assertTrue($effective['menus']['training']);
    }

    public function test_platform_override_force_hides_program(): void
    {
        $visibility = SahodayaNavVisibility::defaults();

        $overrides = ['programs' => ['sports-meet' => false], 'menus' => []];

        $effective = SahodayaNavVisibility::applyOverride($visibility, $overrides);

        $this->assertFalse($effective['programs']['sports-meet']);
        $this->assertTrue($effective['programs']['kalotsav']);
    }

    public function test_null_overrides_passthrough(): void
    {
        $visibility = SahodayaNavVisibility::defaults();
        $visibility['menus']['finance'] = false;

        $effective = SahodayaNavVisibility::applyOverride($visibility, null);

        $this->assertFalse($effective['menus']['finance']);
    }
}
