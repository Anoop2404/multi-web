<?php

namespace App\Support;

use App\Models\FestEvent;
use App\Models\Student;

class FestStudentClassResolver
{
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
        return self::classNumberFromName($student->schoolClass?->name);
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

    /**
     * Match a student's class number against this event's custom FestEventClassGroup
     * rows (each carries its own `classes` array of class numbers). Falls back to the
     * 'open' catch-all key when no custom category claims this student's class — mirrors
     * how the built-in schemes always keep an 'open' bucket in their labels() output.
     */
    public static function customClassGroupForStudent(Student $student, FestEvent $event): ?string
    {
        $classNumber = self::classNumberFromStudent($student);
        if ($classNumber === null) {
            return null;
        }

        $groups = \App\Models\FestEventClassGroup::where('event_id', $event->id)->get(['key', 'classes']);

        foreach ($groups as $group) {
            if (in_array($classNumber, array_map('intval', $group->classes ?? []), true)) {
                return $group->key;
            }
        }

        return 'open';
    }

    /**
     * Match a student's class number against a named FestClassCategoryScheme's groups
     * (the scheme's own `classes` arrays), mirroring customClassGroupForStudent() but for
     * the Sahodaya-wide, reusable schemes rather than one-off per-event categories.
     */
    public static function schemeGroupForStudent(Student $student, int $schemeId): ?string
    {
        $classNumber = self::classNumberFromStudent($student);
        if ($classNumber === null) {
            return null;
        }

        $groups = \App\Models\FestClassCategorySchemeGroup::where('scheme_id', $schemeId)->get(['key', 'classes']);

        foreach ($groups as $group) {
            if (in_array($classNumber, array_map('intval', $group->classes ?? []), true)) {
                return $group->key;
            }
        }

        return 'open';
    }

    public static function classGroupForStudent(Student $student, FestEvent $event): ?string
    {
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
