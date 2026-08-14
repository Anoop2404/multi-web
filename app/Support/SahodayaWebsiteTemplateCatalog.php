<?php

namespace App\Support;

use Illuminate\Support\Arr;
use InvalidArgumentException;

class SahodayaWebsiteTemplateCatalog
{
    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return config('sahodaya_website_templates', []);
    }

    /** @return array<string, mixed> */
    public static function get(string $key): array
    {
        $template = self::all()[$key] ?? null;

        if (! is_array($template)) {
            throw new InvalidArgumentException("Unknown Sahodaya website experience [{$key}].");
        }

        SahodayaWebsiteTemplateValidator::validate($key, $template);

        return $template;
    }

    /** @return list<array<string, mixed>> */
    public static function summaries(): array
    {
        return collect(self::all())->map(function (array $template, string $key) {
            SahodayaWebsiteTemplateValidator::validate($key, $template);

            return [
                'key' => $key,
                'name' => $template['name'],
                'version' => $template['version'],
                'purpose' => $template['purpose'],
                'audience' => $template['audience'],
                'character' => $template['character'],
                'accent' => $template['accent'] ?? null,
                'design' => $template['design'],
                'sections' => collect($template['sections'])->map(fn (array $section) => [
                    'section_type' => $section['section_type'],
                    'variant' => $section['variant'],
                ])->values()->all(),
            ];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    public static function widgetPolicy(?string $key): array
    {
        return $key && isset(self::all()[$key])
            ? Arr::get(self::all(), "{$key}.widgets", [])
            : [];
    }
}
