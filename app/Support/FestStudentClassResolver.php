<?php

namespace App\Support;

use App\Models\FestClassCategorySchemeGroup;
use App\Models\FestEvent;
use App\Models\FestEventClassGroup;
use App\Models\Student;
use Illuminate\Support\Collection;

class FestStudentClassResolver
{
    /**
     * Per-request cache of scheme_id => [key => classes]. schemeGroupForStudent() is
     * called once per student (annotateStudents() maps over the whole roster), and a
     * whole event only ever resolves against a handful of distinct scheme ids, so this
     * turns hundreds of identical FestClassCategorySchemeGroup queries into one per
     * scheme_id per request.
     *
     * @var array<int, Collection>
     */
    private static array $schemeGroupsCache = [];

    public static function classNumberFromName(?string $className): ?int
    {
        if (! filled($className)) {
            return null;
        }

        $name = trim($className);

        if (preg_match('/(?:class|std|standard|grade)?\s*(\d{1,2})\b/i', $name, $m)) {
            $n = (int) $m[1];
            if ($n >= 1 && $n <= 12) {
                return $n;
            }
        }

        $romanMap = [
            'XII' => 12, 'XI' => 11, 'X' => 10, 'IX' => 9, 'VIII' => 8,
            'VII' => 7, 'VI' => 6, 'V' => 5, 'IV' => 4, 'III' => 3, 'II' => 2, 'I' => 1,
        ];

        foreach ($romanMap as $roman => $num) {
            if (preg_match('/\b'.preg_quote($roman, '/').'\b/i', $name)) {
                return $num;
            }
        }

        return null;
    }

    public static function classNumberFromStudent(Student $student): ?int
    {
        $className = $student->schoolClass?->name ?? $student->class ?? null;
        if (! filled($className) && $student->exists && ! $student->relationLoaded('schoolClass')) {
            $student->loadMissing('schoolClass');
            $className = $student->schoolClass?->name ?? $student->class ?? null;
        }

        return self::classNumberFromName($className);
    }

    public static function kalolsavClassGroup(?int $classNumber): ?string
    {
        if ($classNumber === null) {
            return null;
        }

        return config("fest_co_curricular.kalolsav.class_to_group.{$classNumber}");
    }

    public static function kalolsavClassGroupForStudent(Student $student): ?string
    {
        return self::kalolsavClassGroup(self::classNumberFromStudent($student));
    }

    public static function clusterClassGroupForStudent(Student $student): ?string
    {
        if (! $student->relationLoaded('schoolClass') && $student->exists) {
            $student->loadMissing('schoolClass');
        }
        $categoryId = (int) ($student->schoolClass?->class_category_id ?? 0);

        return $categoryId > 0 ? FestClassGroupScheme::clusterKey($categoryId) : null;
    }

    public static function normalizedClassFromName(?string $className): ?string
    {
        if (! filled($className)) {
            return null;
        }

        $name = strtolower(trim($className));

        if (preg_match('/(?:pre[\s\-_]?kg|nursery|play\s*group|playgroup)/i', $name)) {
            return 'Pre-KG';
        }

        if (preg_match('/(?:lkg|lower[\s\-_]?kg|lower[\s\-_]?kindergarten|kg[\s\-_]?1)/i', $name)) {
            return 'LKG';
        }

        if (preg_match('/(?:ukg|upper[\s\-_]?kg|upper[\s\-_]?kindergarten|kg[\s\-_]?2)/i', $name)) {
            return 'UKG';
        }

        $num = self::classNumberFromName($className);
        if ($num !== null) {
            return (string) $num;
        }

        return null;
    }

    public static function normalizedClassFromStudent(Student $student): ?string
    {
        $className = $student->schoolClass?->name ?? $student->class ?? null;
        if (! filled($className) && $student->exists && ! $student->relationLoaded('schoolClass')) {
            $student->loadMissing('schoolClass');
            $className = $student->schoolClass?->name ?? $student->class ?? null;
        }

        return self::normalizedClassFromName($className);
    }

