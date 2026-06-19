<?php

namespace App\Support;

class SectionFieldRegistry
{
    public static function all(): array
    {
        return config('sections', []);
    }

    public static function fields(string $sectionType, string $variant): array
    {
        return config("sections.{$sectionType}.{$variant}.fields", []);
    }

    public static function definition(string $sectionType, string $variant): ?array
    {
        return config("sections.{$sectionType}.{$variant}");
    }
}
