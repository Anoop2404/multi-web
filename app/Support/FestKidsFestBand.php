<?php

namespace App\Support;

class FestKidsFestBand
{
    /** @var list<string> */
    public const KEYS = ['pre_kg', 'lkg', 'ukg', 'class1', 'class2', 'open'];

    /** @return array<string, string> */
    public static function labels(): array
    {
        return config('fest_co_curricular.kids_fest.bands', []);
    }

    public static function isValid(?string $band): bool
    {
        return filled($band) && in_array($band, self::KEYS, true);
    }

    public static function resolveForItem(?string $kidsBand): ?string
    {
        return self::isValid($kidsBand) ? $kidsBand : null;
    }
}
