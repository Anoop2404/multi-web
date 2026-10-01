<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\GalleryAlbum;
use App\Models\NewsArticle;
use App\Support\TenantCache;

class SeoController extends Controller
{
    public function sitemap()
    {
        $tenant = tenancy()->tenant;
        abort_if(!$tenant || !$tenant->is_active, 404);

        $urls = TenantCache::remember($tenant->id, 'sitemap', now()->addHours(6), function () use ($tenant) {
            $base = 'https://' . $tenant->domain;
            $urls = [
                ['loc' => $base, 'priority' => '1.0', 'changefreq' => 'weekly'],
                ['loc' => $base.'/news', 'priority' => '0.8', 'changefreq' => 'weekly'],
                ['loc' => $base.'/events', 'priority' => '0.8', 'changefreq' => 'weekly'],
            ];

            // News articles
            NewsArticle::where('tenant_id', $tenant->id)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->select('slug', 'updated_at')
                ->orderByDesc('updated_at')
                ->limit(100)
                ->each(function ($article) use ($base, &$urls) {
                    $urls[] = [
                        'loc'        => $base . '/news/' . $article->slug,
                        'lastmod'    => $article->updated_at->toAtomString(),
                        'priority'   => '0.7',
                        'changefreq' => 'monthly',
                    ];
                });

            // Events
            Event::where('tenant_id', $tenant->id)
                ->select('slug', 'updated_at')
                ->orderByDesc('updated_at')
                ->limit(50)
                ->each(function ($event) use ($base, &$urls) {
                    if ($event->slug) {
                        $urls[] = [
                            'loc'        => $base . '/events/' . $event->slug,
                            'lastmod'    => $event->updated_at->toAtomString(),
                            'priority'   => '0.6',
                            'changefreq' => 'monthly',
                        ];
                    }
                });

            GalleryAlbum::where('tenant_id', $tenant->id)
                ->select('slug', 'updated_at')
                ->orderByDesc('updated_at')
                ->limit(50)
                ->each(function ($album) use ($base, &$urls) {
                    $urls[] = [
                        'loc'        => $base.'/gallery/'.$album->slug,
                        'lastmod'    => $album->updated_at->toAtomString(),
                        'priority'   => '0.5',
                        'changefreq' => 'monthly',
                    ];
                });

            return $urls;
        });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $url) {
            $xml .= "    <url>\n";
            $xml .= '        <loc>'.htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8')."</loc>\n";
            if (!empty($url['lastmod'])) {
                $xml .= '        <lastmod>'.htmlspecialchars($url['lastmod'], ENT_XML1, 'UTF-8')."</lastmod>\n";
            }
            if (!empty($url['changefreq'])) {
                $xml .= '        <changefreq>'.htmlspecialchars($url['changefreq'], ENT_XML1, 'UTF-8')."</changefreq>\n";
            }
            if (!empty($url['priority'])) {
                $xml .= '        <priority>'.htmlspecialchars($url['priority'], ENT_XML1, 'UTF-8')."</priority>\n";
            }
            $xml .= "    </url>\n";
        }
        $xml .= '</urlset>'."\n";

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Cache-Control' => 'public, max-age=21600',
        ]);
    }

    public function robots()
    {
        $tenant = tenancy()->tenant;
        abort_if(!$tenant, 404);

        $base    = 'https://' . $tenant->domain;
        $content = "User-agent: *\nAllow: /\nSitemap: {$base}/sitemap.xml\n";

        return response($content)->header('Content-Type', 'text/plain');
    }
}
