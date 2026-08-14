<?php

namespace App\Services\Website;

use App\Models\Tenant;
use App\Models\WebsiteSite;
use App\Support\SahodayaSiteBuilderCatalog;
use App\Support\SahodayaTenantBranding;
use App\Support\SahodayaWebsiteTemplateCatalog;

class SahodayaContentReadiness
{
    /** @return array{ready: bool, errors: list<string>, warnings: list<string>, score: int} */
    public function inspect(Tenant $tenant, WebsiteSite $site): array
    {
        $errors = [];
        $warnings = [];
        $context = SahodayaTenantBranding::context($tenant);
        $draft = $site->draft_template_json;
        $sections = $draft['sections'] ?? $site->sectionQuery()->orderBy('display_order')->get()->map(fn ($section) => [
            'section_type' => $section->section_type,
            'variant' => $section->variant,
            'config' => $section->config ?? [],
        ])->all();

        if (blank($context['name'] ?? null)) $errors[] = 'Add the organization name.';
        if (blank($context['logo'] ?? null)) $errors[] = 'Add an organization logo.';
        if (blank($context['phone'] ?? null) && blank($context['email'] ?? null)) $errors[] = 'Add at least one public contact method.';
        if ($sections === []) $errors[] = 'Add at least one homepage section.';

        foreach ($sections as $index => $section) {
            $type = $section['section_type'] ?? '';
            $variant = $section['variant'] ?? '';
            if (! SahodayaSiteBuilderCatalog::allows($type, $variant)) {
                $errors[] = 'Section '.($index + 1).' uses an unsupported layout.';
            }
            $text = strtolower(json_encode($section['config'] ?? [], JSON_UNESCAPED_UNICODE) ?: '');
            foreach (['lorem ipsum', 'john doe', 'sample testimonial', 'your school name'] as $placeholder) {
                if (str_contains($text, $placeholder)) {
                    $errors[] = 'Remove placeholder content from section '.($index + 1).'.';
                    break;
                }
            }
            if ($type === 'hero') {
                $config = $section['config'] ?? [];
                if (blank($config['heading'] ?? $config['title'] ?? null)) $errors[] = 'Give the hero section an accessible heading.';
                if (! empty($config['primary_label']) && blank($config['primary_url'] ?? null)) $errors[] = 'Add a destination for the primary hero action.';
            }
        }

        $design = $draft['design'] ?? $site->design_json ?? [];
        if ($design && ! $this->hasUsableContrast($design['primary'] ?? '#1e40af')) {
            $errors[] = 'Choose a darker primary colour so white text remains readable.';
        }
        if (count($sections) < 5) $warnings[] = 'A fuller homepage usually needs at least five purposeful sections.';
        if (blank($site->seo_json['description'] ?? null)) $warnings[] = 'Add a search description for this website.';
        if ($site->template_key && ! array_key_exists($site->template_key, SahodayaWebsiteTemplateCatalog::all())) {
            $errors[] = 'The selected experience is no longer available.';
        }

        return [
            'ready' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'score' => max(0, 100 - count($errors) * 20 - count($warnings) * 5),
        ];
    }

    private function hasUsableContrast(string $hex): bool
    {
        if (! preg_match('/^#([0-9a-f]{6})$/i', $hex, $matches)) return false;
        $rgb = array_map('hexdec', str_split($matches[1], 2));
        $luminance = (0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2]) / 255;
        return $luminance <= 0.55;
    }
}
