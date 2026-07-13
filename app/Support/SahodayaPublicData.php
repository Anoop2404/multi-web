<?php

namespace App\Support;

use App\Models\Circular;
use App\Models\KalotsavEvent;
use App\Models\OfficeBearers;
use App\Models\Tenant;
use Illuminate\Support\Collection;

class SahodayaPublicData
{
    /** Role display order — common across Kerala Sahodaya sites */
    public const BEARER_ROLE_ORDER = [
        'Chief Patron', 'Patron', 'President', 'Vice President',
        'General Secretary', 'Joint Secretary', 'Organising Secretary',
        'Treasurer', 'IT Coordinator', 'Sports Co-ordinator', 'Academic Co-ordinator',
        'MSAT Controller of Exam', 'Confed Executive', 'Executive Member',
    ];

    public static function officeBearers(string $tenantId): Collection
    {
        $bearers = OfficeBearers::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        return $bearers
            ->groupBy('role')
            ->map(fn (Collection $group) => $group
                ->sortByDesc(fn (OfficeBearers $b) => sprintf(
                    '%d-%010d-%04d',
                    self::bearerHasPhoto($b) ? 1 : 0,
                    $b->updated_at?->timestamp ?? 0,
                    $b->display_order,
                ))
                ->first())
            ->filter()
            ->sortBy(fn (OfficeBearers $b) => [
                ($idx = array_search($b->role, self::BEARER_ROLE_ORDER, true)) === false ? 999 : $idx,
                $b->display_order,
                $b->id,
            ])
            ->values();
    }

    private static function bearerHasPhoto(OfficeBearers $bearer): bool
    {
        return filled($bearer->photo) && $bearer->photo !== '0';
    }

    public static function memberSchools(string $tenantId): Collection
    {
        return Tenant::where('parent_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public static function upcomingEvents(string $tenantId, int $limit = 4): Collection
    {
        return KalotsavEvent::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('event_date')->orWhere('event_date', '>=', now()->toDateString());
            })
            ->orderBy('event_date')
            ->limit($limit)
            ->get();
    }

    public static function latestCirculars(string $tenantId, int $limit = 6): Collection
    {
        return Circular::where('tenant_id', $tenantId)
            ->orderByDesc('issued_date')
            ->limit($limit)
            ->get();
    }

    public static function announcements(string $tenantId, array $config = [], int $limit = 5): Collection
    {
        $manual = collect($config['announcements'] ?? [])
            ->filter(fn ($a) => ! empty($a['title']))
            ->map(fn ($a) => (object) [
                'title' => $a['title'],
                'url'   => $a['url'] ?? '#',
                'date'  => $a['date'] ?? null,
                'badge' => $a['badge'] ?? 'News',
            ]);

        $fromDb = self::latestCirculars($tenantId, $limit)->map(fn ($c) => (object) [
            'title' => $c->title,
            'url'   => $c->file_path ? asset('storage/'.$c->file_path) : '#',
            'date'  => $c->issued_date?->format('M j, Y'),
            'badge' => $c->category ?? 'Circular',
        ]);

        return $manual->concat($fromDb)->take($limit);
    }

    /** Programmes grid — inspired by CKSC + Confederation sites */
    public static function programmes(array $config): array
    {
        if (! empty($config['programmes']) && is_array($config['programmes'])) {
            return $config['programmes'];
        }

        return [
            ['label' => 'Kalotsav', 'description' => 'Inter-school arts & cultural festival', 'icon' => '🏆', 'url' => '#academic'],
            ['label' => 'Athletic Meet', 'description' => 'Annual sports championship', 'icon' => '🏃', 'url' => '#academic'],
            ['label' => 'Kids Fest', 'description' => 'Primary cluster cultural events', 'icon' => '🎨', 'url' => '#academic'],
            ['label' => 'MSAT / Aptitude', 'description' => 'Talent search & assessment', 'icon' => '📚', 'url' => '#academic'],
            ['label' => 'Teacher Fest', 'description' => 'Professional development for educators', 'icon' => '👩‍🏫', 'url' => '#academic'],
            ['label' => 'Membership', 'description' => 'School registration & renewal', 'icon' => '📝', 'url' => '/school-register'],
        ];
    }

