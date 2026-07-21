<?php

namespace App\Support;

/**
 * Kerala CBSE Sahodaya standard item catalog (CKSC / state pattern).
 *
 * | Program       | Items | Source                                      |
 * |---------------|-------|---------------------------------------------|
 * | Kalotsav      | 140   | Capital District Sahodaya Tarang items.html |
 * | Sports Meet   | 290+  | Kerala state schools athletics + games (U14/U17/U19) |
 * | Kids Fest     | 71    | CKSC / Sahodaya Kids Fest manual pattern    |
 * | Teacher Fest  | 34    | CKSC Teacher Fest programme pattern         |
 * | English Fest  | 20    | English literary & speech items             |
 * | Science Fest  | 17    | Science quiz, exhibition & models           |
 */
class FestItemCatalog
{
    /** @return list<array<string, mixed>> */
    public static function forEventType(string $eventType): array
    {
        return match ($eventType) {
            'kalolsavam'   => self::kalolsavItems(),
            'sports'       => self::sportsItems(),
            'kids_fest'    => self::kidsFestItems(),
            'teacher_fest' => self::teacherFestItems(),
            'english_fest' => self::englishFestItems(),
            'science_fest' => self::scienceFestItems(),
            default        => [],
        };
    }

    /** @return list<array<string, mixed>> */
    public static function mcsKalotsavItems(): array
    {
        return self::loadCatalogRows(
            require __DIR__.'/data/mcs_kalotsav_items.php',
            enrichGroups: true,
            defaults: [
                'owner_level' => 'sahodaya',
                'max_per_school' => 1,
                'qualify_count' => 1,
                'source' => 'mcs',
            ],
        );
    }

