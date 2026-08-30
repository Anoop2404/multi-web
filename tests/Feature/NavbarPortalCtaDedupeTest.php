<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Support\SchoolPortalNavLinks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression test for a navbar bug where the school portal_cta button (rendered by
 * partials/navbars/portal-cta.blade.php) and a SchoolPortalNavLinks::ensureNavItems()
 * injected "Admin Login" nav item both point at the same URL and both rendered —
 * showing the same login link twice, side by side. Fixed in
 * SchoolPortalNavLinks::mergePortalCta()/ensureNavItems(), which now skips injecting the
 * login item into $items when the standalone CTA button (show_in_navbar) already covers it.
 */
class NavbarPortalCtaDedupeTest extends TestCase
{
    use RefreshDatabase;

    private function renderNavbar(array $items, array $portalCtaOverrides, string $variant = 'logo-left'): string
    {
        $tenant = Tenant::make(['id' => 'dedupe-test-tenant', 'name' => 'Dedupe Test School']);

        $navConfig = SchoolPortalNavLinks::mergePortalCta([
            'style' => $variant,
            'layout_variant' => $variant,
            'items' => $items,
            'portal_cta' => $portalCtaOverrides,
        ]);

        return view('partials.navbar', [
            'tenant' => $tenant,
            'navConfig' => $navConfig,
            'logo' => null,
            'homeUrl' => '/',
        ])->render();
    }

    /** Desktop-visible markup only — the mobile drawer is a separate, `lg:hidden` copy of the same links and isn't part of what a desktop visitor sees at once. */
    private function desktopRegion(string $html): string
    {
        return Str::before($html, 'x-show="open" x-cloak');
    }

    public function test_admin_login_appears_once_when_the_cta_button_is_shown(): void
    {
        // Explicitly reproduce the pre-fix bug conditions: CTA button shown AND the
        // login item also requested in the menu — the dedupe logic must still collapse
        // this to one link. (Defaults no longer point the CTA at admin login at all —
        // see test_default_cta_points_at_portal_login_not_admin_login below — so this
        // test configures the scenario explicitly rather than relying on defaults.)
        $html = $this->renderNavbar(items: [
            ['label' => 'Home', 'url' => '/', 'external' => false, 'children' => []],
        ], portalCtaOverrides: [
            'show_in_navbar' => true,
            'show_in_menu' => true,
            'login_url' => SchoolPortalNavLinks::LOGIN_URL,
            'login_label' => 'Admin Login',
        ]);

        $count = substr_count($this->desktopRegion($html), 'href="'.SchoolPortalNavLinks::LOGIN_URL.'"');
        $this->assertSame(1, $count, "Expected exactly one Admin Login link in the desktop navbar, found {$count}.");
    }

    public function test_admin_login_still_appears_in_menu_when_the_cta_button_is_hidden(): void
    {
        $html = $this->renderNavbar(items: [
            ['label' => 'Home', 'url' => '/', 'external' => false, 'children' => []],
        ], portalCtaOverrides: [
            'show_in_navbar' => false,
            'show_in_menu' => true,
            'login_url' => SchoolPortalNavLinks::LOGIN_URL,
            'login_label' => 'Admin Login',
        ]);

        // No standalone CTA button, so the plain menu item is the only way to reach
        // /login — it must still be there.
        $this->assertSame(1, substr_count($this->desktopRegion($html), 'href="'.SchoolPortalNavLinks::LOGIN_URL.'"'));
    }

    public function test_default_cta_points_at_portal_login_not_admin_login(): void
    {
        // Public visitors have no use for an admin login CTA. The default button now
        // points at the student/parent portal, and admin login isn't in the nav at all
        // (it's still reachable via the footer — see SchoolPortalNavLinks::ensureFooterLinks).
        $html = $this->renderNavbar(items: [
            ['label' => 'Home', 'url' => '/', 'external' => false, 'children' => []],
        ], portalCtaOverrides: []);

        $desktop = $this->desktopRegion($html);
        $this->assertSame(1, substr_count($desktop, 'href="'.SchoolPortalNavLinks::PORTAL_LOGIN_URL.'"'));
        $this->assertSame(0, substr_count($desktop, 'href="'.SchoolPortalNavLinks::LOGIN_URL.'"'));
    }

    public function test_sahodaya_homepage_keeps_distinct_school_login_and_portal_links(): void
    {
        // Sahodaya's own nav (School Login -> /login, general portal CTA -> /portal) uses
        // genuinely different URLs and must not be affected by the school-side de-dupe.
        $tenant = Tenant::make(['id' => 'dedupe-sahodaya-tenant', 'name' => 'Dedupe Sahodaya', 'type' => 'sahodaya']);

        $navConfig = \App\Support\PortalNavLinks::mergePortalCta([
            'style' => 'sahodaya-modern',
            'layout_variant' => 'sahodaya-modern',
            'items' => [
                ['label' => 'Home', 'url' => '/', 'external' => false, 'children' => []],
                ['label' => 'School Login', 'url' => '/login', 'external' => false, 'children' => []],
            ],
            'portal_cta' => ['show_in_navbar' => true, 'portal_url' => '/portal', 'login_label' => 'Login'],
        ]);

        $html = view('partials.navbar', [
            'tenant' => $tenant,
            'navConfig' => $navConfig,
            'logo' => null,
            'homeUrl' => '/',
        ])->render();

        $this->assertStringContainsString('href="/login"', $html);
        $this->assertStringContainsString('href="/portal"', $html);
    }
}
