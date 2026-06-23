<?php

namespace App\Services;

use App\Models\Tenant;
use App\Support\CkscSiteTemplate;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Imports CMS content from the standalone Sahodaya Laravel app database.
 */
class StandaloneSahodayaImporter
{
    public function __construct(private readonly string $sourcePath) {}

    public function importInto(Tenant $sahodaya): array
    {
        $this->configureConnection();
        $stats = [];

        CkscSiteTemplate::apply($sahodaya, true);

        $stats['sliders'] = $this->importHeroSlides($sahodaya);
        $stats['about'] = $this->importAbout($sahodaya);
        $stats['programmes'] = $this->importServices($sahodaya);
        $stats['core_values'] = $this->importCoreValues($sahodaya);
        $stats['timelines'] = $this->importTimelines($sahodaya);
        $stats['news'] = $this->importNews($sahodaya);
        $stats['menus'] = $this->importMenus($sahodaya);
        $stats['cms_pages'] = $this->importPages($sahodaya);
        $stats['contact'] = $this->importContact($sahodaya);

        return $stats;
    }

    private function configureConnection(): void
    {
        $envPath = rtrim($this->sourcePath, '/').'/.env';
        if (! File::exists($envPath)) {
            throw new RuntimeException("No .env found at {$envPath}. Apply CKSC template defaults instead.");
        }

        $vars = $this->parseEnv($envPath);
        $driver = $vars['DB_CONNECTION'] ?? 'mysql';

        Config::set('database.connections.standalone_import', match ($driver) {
            'sqlite' => [
                'driver'   => 'sqlite',
                'database' => $this->resolveSqlitePath($vars, $envPath),
                'prefix'   => '',
            ],
            default => [
                'driver'   => $driver,
                'host'     => $vars['DB_HOST'] ?? '127.0.0.1',
                'port'     => $vars['DB_PORT'] ?? '3306',
                'database' => $vars['DB_DATABASE'] ?? '',
                'username' => $vars['DB_USERNAME'] ?? '',
                'password' => $vars['DB_PASSWORD'] ?? '',
                'charset'  => 'utf8mb4',
                'collation'=> 'utf8mb4_unicode_ci',
                'prefix'   => '',
            ],
        });

        DB::purge('standalone_import');
        DB::connection('standalone_import')->getPdo();
    }

    /** @return array<string, string> */
    private function parseEnv(string $path): array
    {
        $vars = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $vars[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
        }

        return $vars;
    }

    /** @param array<string, string> $vars */
    private function resolveSqlitePath(array $vars, string $envPath): string
    {
        $db = $vars['DB_DATABASE'] ?? 'database/database.sqlite';
        if ($db === ':memory:' || str_starts_with($db, '/')) {
            return $db;
        }

        return dirname($envPath).'/'.$db;
    }

    private function conn()
    {
        return DB::connection('standalone_import');
    }

