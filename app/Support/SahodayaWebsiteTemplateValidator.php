<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class SahodayaWebsiteTemplateValidator
{
    /**
     * @param  array<string, mixed>  $template
     * @param  class-string  $catalog  Section-type/variant allow-list to validate against —
     *                                  SahodayaSiteBuilderCatalog or SchoolSiteBuilderCatalog.
     */
    public static function validate(string $key, array $template, string $catalog = SahodayaSiteBuilderCatalog::class): void
    {
        $errors = [];

        foreach (['name', 'version', 'purpose', 'audience', 'character', 'design', 'sections'] as $required) {
            if (! array_key_exists($required, $template)) {
                $errors[] = "{$key} is missing {$required}.";
            }
        }

        foreach ($template['sections'] ?? [] as $index => $section) {
            $type = $section['section_type'] ?? '';
            $variant = $section['variant'] ?? '';
            if (! $catalog::allows($type, $variant)) {
                $errors[] = "{$key} section ".($index + 1)." references unsupported {$type}/{$variant}.";
            }
        }

        $design = $template['design'] ?? [];
        foreach (['primary', 'secondary', 'accent_color'] as $colour) {
            if (! preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($design[$colour] ?? ''))) {
                $errors[] = "{$key} has an invalid {$colour}.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['template' => $errors]);
        }
    }
}