    /**
     * Match a student's class against this event's custom FestEventClassGroup
     * rows (each carries its own `classes` array of class identifiers). Falls back to the
     * 'open' catch-all key when no custom category claims this student's class — mirrors
     * how the built-in schemes always keep an 'open' bucket in their labels() output.
     */
    public static function customClassGroupForStudent(Student $student, FestEvent $event): ?string
    {
        $normalized = self::normalizedClassFromStudent($student);
        if ($normalized === null) {
            return null;
        }

        $groups = FestEventClassGroup::where('event_id', $event->id)->get(['key', 'classes']);

        foreach ($groups as $group) {
            $groupClasses = collect($group->classes ?? [])->map(fn ($c) => strtoupper(trim((string) $c)))->all();
            if (in_array(strtoupper($normalized), $groupClasses, true)) {
                return $group->key;
            }
        }

        return 'open';
    }

    /**
     * Match a student's class against a named FestClassCategoryScheme's groups
     * (the scheme's own `classes` arrays), mirroring customClassGroupForStudent() but for
     * the Sahodaya-wide, reusable schemes rather than one-off per-event categories.
     */
    public static function schemeGroupForStudent(Student $student, int $schemeId): ?string
    {
        $normalized = self::normalizedClassFromStudent($student);
        if ($normalized === null) {
            return null;
        }

        $groups = self::$schemeGroupsCache[$schemeId] ??= FestClassCategorySchemeGroup::where('scheme_id', $schemeId)->get(['key', 'classes']);

        foreach ($groups as $group) {
            $groupClasses = collect($group->classes ?? [])->map(fn ($c) => strtoupper(trim((string) $c)))->all();
            if (in_array(strtoupper($normalized), $groupClasses, true)) {
                return $group->key;
            }
        }

        return 'open';
    }

    public static function classGroupForStudent(Student $student, FestEvent $event): ?string
    {
        $event = $event->rootEvent();
        $scheme = FestClassGroupScheme::resolveForEvent($event);

        if (is_string($scheme) && ctype_digit($scheme)) {
            return self::schemeGroupForStudent($student, (int) $scheme);
        }

        if ($scheme === 'cluster') {
            return self::clusterClassGroupForStudent($student);
        }

        if ($scheme === 'custom') {
            return self::customClassGroupForStudent($student, $event);
        }

        return self::kalolsavClassGroupForStudent($student);
    }

    /** A class may belong to both a single-class and combined category. */
    public static function classGroupsForStudent(Student $student, FestEvent $event): array
    {
        $root = $event->rootEvent();
        $scheme = FestClassGroupScheme::resolveForEvent($root);
        $normalized = self::normalizedClassFromStudent($student);
        if ($normalized === null) {
            return [];
        }
        if ($scheme === 'custom') {
            $groups = FestEventClassGroup::where('event_id', $root->id)->get(['key', 'classes']);
        } elseif (is_string($scheme) && ctype_digit($scheme)) {
            $groups = self::$schemeGroupsCache[(int) $scheme] ??= FestClassCategorySchemeGroup::where('scheme_id', (int) $scheme)->get(['key', 'classes']);
        } else {
            return array_values(array_filter([self::classGroupForStudent($student, $root)]));
        }

        return $groups->filter(fn ($group) => collect($group->classes ?? [])->contains(fn ($class) => strtoupper(trim((string) $class)) === strtoupper($normalized)))->pluck('key')->values()->all();
    }

    public static function kidsFestBandForStudent(Student $student): ?string
    {
        $className = strtolower(trim($student->schoolClass?->name ?? ''));

        foreach (config('fest_co_curricular.kids_fest.class_name_patterns', []) as $band => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($className, $pattern)) {
                    return $band;
                }
            }
        }

        $classNum = self::classNumberFromStudent($student);
        if ($classNum === 1) {
            return 'class1';
        }
        if ($classNum === 2) {
            return 'class2';
        }

        return null;
    }

    public static function isKalolsavEligible(Student $student): bool
    {
        $num = self::classNumberFromStudent($student);

        if ($num === null) {
            return false;
        }

        $min = config('fest_co_curricular.kalolsav.min_class', 3);
        $max = config('fest_co_curricular.kalolsav.max_class', 12);

        return $num >= $min && $num <= $max;
    }

    public static function isKidsFestEligible(Student $student): bool
    {
        return self::kidsFestBandForStudent($student) !== null;
    }
}
