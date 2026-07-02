<?php

namespace App\Support;

class FestConductLevels
{
    /** @var list<string> */
    public const ALL = ['state', 'sahodaya', 'school'];

    /** @return list<string> */
    public static function allowedFor(string $eventType): array
    {
        return self::ALL;
    }

    /** @return list<string> */
    public static function defaultsFor(string $eventType): array
    {
        return $eventType === 'sports' ? ['sahodaya', 'school'] : ['state', 'sahodaya'];
    }

    /** @param  list<string>  $levels */
    public static function normalize(array $levels, string $eventType): array
    {
        $allowed = self::allowedFor($eventType);

        return array_values(array_unique(array_filter(
            $levels,
            fn ($level) => in_array($level, $allowed, true)
        )));
    }

    public static function isAllowed(string $level, string $eventType): bool
    {
        return in_array($level, self::allowedFor($eventType), true);
    }

    /** @return array<string, string> */
    public static function labelsFor(string $eventType): array
    {
        $all = \App\Models\FestStateProgram::levelLabels();

        return array_intersect_key($all, array_flip(self::allowedFor($eventType)));
    }
}
