<?php

namespace App\Services\Website;

use App\Models\SiteSection;
use App\Models\Tenant;
use App\Models\WebsiteSite;
use App\Models\WebsiteSiteVersion;
use App\Support\HtmlSanitizer;
use App\Support\SahodayaTenantBranding;
use App\Support\SahodayaWebsiteTemplateCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SahodayaTemplateApplier
{
    /** @return array<string, mixed> */
    public function applyDraft(Tenant $tenant, WebsiteSite $site, string $templateKey, string $mode = 'full'): array
    {
        $template = SahodayaWebsiteTemplateCatalog::get($templateKey);
        $context = SahodayaTenantBranding::context($tenant);
        $sections = $mode === 'style'
            ? $this->snapshotSections($site, public: true)
            : $this->hydrate($template['sections'], $context);

        $draft = [
            'template_key' => $templateKey,
            'template_version' => $template['version'],
            'mode' => $mode,
            'design' => $template['design'],
            'widgets' => $template['widgets'] ?? [],
            'sections' => $sections,
            'created_at' => now()->toIso8601String(),
            'created_by' => auth()->id(),
        ];

        $site->update(['draft_template_json' => $draft]);

        return $draft;
    }

    public function cancelDraft(WebsiteSite $site): void
    {
        $site->update(['draft_template_json' => null]);
    }

    /** @return Collection<int, SiteSection> */
    public function previewSections(WebsiteSite $site): Collection
    {
        $draft = $site->draft_template_json;
        if (empty($draft['sections'])) {
            return $site->sectionQuery()->active()->get();
        }

        return collect($draft['sections'])->map(function (array $recipe, int $index) use ($site) {
            $section = new SiteSection([
                'tenant_id' => $site->tenant_id,
                'site_id' => $site->id,
                'section_type' => $recipe['section_type'],
                'variant' => $recipe['variant'],
                'display_order' => $index,
                'is_active' => $recipe['is_active'] ?? true,
                'status' => SiteSection::STATUS_DRAFT,
                'config' => $recipe['config'] ?? [],
                'layout_json' => $recipe['layout'] ?? $recipe['layout_json'] ?? [],
            ]);
            $section->id = 'draft-'.($index + 1);

            return $section;
        });
    }

    public function publishDraft(WebsiteSite $site): WebsiteSite
    {
        $draft = $site->draft_template_json;
        abort_if(empty($draft['sections']) || empty($draft['template_key']), 422, 'Apply an experience draft before publishing.');

        return DB::transaction(function () use ($site, $draft) {
            $this->recordSnapshot($site, 'before_template_publish');

            if (($draft['mode'] ?? 'full') !== 'style') {
                $this->deleteSections($site);
                foreach ($draft['sections'] as $order => $recipe) {
                    $config = HtmlSanitizer::sanitizeConfig($recipe['config'] ?? []);
                    $layout = $this->validatedLayout($recipe['layout'] ?? $recipe['layout_json'] ?? []);
                    SiteSection::create([
                        'tenant_id' => $site->tenant_id,
                        'site_id' => $site->id,
                        'section_type' => $recipe['section_type'],
                        'variant' => $recipe['variant'],
                        'display_order' => $order,
                        'is_active' => $recipe['is_active'] ?? true,
                        'status' => SiteSection::STATUS_PUBLISHED,
                        'config' => $config,
                        'published_config' => $config,
                        'layout_json' => $layout,
                        'published_layout_json' => $layout,
                        'published_at' => now(),
                        'updated_by' => auth()->id(),
                    ]);
                }
            }

            $site->update([
                'template_key' => $draft['template_key'],
                'template_version' => $draft['template_version'] ?? null,
                'experience_version' => 'v2',
                'design_json' => $draft['design'] ?? [],
                'draft_template_json' => null,
            ]);

            return $site->fresh();
        });
    }

    public function restore(WebsiteSite $site, WebsiteSiteVersion $version): WebsiteSite
    {
        abort_unless($version->website_site_id === $site->id, 404);
        $snapshot = $version->snapshot_json;

        return DB::transaction(function () use ($site, $snapshot) {
            $this->recordSnapshot($site, 'before_restore');
            $this->deleteSections($site);

            foreach ($snapshot['sections'] ?? [] as $order => $section) {
                SiteSection::create([
                    'tenant_id' => $site->tenant_id,
                    'site_id' => $site->id,
                    'section_type' => $section['section_type'],
                    'variant' => $section['variant'],
                    'display_order' => $section['display_order'] ?? $order,
                    'is_active' => $section['is_active'] ?? true,
                    'status' => SiteSection::STATUS_PUBLISHED,
                    'config' => $section['config'] ?? [],
                    'published_config' => $section['published_config'] ?? $section['config'] ?? [],
                    'layout_json' => $section['layout_json'] ?? [],
                    'published_layout_json' => $section['published_layout_json'] ?? $section['layout_json'] ?? [],
                    'published_at' => now(),
                    'updated_by' => auth()->id(),
                ]);
            }

            $meta = $snapshot['site'] ?? [];
            $site->update([
                'template_key' => $meta['template_key'] ?? null,
                'template_version' => $meta['template_version'] ?? null,
                'experience_version' => $meta['experience_version'] ?? 'v1',
                'homepage_mode' => $meta['homepage_mode'] ?? 'evergreen',
                'homepage_mode_override_until' => $meta['homepage_mode_override_until'] ?? null,
                'design_json' => $meta['design_json'] ?? [],
                'draft_template_json' => null,
            ]);

            return $site->fresh();
        });
    }

    public function recordSnapshot(WebsiteSite $site, string $action): WebsiteSiteVersion
    {
        return WebsiteSiteVersion::create([
            'website_site_id' => $site->id,
            'action' => $action,
            'template_key' => $site->template_key,
            'template_version' => $site->template_version,
            'snapshot_json' => [
                'site' => $site->only(['template_key', 'template_version', 'experience_version', 'homepage_mode', 'homepage_mode_override_until', 'design_json']),
                'sections' => $this->snapshotSections($site, public: true),
            ],
            'created_by' => auth()->id(),
            'created_at' => now(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function snapshotSections(WebsiteSite $site, bool $public): array
    {
        return $site->sectionQuery()->orderBy('display_order')->get()->map(function (SiteSection $section) use ($public) {
            return [
                'section_type' => $section->section_type,
                'variant' => $section->variant,
                'display_order' => $section->display_order,
                'is_active' => $section->is_active,
                'config' => $public ? $section->publicConfig() : ($section->config ?? []),
                'published_config' => $section->published_config,
                'layout' => $public ? $section->publicLayout() : ($section->layout_json ?? []),
                'layout_json' => $section->layout_json,
                'published_layout_json' => $section->published_layout_json,
            ];
        })->values()->all();
    }

    /** @param mixed $value */
    private function hydrate($value, array $context)
    {
        if (is_array($value)) {
            return collect($value)->map(fn ($item) => $this->hydrate($item, $context))->all();
        }
        if (! is_string($value)) {
            return $value;
        }

        return str_replace(
            ['{{name}}', '{{short_name}}', '{{region}}'],
            [$context['name'], $context['short_name'], $context['region']],
            $value,
        );
    }

    /** @return array<string, string> */
    private function validatedLayout(array $layout): array
    {
        $allowed = [
            'width' => ['narrow', 'standard', 'wide', 'full'],
            'spacing' => ['compact', 'standard', 'spacious'],
            'surface' => ['canvas', 'muted', 'primary', 'dark', 'image'],
            'heading_alignment' => ['left', 'center'],
            'media_treatment' => ['natural', 'framed', 'editorial', 'edge-to-edge'],
        ];

        return collect($allowed)->mapWithKeys(function (array $values, string $key) use ($layout) {
            $value = $layout[$key] ?? $values[0];
            return [$key => in_array($value, $values, true) ? $value : $values[0]];
        })->all();
    }

    private function deleteSections(WebsiteSite $site): void
    {
        $ids = $site->sectionQuery()->pluck('id');
        if ($ids->isNotEmpty()) {
            \App\Models\SiteSectionVersion::whereIn('site_section_id', $ids)->delete();
            $site->sectionQuery()->delete();
        }
    }
}
