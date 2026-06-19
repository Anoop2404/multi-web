<?php

namespace App\Support;

use App\Models\OfficeBearers;
use App\Models\SiteSection;
use App\Models\Tenant;
use App\Models\TenantSetting;

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
        self::seedSections($sahodaya);
        self::seedSampleBearers($sahodaya);
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
                    ['label' => 'About', 'url' => '/#about', 'external' => false, 'children' => []],
                    ['label' => 'Programmes', 'url' => '/#programmes', 'external' => false, 'children' => []],
                    ['label' => 'Office Bearers', 'url' => '/#office-bearers', 'external' => false, 'children' => []],
                    ['label' => 'Member Schools', 'url' => '/#member-schools', 'external' => false, 'children' => []],
                    [
                        'label' => 'Academic', 'url' => '/#academic', 'external' => false,
                        'children' => [
                            ['label' => 'Kids Fest 2025-26', 'url' => '/#academic', 'external' => false],
                            ['label' => 'Athletic Meet', 'url' => '/#academic', 'external' => false],
                            ['label' => 'Kalotsav 2025', 'url' => '/#academic', 'external' => false],
                            ['label' => 'MSAT / Aptitude', 'url' => '/#academic', 'external' => false],
                            ['label' => 'Teacher Fest', 'url' => '/#academic', 'external' => false],
                            ['label' => 'Membership Renewal', 'url' => '/school-register', 'external' => false],
                        ],
                    ],
                    ['label' => 'Useful Links', 'url' => '/#useful-links', 'external' => false, 'children' => []],
                    ['label' => 'Contact', 'url' => '/#contact', 'external' => false, 'children' => []],
                    ['label' => 'School Login', 'url' => '/login', 'external' => false, 'children' => []],
                ],
            ]]
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
                    ['label' => 'School Registration', 'url' => '/school-register'],
                ],
            ]]
        );
    }

    private static function seedSections(Tenant $sahodaya): void
    {
        if ($sahodaya->sections()->exists()) {
            return;
        }

        SiteSection::create([
            'tenant_id'     => $sahodaya->id,
            'section_type'  => 'sahodaya_home',
            'variant'       => 'dashboard',
            'display_order' => 1,
            'is_active'     => true,
            'config'        => [
                'heading'            => $sahodaya->name,
                'tagline'            => 'Uniting CBSE schools for academic excellence, cultural programs, and collaborative growth.',
                'eyebrow'            => 'CBSE Sahodaya School Complex',
                'motto'              => 'Caring and Sharing',
                'about_heading'      => 'Caring and Sharing',
                'about_text'         => 'An association of CBSE-affiliated schools fostering collaboration, cultural programmes, sports meets, and professional development — guided by the Sahodaya philosophy of collective growth.',
                'programmes_heading' => 'Programmes & Services',
                'bearers_heading'    => 'Office Bearers',
                'academic_heading'   => 'Programs & Results',
                'events_heading'     => 'Upcoming Events',
                'schools_heading'    => 'Member Schools',
                'links_heading'      => 'Useful Links',
                'portal_heading'     => 'Member School Portal',
                'portal_text'        => 'Schools can log in to submit annual registration, upload student & teacher data, and track membership status.',
                'contact_heading'    => 'Contact Us',
                'contact_text'       => 'Reach the Sahodaya office for membership, events, and academic coordination.',
            ],
        ]);
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
