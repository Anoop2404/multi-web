<?php

namespace App\Support;

use App\Models\SahodayaProfile;
use App\Models\SiteSection;
use App\Models\Tenant;

class SahodayaHomepageContent
{
    public static function get(Tenant $sahodaya): array
    {
        return TenancyDatabase::whenDatabaseReady(
            $sahodaya,
            fn () => self::loadFromDatabase($sahodaya),
            self::fallback($sahodaya),
        ) ?? self::fallback($sahodaya);
    }

    public static function update(Tenant $sahodaya, array $data): void
    {
        TenancyDatabase::runWhenDatabaseReady($sahodaya, function () use ($sahodaya, $data) {
            $section = self::sectionRecord($sahodaya);
            $config  = array_merge($section->config ?? [], $data);
            $section->update(['config' => $config]);

            $profile = SahodayaProfile::firstOrCreate(['tenant_id' => $sahodaya->id]);
            $profile->update([
                'contact_phone' => $data['phone'] ?? $profile->contact_phone,
                'contact_email' => $data['email'] ?? $profile->contact_email,
                'address'       => $data['address'] ?? $profile->address,
            ]);

            $footer = $sahodaya->getSetting('footer_config', []) ?? [];
            if (! empty($data['phone'])) {
                $footer['phone'] = $data['phone'];
            }
            if (! empty($data['email'])) {
                $footer['email'] = $data['email'];
            }
            if (! empty($data['address'])) {
                $footer['address'] = $data['address'];
            }
            $sahodaya->setSetting('footer_config', $footer);

            $sahodaya->invalidateCache();
        });
    }

    /** @internal Call only inside tenant DB context. */
    public static function section(Tenant $sahodaya): SiteSection
    {
        return self::sectionRecord($sahodaya);
    }

    private static function sectionRecord(Tenant $sahodaya): SiteSection
    {
        return SiteSection::firstOrCreate(
            [
                'tenant_id'    => $sahodaya->id,
                'section_type' => 'sahodaya_home',
                'variant'      => 'dashboard',
            ],
            [
                'display_order' => 1,
                'is_active'     => true,
                'config'        => [],
            ]
        );
    }

    private static function loadFromDatabase(Tenant $sahodaya): array
    {
        $profile = SahodayaProfile::firstOrCreate(
            ['tenant_id' => $sahodaya->id],
            ['student_data_mode' => 'not_required', 'membership_fee_type' => 'fixed']
        );

        $config = self::sectionRecord($sahodaya)->config ?? [];

        return [
            'heading'            => $config['heading'] ?? $sahodaya->name,
            'tagline'            => $config['tagline'] ?? '',
            'eyebrow'            => $config['eyebrow'] ?? 'CBSE Sahodaya School Complex',
            'motto'              => $config['motto'] ?? SahodayaPublicData::motto([]),
            'about_heading'      => $config['about_heading'] ?? 'Caring and Sharing',
            'about_text'         => $config['about_text'] ?? SahodayaPublicData::aboutText([], $sahodaya),
            'phone'              => $config['phone'] ?? $profile->contact_phone ?? '',
            'email'              => $config['email'] ?? $profile->contact_email ?? '',
            'address'            => $profile->address ?? '',
            'contact_heading'    => $config['contact_heading'] ?? 'Contact Us',
            'contact_text'       => $config['contact_text'] ?? '',
            'announcements'      => $config['announcements'] ?? [],
            'programmes'         => ! empty($config['programmes']) ? $config['programmes'] : SahodayaPublicData::programmes([]),
            'years'              => ! empty($config['years']) ? $config['years'] : SahodayaPublicData::academicYears([]),
            'links'              => ! empty($config['links']) ? $config['links'] : SahodayaPublicData::usefulLinks([]),
            'programmes_heading' => $config['programmes_heading'] ?? 'Programmes & Services',
            'academic_heading'   => $config['academic_heading'] ?? 'Programs & Results',
            'links_heading'      => $config['links_heading'] ?? 'Useful Links',
        ];
    }

    /** @return array<string, mixed> */
    private static function fallback(Tenant $sahodaya): array
    {
        return [
            'heading'            => $sahodaya->name,
            'tagline'            => '',
            'eyebrow'            => 'CBSE Sahodaya School Complex',
            'motto'              => SahodayaPublicData::motto([]),
            'about_heading'      => 'Caring and Sharing',
            'about_text'         => SahodayaPublicData::aboutText([], $sahodaya),
            'phone'              => '',
            'email'              => '',
            'address'            => '',
            'contact_heading'    => 'Contact Us',
            'contact_text'       => '',
            'announcements'      => [],
            'programmes'         => SahodayaPublicData::programmes([]),
            'years'              => SahodayaPublicData::academicYears([]),
            'links'              => SahodayaPublicData::usefulLinks([]),
            'programmes_heading' => 'Programmes & Services',
            'academic_heading'   => 'Programs & Results',
            'links_heading'      => 'Useful Links',
        ];
    }
}