    /** @return list<array<string, mixed>> */
    public static function kalolsavItems(): array
    {
        return self::loadCatalogRows(
            require __DIR__.'/data/cksc_kalolsav_items.php',
            enrichGroups: true,
            defaults: [
                'owner_level' => 'sahodaya',
                'max_per_school' => 1,
                'qualify_count' => 2,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function teamSquadRow(
        string $title,
        ?string $ageGroup,
        string $gender,
        string $venue,
        int $minPlaying,
        int $maxSubs,
        int $maxSquad,
        int $standbys = 0,
        string $discipline = 'team_game',
        string $format = 'team',
        string $participantType = 'team',
        ?string $stageType = null,
        ?string $classGroup = null,
    ): array {
        $minSquad = $minPlaying;
        $venueType = in_array($venue, ['indoor', 'outdoor'], true) ? $venue : null;

        return [
            'title'                => $title,
            'class_group'          => $classGroup ?? 'open',
            'age_group'            => $ageGroup,
            'gender'               => $gender,
            'venue_type'           => $venueType,
            'stage_type'           => $stageType,
            'sport_discipline'     => $discipline,
            'competition_format'   => $format,
            'participant_type'     => $participantType,
            'min_group_size'       => $minSquad,
            'max_group_size'       => $maxSquad,
            'criteria_json'        => [
                'min_playing'  => $minPlaying,
                'max_playing'  => $minPlaying,
                'max_subs'     => $maxSubs,
                'max_squad'    => $maxSquad,
                'min_squad'    => $minSquad,
                'standbys'     => $standbys,
                'subs_allowed' => $maxSubs > 0,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $defaults
     * @return list<array<string, mixed>>
     */
    private static function loadCatalogRows(array $rows, bool $enrichGroups = false, array $defaults = []): array
    {
        $items = [];

        foreach ($rows as $row) {
            if ($enrichGroups && ($row['participant_type'] ?? 'individual') === 'group') {
                $min = (int) ($row['min_group_size'] ?? 7);
                $max = (int) ($row['max_group_size'] ?? $min);
                $standbys = (int) ($row['standbys'] ?? 0);
                unset($row['min_group_size'], $row['max_group_size'], $row['standbys']);

                $row = array_merge(
                    self::teamSquadRow(
                        title: $row['title'],
                        ageGroup: $row['age_group'] ?? null,
                        gender: $row['gender'],
                        venue: $row['stage_type'] ?? $row['venue_type'] ?? 'on_stage',
                        minPlaying: $min,
                        maxSubs: 0,
                        maxSquad: $max + $standbys,
                        standbys: $standbys,
                        participantType: 'group',
                        stageType: $row['stage_type'] ?? null,
                        classGroup: $row['class_group'] ?? null,
                    ),
                    $row,
                );
            }

            $items[] = array_merge($defaults, $row);
        }

        return $items;
    }

    /** @return list<array<string, mixed>> */
    public static function sportsItems(): array
    {
        $items = self::loadCatalogRows(
            require __DIR__.'/data/cksc_sports_items.php',
            defaults: [
                'category' => 'sports',
                'owner_level' => 'sahodaya',
                'max_per_school' => 1,
                'qualify_count' => 2,
                'participant_type' => 'individual',
                'class_group' => 'open',
            ],
        );

        return self::expandSportsAgeGenderItems($items);
    }

    /**
     * Expand legacy open-age sports templates into U14 / U17 / U19 (and Boys/Girls where needed).
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function expandSportsAgeGenderItems(array $items): array
    {
        $expanded = [];

        foreach ($items as $row) {
            if (($row['age_group'] ?? '') !== 'open') {
                $expanded[] = $row;

                continue;
            }

            foreach (self::expandSportsCatalogRow($row) as $variant) {
                $expanded[] = $variant;
            }
        }

        return $expanded;
    }

    /** @return list<array<string, mixed>> */
    private static function expandSportsCatalogRow(array $row): array
    {
        $ages = ['u14', 'u17', 'u19'];
        $variants = [];

        foreach ($ages as $age) {
            foreach (self::targetGendersForSportsRow($row) as $gender) {
                $clone = $row;
                $clone['age_group'] = $age;
                $clone['gender'] = $gender;
                $clone['title'] = self::sportsExpandedTitle($row, $age, $gender);

                $variants[] = $clone;
            }
        }

        return $variants;
    }

    /** @return list<string> */
    private static function targetGendersForSportsRow(array $row): array
    {
        $gender = strtolower((string) ($row['gender'] ?? 'open'));
        $format = strtolower((string) ($row['competition_format'] ?? ''));

        if (in_array($gender, ['male', 'female'], true)) {
            return [$gender];
        }

        if ($gender === 'mixed' && ($format === 'mixed_doubles' || str_contains(strtolower($row['title'] ?? ''), 'mixed'))) {
            return ['mixed'];
        }

        return ['male', 'female'];
    }

    private static function sportsExpandedTitle(array $row, string $age, string $gender): string
    {
        $base = trim((string) ($row['title'] ?? 'Item'));
        $agePrefix = strtoupper($age).' — ';

        if (preg_match('/^U\d+\s*—/i', $base)) {
            $agePrefix = '';
        }

        $origGender = strtolower((string) ($row['gender'] ?? 'open'));
        if ($origGender === 'mixed' && $gender !== 'mixed') {
            $label = FestSportsAgeGroup::genderLabel($gender);
            if ($label && ! preg_match('/\b(Boys|Girls|Male|Female)\b/i', $base)) {
                $base = rtrim($base, ' —').' — '.$label;
            }
        }

        return $agePrefix.$base;
    }

    /** @return list<array<string, mixed>> */
    public static function kidsFestItems(): array
    {
        return self::loadCatalogRows(
            require __DIR__.'/data/cksc_kids_fest_items.php',
            enrichGroups: true,
            defaults: [
                'category' => 'general',
                'owner_level' => 'sahodaya',
                'max_per_school' => 1,
                'participant_type' => 'individual',
                'gender' => 'mixed',
                'class_group' => 'open',
            ],
        );
    }

    /** @return list<array<string, mixed>> */
    public static function teacherFestItems(): array
    {
        return self::loadCatalogRows(
            require __DIR__.'/data/cksc_teacher_fest_items.php',
            enrichGroups: true,
            defaults: [
                'owner_level' => 'sahodaya',
                'max_per_school' => 1,
                'participant_type' => 'individual',
                'class_group' => 'open',
                'gender' => 'mixed',
                'stage_type' => 'on_stage',
                'duration_minutes' => 5,
            ],
        );
    }

    /** @return list<array<string, mixed>> */
    public static function englishFestItems(): array
    {
        return self::loadCatalogRows(
            require __DIR__.'/data/mcs_english_fest_items.php',
            enrichGroups: true,
            defaults: [
                'owner_level' => 'sahodaya',
                'max_per_school' => 1,
                'qualify_count' => 1,
                'participant_type' => 'individual',
                'gender' => 'mixed',
            ],
        );
    }

    /** @return list<array<string, mixed>> */
    public static function scienceFestItems(): array
    {
        return self::loadCatalogRows(
            require __DIR__.'/data/cksc_science_fest_items.php',
            enrichGroups: true,
            defaults: [
                'owner_level' => 'sahodaya',
                'max_per_school' => 1,
                'qualify_count' => 2,
                'participant_type' => 'individual',
                'gender' => 'mixed',
            ],
        );
    }
}
