<?php

namespace App\Support;

use App\Models\OfficeBearers;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TenantSubscription;
use App\Models\WebsiteSite;
use App\Services\Website\SahodayaTemplateApplier;

class SahodayaSiteTemplate
{
    public static function apply(Tenant $sahodaya): void
    {
        if ($sahodaya->type !== 'sahodaya') {
            return;
        }

        self::seedNav($sahodaya);
        self::seedTheme($sahodaya);
        self::seedFooter($sahodaya);
        self::ensureFreeSubscription($sahodaya);
        self::seedSections($sahodaya);
        self::seedSampleBearers($sahodaya);
    }

    private static function ensureFreeSubscription(Tenant $sahodaya): void
    {
        if (TenantSubscription::where('tenant_id', $sahodaya->id)->exists()) {
            return;
        }

        $freePlan = SubscriptionPlan::where('slug', 'free')->first();
        if (! $freePlan) {
            throw new \RuntimeException('Free subscription plan is not seeded — run SubscriptionPlanSeeder before provisioning tenants.');
        }

        TenantSubscription::create([
            'tenant_id' => $sahodaya->id,
            'plan_id' => $freePlan->id,
            'period_start' => now(),
            'period_end' => now()->addYears(50),
            'status' => 'active',
            'auto_renew' => true,
        ]);
    }

    private static function seedNav(Tenant $sahodaya): void
    {
        TenantSetting::updateOrCreate(
            ['tenant_id' => $sahodaya->id, 'key' => 'nav_config'],
            ['value' => [
                'style'          => 'sahodaya-modern',
                'layout_variant' => 'sahodaya-modern',
                'items'          => [
                    ['label' => 'Home', 'url' => '/', 'external' => false, 'children' => []],
                    ['label' => 'About', 'url' => '/#about-sahodaya', 'external' => false, 'children' => []],
                    ['label' => 'Programmes', 'url' => '/#events-programs', 'external' => false, 'children' => []],
                    ['label' => 'Office Bearers', 'url' => '/#office-bearers', 'external' => false, 'children' => []],
                    ['label' => 'Member Schools', 'url' => '/#member-schools', 'external' => false, 'children' => []],
                    ['label' => 'Gallery', 'url' => '/#gallery', 'external' => false, 'children' => []],
                    [
                        'label' => 'Academic', 'url' => '/fest', 'external' => false,
                        'children' => [
                            ['label' => 'Fest Schedule & Results', 'url' => '/fest', 'external' => false],
                            ['label' => 'MCQ Talent Search Papers', 'url' => '/mcq/papers', 'external' => false],
                            ['label' => 'Membership Renewal', 'url' => '/school-register', 'external' => false],
                        ],
                    ],
                    ['label' => 'Circulars', 'url' => '/circulars', 'external' => false, 'children' => []],
                    ['label' => 'Contact', 'url' => '/#contact', 'external' => false, 'children' => []],
                    ['label' => 'School Registration', 'url' => '/school-register', 'external' => false, 'children' => []],
                    ['label' => 'School Login', 'url' => '/login', 'external' => false, 'children' => []],
                ],
                'portal_cta' => PortalNavLinks::portalCtaDefaults(),
            ]]
        );

        TenantSetting::updateOrCreate(
            ['tenant_id' => $sahodaya->id, 'key' => TenantPublicSite::SETTING_KEY],
            ['value' => ['enabled' => true]]
        );
    }

    private static function seedTheme(Tenant $sahodaya): void
    {
        TenantSetting::updateOrCreate(
            ['tenant_id' => $sahodaya->id, 'key' => 'theme'],
            ['value' => [
                'primary'        => '#5b21b6',
                'secondary'      => '#7c3aed',
                'accent_color'   => '#f59e0b',
                'font_heading'   => 'Inter',
                'font_body'      => 'Inter',
                'border_radius'  => '0.75rem',
                'navbar_style'   => 'light',
                'footer_style'   => 'dark',
            ]]
        );
    }

    private static function seedFooter(Tenant $sahodaya): void
    {
        TenantSetting::updateOrCreate(
            ['tenant_id' => $sahodaya->id, 'key' => 'footer_config'],
            ['value' => [
                'layout_variant' => 'three-column',
                'tagline'        => 'CBSE Sahodaya School Complex',
                'copyright'      => '© '.date('Y').' '.$sahodaya->name.'. All rights reserved.',
                'phone'          => $sahodaya->sahodayaProfile?->contact_phone,
                'email'          => $sahodaya->sahodayaProfile?->contact_email,
                'quick_links'    => [
                    ['label' => 'CBSE Official', 'url' => 'https://www.cbse.gov.in'],
                    ['label' => 'School Registration', 'url' => PortalNavLinks::REGISTER_URL],
                    ['label' => 'School Login', 'url' => PortalNavLinks::LOGIN_URL],
                ],
            ]]
        );
    }

    private static function seedSections(Tenant $sahodaya): void
    {
        if ($sahodaya->sections()->exists()) {
            return;
        }

        $site = WebsiteSite::ensurePrimary($sahodaya->id);
        $template = SahodayaWebsiteTemplateCatalog::get('network-directory');
        $context = SahodayaTenantBranding::context($sahodaya);

        $applier = app(SahodayaTemplateApplier::class);
        $applier->applyDraft($sahodaya, $site, 'network-directory', $template, $context);
        $applier->publishDraft($site);
    }

    private static function seedSampleBearers(Tenant $sahodaya): void
    {
        if (OfficeBearers::where('tenant_id', $sahodaya->id)->exists()) {
            return;
        }

        $samples = [
            ['role' => 'President', 'name' => 'President Name', 'school_name' => 'Member School'],
            ['role' => 'General Secretary', 'name' => 'Secretary Name', 'school_name' => 'Member School'],
            ['role' => 'Treasurer', 'name' => 'Treasurer Name', 'school_name' => 'Member School'],
            ['role' => 'IT Coordinator', 'name' => 'IT Coordinator', 'school_name' => 'Member School'],
        ];

        foreach ($samples as $i => $sample) {
            OfficeBearers::create(array_merge($sample, [
                'tenant_id'     => $sahodaya->id,
                'display_order' => $i,
                'is_active'     => true,
            ]));
        }
    }
}
