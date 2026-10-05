<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\ValidationException;

class SectionConfigValidator
{
    /**
     * Validate a section config payload against the fields declared in config/sections.php
     * for the given section type/variant. Returns the cleaned config array; throws on failure.
     */
    public static function validate(string $sectionType, string $variant, array $config): array
    {
        $fields = SectionFieldRegistry::fields($sectionType, $variant);
        if (! $fields) {
            return $config;
        }

        $rules = [];
        $attributeLabels = [];
        foreach ($fields as $field) {
            $rules[$field['key']] = self::rulesForField($field);
            $attributeLabels[$field['key']] = $field['label'] ?? $field['key'];
        }

        $validator = ValidatorFacade::make($config, $rules, [
            'date' => 'The :attribute must be a valid date (YYYY-MM-DD).',
            'date_format' => 'The :attribute must be a valid date (YYYY-MM-DD).',
            'email' => 'The :attribute must be a valid email address.',
            'url' => 'The :attribute must be a valid URL.',
            'integer' => 'The :attribute must be an integer.',
            'numeric' => 'The :attribute must be a number.',
            'boolean' => 'The :attribute must be true or false.',
            'array' => 'The :attribute must be a list.',
        ], $attributeLabels);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        return self::normalizeRepeaterItems($fields, $validated);
    }

    private static function rulesForField(array $field): array
    {
        $type = $field['type'] ?? 'text';
        $rules = [];

        if (! empty($field['required']) && $type !== 'switch' && $type !== 'checkbox') {
            $rules[] = 'required';
        } elseif (array_key_exists('required', $field) && ! $field['required']) {
            $rules[] = 'nullable';
        } else {
            $rules[] = 'nullable';
        }

        switch ($type) {
            case 'email':
                $rules[] = 'email:rfc';
                break;
            case 'tel':
                $rules[] = 'string|regex:/^[\d\s+()\-]+$/';
                break;
            case 'url':
                $rules[] = 'url';
                break;
            case 'number':
                $rules[] = 'numeric';
                if (isset($field['min'])) {
                    $rules[] = 'min:' . $field['min'];
                }
                if (isset($field['max'])) {
                    $rules[] = 'max:' . $field['max'];
                }
                break;
            case 'integer':
                $rules[] = 'integer';
                if (isset($field['min'])) {
                    $rules[] = 'min:' . $field['min'];
                }
                if (isset($field['max'])) {
                    $rules[] = 'max:' . $field['max'];
                }
                break;
            case 'boolean':
            case 'switch':
            case 'checkbox':
                $rules[] = 'boolean';
                break;
            case 'date':
                $rules[] = 'date_format:Y-m-d';
                break;
            case 'time':
                $rules[] = 'date_format:H:i';
                break;
            case 'date_range':
                $rules[] = 'array';
                $fromRules = ['nullable', 'date_format:Y-m-d'];
                $toRules = ['nullable', 'date_format:Y-m-d', 'after_or_equal:' . ($field['key'] ?? '') . '.from'];
                $rules["{$field['key']}.from"] = $fromRules;
                $rules["{$field['key']}.to"] = $toRules;
                break;
            case 'academic_years':
                $rules[] = 'string|regex:/^\d{4}-\d{4}$/';
                break;
            case 'select':
                if (! empty($field['options'])) {
                    $values = array_map(
                        fn ($o) => is_array($o) ? ($o['value'] ?? '') : $o,
                        $field['options']
                    );
                    $rules[] = 'in:' . implode(',', array_filter($values, fn ($v) => $v !== ''));
                } else {
                    $rules[] = 'string';
                }
                break;
            case 'multiselect':
                $rules[] = 'array';
                $rules["{$field['key']}.*"] = ['string'];
                break;
            case 'repeater':
                $rules[] = 'array';
                if (! empty($field['fields'])) {
                    foreach ($field['fields'] as $subField) {
                        $subRules = self::rulesForField($subField);
                        $rules["{$field['key']}.*"] = ['array'];
                        $rules["{$field['key']}.*.{$subField['key']}"] = $subRules;
                    }
                    $rules["{$field['key']}.*.\\_enabled"] = ['nullable', 'boolean'];
                    $rules["{$field['key']}.*.\\_featured"] = ['nullable', 'boolean'];
                    $rules["{$field['key']}.*.\\_start_date"] = ['nullable', 'date_format:Y-m-d'];
                    $rules["{$field['key']}.*.\\_end_date"] = ['nullable', 'date_format:Y-m-d', 'after_or_equal:' . ($field['key'] ?? '') . '.*._start_date'];
                }
                break;
            case 'color':
                $rules[] = 'string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/';
                break;
            case 'media':
                $rules[] = 'string';
                break;
            case 'textarea':
            case 'text':
            case 'wysiwyg':
            default:
                $rules[] = 'string';
                if (isset($field['maxLength'])) {
                    $rules[] = 'max:' . $field['maxLength'];
                }
                break;
        }

        return $rules;
    }

    private static function normalizeRepeaterItems(array $fields, array $validated): array
    {
        foreach ($fields as $field) {
            $key = $field['key'] ?? null;
            if (! $key || ($field['type'] ?? null) !== 'repeater') {
                continue;
            }
            if (! isset($validated[$key]) || ! is_array($validated[$key])) {
                continue;
            }
            $validated[$key] = array_values(array_filter(
                $validated[$key],
                fn ($item) => is_array($item) && ! empty(array_filter($item, fn ($v) => $v !== null && $v !== '' && $v !== []))
            ));
        }
        return $validated;
    }
}