    private function tableExists(string $table): bool
    {
        try {
            $this->conn()->table($table)->limit(1)->exists();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function importHeroSlides(Tenant $sahodaya): int
    {
        if (! $this->tableExists('sliders')) {
            return 0;
        }

        $slides = $this->conn()->table('sliders')
            ->where('status', true)
            ->orderBy('sort_order')
            ->get(['title', 'content', 'image_url']);

        if ($slides->isEmpty()) {
            return 0;
        }

        $section = $sahodaya->sections()->where('section_type', 'hero')->first();
        if (! $section) {
            return 0;
        }

        $mapped = $slides->map(fn ($s) => [
            'title'   => $s->title,
            'content' => $s->content,
            'image'   => $this->storageUrl($s->image_url),
        ])->all();

        $config = $section->config ?? [];
        $config['slides'] = $mapped;
        $section->update(['config' => $config]);

        return count($mapped);
    }

    private function importAbout(Tenant $sahodaya): bool
    {
        if (! $this->tableExists('aboutus')) {
            return false;
        }

        $about = $this->conn()->table('aboutus')->where('status', true)->first();
        if (! $about) {
            return false;
        }

        $section = $sahodaya->sections()
            ->where('section_type', 'about_sahodaya')
            ->where('variant', 'single-column')
            ->first();

        if ($section) {
            $config = $section->config ?? [];
            $config['heading'] = $about->title ?? $config['heading'] ?? '';
            $config['content'] = $about->content ?? $config['content'] ?? '';
            $section->update(['config' => $config]);
        }

        $pages = $sahodaya->getSetting('cms_pages', []);
        $pages['about']['content_html'] = $about->content ?? ($pages['about']['content_html'] ?? '');
        $sahodaya->setSetting('cms_pages', $pages);

        return true;
    }

    private function importServices(Tenant $sahodaya): int
    {
        if (! $this->tableExists('services')) {
            return 0;
        }

        $services = $this->conn()->table('services')
            ->where('status', true)
            ->orderBy('sort_order')
            ->get(['title', 'description', 'image_url']);

        if ($services->isEmpty()) {
            return 0;
        }

        $section = $sahodaya->sections()->where('section_type', 'programmes')->first();
        if (! $section) {
            return 0;
        }

        $programmes = $services->map(fn ($s) => [
            'label'       => $s->title,
            'description' => $s->description,
            'icon'        => '📌',
            'url'         => '#',
            'image'       => $this->storageUrl($s->image_url),
        ])->all();

        $config = $section->config ?? [];
        $config['programmes'] = $programmes;
        $section->update(['config' => $config]);

        return count($programmes);
    }

    private function importCoreValues(Tenant $sahodaya): int
    {
        if (! $this->tableExists('core_values')) {
            return 0;
        }

        $values = $this->conn()->table('core_values')
            ->where('status', true)
            ->orderBy('sort_order')
            ->get(['type', 'content']);

        if ($values->isEmpty()) {
            return 0;
        }

        $section = $sahodaya->sections()
            ->where('section_type', 'about_sahodaya')
            ->where('variant', 'vision-mission')
            ->first();

        if (! $section) {
            return 0;
        }

        $config = $section->config ?? [];
        foreach ($values as $v) {
            $type = strtolower($v->type ?? '');
            if (str_contains($type, 'vision')) {
                $config['vision'] = $v->content;
            } elseif (str_contains($type, 'mission')) {
                $config['mission'] = $v->content;
            } else {
                $config['values'] = array_merge($config['values'] ?? [], [$v->content]);
            }
        }
        $section->update(['config' => $config]);

        return $values->count();
    }

    private function importTimelines(Tenant $sahodaya): int
    {
        if (! $this->tableExists('timelines')) {
            return 0;
        }

        $rows = $this->conn()->table('timelines')
            ->where('status', true)
            ->orderBy('sort_order')
            ->get(['event_date', 'description', 'title']);

        if ($rows->isEmpty()) {
            return 0;
        }

        $section = $sahodaya->sections()
            ->where('section_type', 'about_sahodaya')
            ->where('variant', 'with-timeline')
            ->first();

        if (! $section) {
            return 0;
        }

        $milestones = $rows->map(fn ($r) => [
            'year'        => $r->event_date ? date('Y', strtotime($r->event_date)) : '',
            'title'       => $r->title ?? '',
            'description' => $r->description ?? '',
        ])->all();

        $config = $section->config ?? [];
        $config['milestones'] = $milestones;
        $section->update(['config' => $config]);

        return count($milestones);
    }

    private function importNews(Tenant $sahodaya): int
    {
        if (! $this->tableExists('news')) {
            return 0;
        }

        return (int) $this->conn()->table('news')->where('status', true)->count();
    }

    private function importMenus(Tenant $sahodaya): int
    {
        if (! $this->tableExists('menus')) {
            return 0;
        }

        $parents = $this->conn()->table('menus')
            ->whereNull('parent_id')
            ->where('status', true)
            ->orderBy('sort_order')
            ->get();

        if ($parents->isEmpty()) {
            return 0;
        }

        $items = [];
        foreach ($parents as $parent) {
            $children = $this->conn()->table('menus')
                ->where('parent_id', $parent->id)
                ->where('status', true)
                ->orderBy('sort_order')
                ->get();

            $childItems = [];
            foreach ($children as $child) {
                $url = $child->url;
                if (! $url && $child->page_id) {
                    $page = $this->conn()->table('pages')->where('id', $child->page_id)->first();
                    $url = $page ? '/page/'.strtolower(str_replace(' ', '-', $page->title)) : '#';
                }
                $childItems[] = [
                    'label'    => $child->name,
                    'url'      => str_starts_with($url ?? '', '/') ? $url : '/'.ltrim($url ?? '#', '/'),
                    'external' => false,
                ];
            }

            $items[] = [
                'label'    => $parent->name,
                'url'      => $parent->url ?: (empty($childItems) ? '/' : '#'),
                'external' => false,
                'children' => $childItems,
            ];
        }

        $nav = $sahodaya->getSetting('nav_config', []);
        $nav['items'] = $items;
        $nav['layout_variant'] = 'cksc-pill';
        $nav['style'] = 'cksc-pill';
        $sahodaya->setSetting('nav_config', $nav);

        return count($items);
    }

    private function importPages(Tenant $sahodaya): int
    {
        if (! $this->tableExists('pages')) {
            return 0;
        }

        $pages = $this->conn()->table('pages')->where('status', true)->get(['title', 'content']);
        $cms = $sahodaya->getSetting('cms_pages', []);

        foreach ($pages as $page) {
            $slug = 'page/'.strtolower(str_replace(' ', '-', $page->title));
            $cms[$slug] = [
                'title'        => $page->title,
                'subtitle'     => $page->title,
                'content_html' => $page->content,
            ];
        }

        $sahodaya->setSetting('cms_pages', $cms);

        return $pages->count();
    }

    private function importContact(Tenant $sahodaya): bool
    {
        if (! $this->tableExists('contact_details')) {
            return false;
        }

        $contact = $this->conn()->table('contact_details')->where('status', true)->first();
        if (! $contact) {
            return false;
        }

        $footer = $sahodaya->getSetting('footer_config', []);
        $footer['phone'] = $contact->phone ?? $footer['phone'] ?? null;
        $footer['email'] = $contact->email ?? $footer['email'] ?? null;
        $footer['address'] = $contact->address ?? $footer['address'] ?? null;
        $sahodaya->setSetting('footer_config', $footer);

        $section = $sahodaya->sections()->where('section_type', 'contact')->first();
        if ($section) {
            $config = $section->config ?? [];
            $config['phone'] = $contact->phone ?? $config['phone'] ?? null;
            $config['email'] = $contact->email ?? $config['email'] ?? null;
            $config['address'] = $contact->address ?? $config['address'] ?? null;
            $config['map_embed'] = $contact->map_embed ?? $config['map_embed'] ?? null;
            $section->update(['config' => $config]);
        }

        return true;
    }

    private function storageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return '/storage/'.ltrim($path, '/');
    }
}
