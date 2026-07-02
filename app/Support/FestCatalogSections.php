<?php

namespace App\Support;

/**
 * Master catalog browse sections — one page per group (sports discipline, kalotsav category, etc.).
 */
class FestCatalogSections
{
    /** @return list<array{slug: string, label: string, description: string, filter: array<string, string>}> */
    public static function forEventType(string $eventType): array
    {
        return match ($eventType) {
            'sports' => [
                ['slug' => 'track', 'label' => 'Track & cross country', 'description' => 'Running events U8–U19', 'filter' => ['sport_discipline' => 'track']],
                ['slug' => 'field', 'label' => 'Field events', 'description' => 'Jump, throw, vault', 'filter' => ['sport_discipline' => 'field']],
                ['slug' => 'relay', 'label' => 'Relays', 'description' => '4×100m and 4×400m teams', 'filter' => ['sport_discipline' => 'relay']],
                ['slug' => 'team-games', 'label' => 'Team games', 'description' => 'Football, cricket, kabaddi, etc.', 'filter' => ['sport_discipline' => 'team_game']],
                ['slug' => 'racket', 'label' => 'Racket sports', 'description' => 'Badminton, tennis, table tennis', 'filter' => ['sport_discipline' => 'racket']],
                ['slug' => 'board-games', 'label' => 'Board games', 'description' => 'Chess, carrom', 'filter' => ['sport_discipline' => 'board_game']],
                ['slug' => 'martial-arts', 'label' => 'Martial arts', 'description' => 'Judo, taekwondo, wrestling', 'filter' => ['sport_discipline' => 'martial_arts']],
                ['slug' => 'aquatics', 'label' => 'Swimming / aquatics', 'description' => 'Pool events by age', 'filter' => ['sport_discipline' => 'aquatics']],
                ['slug' => 'gymnastics', 'label' => 'Gymnastics', 'description' => 'Artistic gymnastics', 'filter' => ['sport_discipline' => 'gymnastics']],
            ],
            'kalolsavam' => [
                ['slug' => 'cat-1', 'label' => 'Category I (LP)', 'description' => 'Classes III & IV', 'filter' => ['class_group' => 'lp']],
                ['slug' => 'cat-2', 'label' => 'Category II (UP)', 'description' => 'Classes V–VII', 'filter' => ['class_group' => 'up']],
                ['slug' => 'cat-3', 'label' => 'Category III (HS)', 'description' => 'Classes VIII–X', 'filter' => ['class_group' => 'hs']],
                ['slug' => 'cat-4', 'label' => 'Category IV (HSS)', 'description' => 'Classes XI & XII', 'filter' => ['class_group' => 'hss']],
                ['slug' => 'group', 'label' => 'Group items', 'description' => 'Codes 501–511', 'filter' => ['class_group' => 'open']],
            ],
            'kids_fest' => [
                ['slug' => 'pre-kg', 'label' => 'Pre-KG', 'description' => 'Play school / nursery', 'filter' => ['kids_band' => 'pre_kg']],
                ['slug' => 'lkg', 'label' => 'LKG', 'description' => 'Lower kindergarten', 'filter' => ['kids_band' => 'lkg']],
                ['slug' => 'ukg', 'label' => 'UKG', 'description' => 'Upper kindergarten', 'filter' => ['kids_band' => 'ukg']],
                ['slug' => 'class-1', 'label' => 'Class 1', 'description' => 'Kiddies band', 'filter' => ['kids_band' => 'class1']],
                ['slug' => 'class-2', 'label' => 'Class 2', 'description' => 'Kiddies band', 'filter' => ['kids_band' => 'class2']],
            ],
            'teacher_fest' => [
                ['slug' => 'on-stage', 'label' => 'On stage', 'description' => 'Music, dance, drama, speech', 'filter' => ['stage_type' => 'on_stage', 'participant_type' => 'individual']],
                ['slug' => 'off-stage', 'label' => 'Off stage', 'description' => 'Drawing, painting, essay', 'filter' => ['stage_type' => 'off_stage']],
                ['slug' => 'group', 'label' => 'Group items', 'description' => 'Group song, dance, mime', 'filter' => ['participant_type' => 'group']],
            ],
            default => [],
        };
    }

    /** @return array{slug: string, label: string, description: string, filter: array<string, string>}|null */
    public static function find(string $eventType, string $slug): ?array
    {
        foreach (self::forEventType($eventType) as $section) {
            if ($section['slug'] === $slug) {
                return $section;
            }
        }

        return null;
    }

    /** @param  array<string, string>  $filter */
    public static function applyFilter($query, array $filter): void
    {
        foreach ($filter as $column => $value) {
            if ($column === 'participant_type' && $value === 'individual') {
                $query->where(function ($q) {
                    $q->where('participant_type', 'individual')->orWhereNull('participant_type');
                });

                continue;
            }

            $query->where($column, $value);
        }
    }

    /**
     * @return list<array{slug: string, label: string, description: string, total: int, enabled: int}>
     */
    public static function summaries(string $tenantId, string $eventType): array
    {
        $sections = [];
        $base = \App\Models\FestCatalogItem::forProgram($tenantId, $eventType);

        foreach (self::forEventType($eventType) as $section) {
            $q = clone $base;
            self::applyFilter($q, $section['filter']);
            $sections[] = [
                'slug'        => $section['slug'],
                'label'       => $section['label'],
                'description' => $section['description'],
                'total'       => (clone $q)->count(),
                'enabled'     => (clone $q)->where('is_enabled', true)->count(),
            ];
        }

        return $sections;
    }
}