    public static function motto(array $config): string
    {
        return $config['motto'] ?? 'Caring and Sharing — rising together for academic excellence.';
    }

    public static function aboutText(array $config, Tenant $tenant): string
    {
        return $config['about_text']
            ?? 'An association of CBSE-affiliated schools fostering collaboration, cultural programmes, sports meets, and professional development — guided by the Sahodaya philosophy of collective growth.';
    }

    /** @return array<int, array{year: string, links: array}> */
    public static function academicYears(array $config): array
    {
        if (! empty($config['years']) && is_array($config['years'])) {
            return $config['years'];
        }

        return [
            [
                'year'  => '2025-26',
                'links' => [
                    ['label' => 'Kids Fest 2025-26', 'url' => '#', 'icon' => '🎨'],
                    ['label' => 'Athletic Meet 2025-26', 'url' => '#', 'icon' => '🏃'],
                    ['label' => 'Kalotsav 2025', 'url' => '#', 'icon' => '🏆'],
                    ['label' => 'MSAT Resources', 'url' => '#', 'icon' => '📚'],
                    ['label' => 'English Fest Result', 'url' => '#', 'icon' => '📖'],
                    ['label' => 'Science Fest', 'url' => '#', 'icon' => '🔬'],
                    ['label' => 'Toppers\' Meet Registration', 'url' => '#', 'icon' => '🎓'],
                    ['label' => 'Membership Renewal', 'url' => '/school-register', 'icon' => '📝'],
                    ['label' => 'CBSE Portals & Manuals', 'url' => '#', 'icon' => '🔗'],
                ],
            ],
            [
                'year'  => '2024-25',
                'links' => [
                    ['label' => 'Kids Fest Results', 'url' => '#', 'icon' => '🎨'],
                    ['label' => 'Kalotsav 2024', 'url' => '#', 'icon' => '🏆'],
                    ['label' => 'English Fest Result', 'url' => '#', 'icon' => '📖'],
                    ['label' => 'Science Fest Result', 'url' => '#', 'icon' => '🔬'],
                    ['label' => 'MSAT Registration', 'url' => '#', 'icon' => '📚'],
                ],
            ],
            [
                'year'  => '2023-24',
                'links' => [
                    ['label' => 'Activity Calendar', 'url' => '#', 'icon' => '📅'],
                    ['label' => 'Toppers\' Meet Photos', 'url' => '#', 'icon' => '📸'],
                ],
            ],
        ];
    }

    /** @return array<int, array{label: string, url: string, icon?: string}> */
    public static function usefulLinks(array $config): array
    {
        if (! empty($config['links']) && is_array($config['links'])) {
            return $config['links'];
        }

        return [
            ['label' => 'CBSE Official', 'url' => 'https://www.cbse.gov.in', 'icon' => '🏛️'],
            ['label' => 'CKSC Confederation', 'url' => 'https://www.confedsahodaya.com', 'icon' => '🤝'],
            ['label' => 'DIKSHA Portal', 'url' => 'https://diksha.gov.in', 'icon' => '📱'],
            ['label' => 'Swayam', 'url' => 'https://swayam.gov.in', 'icon' => '🎓'],
            ['label' => 'NCERT', 'url' => 'https://ncert.nic.in', 'icon' => '📘'],
            ['label' => 'Sarva Shiksha', 'url' => 'https://samagra.education.gov.in', 'icon' => '🏫'],
        ];
    }

    public static function contactPhone(Tenant $tenant, array $config = []): ?string
    {
        return $config['phone']
            ?? $tenant->sahodayaProfile?->contact_phone
            ?? $tenant->getSetting('contact_phone');
    }
}
