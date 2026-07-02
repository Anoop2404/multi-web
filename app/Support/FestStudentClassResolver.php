<?php

namespace App\Support;

use App\Models\Student;

class FestStudentClassResolver
{
    public static function classNumberFromName(?string $className): ?int
    {
        if (! filled($className)) {
            return null;
        }

        $name = trim($className);
        if (preg_match('/^(?:class\s*)?(\d{1,2})\b/i', $name, $m)) {
            $n = (int) $m[1];

            return ($n >= 1 && $n <= 12) ? $n : null;
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
