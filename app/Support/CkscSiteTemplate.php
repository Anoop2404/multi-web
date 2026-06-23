<?php

namespace App\Support;

use App\Models\SiteSection;
use App\Models\Tenant;

class CkscSiteTemplate
{
    public static function apply(Tenant $sahodaya, bool $replaceSections = true): void
    {
        if ($sahodaya->type !== 'sahodaya') {
            return;
        }

        self::seedNav($sahodaya);
        self::seedTheme($sahodaya);
        self::seedFooter($sahodaya);
        self::seedCmsPages($sahodaya);

        if ($replaceSections || ! $sahodaya->sections()->exists()) {
            self::seedSections($sahodaya, $replaceSections);
        } else {
            SahodayaTenantBranding::personalizeExistingSections($sahodaya);
        }

        TenantPublicSite::setEnabled($sahodaya, true);
    }

    private static function seedNav(Tenant $sahodaya): void
    {
        $sahodaya->setSetting('nav_config', PortalNavLinks::mergePortalCta(
            SahodayaTenantBranding::navConfig($sahodaya)
        ));
    }

    private static function seedTheme(Tenant $sahodaya): void
    {
        $existing = $sahodaya->getSetting('theme', []) ?? [];
        if (! empty($existing['customized'])) {
            return;
        }

        $sahodaya->setSetting('theme', SahodayaTenantBranding::defaultTheme($sahodaya));
    }

    private static function seedFooter(Tenant $sahodaya): void
    {
        $sahodaya->setSetting('footer_config', PortalNavLinks::ensureFooterLinks(
            SahodayaTenantBranding::footerConfig($sahodaya)
        ));
    }

    private static function seedCmsPages(Tenant $sahodaya): void
    {
        $sahodaya->setSetting('cms_pages', SahodayaTenantBranding::cmsPages($sahodaya));
    }

    private static function seedSections(Tenant $sahodaya, bool $replace): void
    {
        if ($replace) {
            $sahodaya->sections()->delete();
        }

        foreach (SahodayaTenantBranding::homepageSections($sahodaya) as $section) {
            SiteSection::create(array_merge($section, [
                'tenant_id' => $sahodaya->id,
                'is_active' => true,
            ]));
        }
    }
}
