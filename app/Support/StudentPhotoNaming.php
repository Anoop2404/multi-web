<?php

namespace App\Support;

use App\Models\Student;
use Illuminate\Support\Collection;

class StudentPhotoNaming
{
    /** Filesystem-safe stem from a student ID, e.g. STU/26/0006 → STU_26_0006. */
    public static function filenameStem(?string $regNo): ?string
    {
        if (blank($regNo)) {
            return null;
        }

        return str_replace('/', '_', trim($regNo));
    }

    public static function expectedFilename(?string $regNo, string $extension = 'jpg'): ?string
    {
        $stem = self::filenameStem($regNo);

        return $stem ? "{$stem}.{$extension}" : null;
    }

    public static function expectedFilenameByName(?string $name, string $extension = 'jpg'): ?string
    {
        if (blank($name)) {
            return null;
        }

        return trim($name).'.'.$extension;
    }

    /** @return list<string> */
    public static function lookupKeysFromZipEntry(string $entry): array
    {
        $normalized = str_replace('\\', '/', trim($entry, '/'));
        $keys = [];

        if (preg_match('/^(.+)\.[^.\/]+$/', $normalized, $matches)) {
            $keys[] = $matches[1];
        }

        $filename = pathinfo($normalized, PATHINFO_FILENAME);
        if ($filename !== '') {
            $keys[] = $filename;
        }

        return array_values(array_unique(array_filter($keys)));
    }

    /** @return list<string> */
    public static function regNoCandidates(string $key): array
    {
        $key = trim($key);
        $candidates = [$key];

        foreach ([
            str_replace('_', '/', $key),
            str_replace('-', '/', $key),
            str_replace(' ', '/', $key),
        ] as $variant) {
            if ($variant !== $key) {
                $candidates[] = $variant;
            }
        }

        $collapsed = str_replace(['/', '_', '-', ' ', '\\'], '', $key);
        if ($collapsed !== '' && $collapsed !== $key) {
            $candidates[] = $collapsed;
        }

        if (preg_match('/^STU(\d{2})(\d+)$/i', str_replace(['/', '_', '-', ' ', '\\'], '', $key), $matches)) {
            $candidates[] = sprintf('STU/%s/%s', $matches[1], str_pad($matches[2], 4, '0', STR_PAD_LEFT));
            $candidates[] = sprintf('STU_%s_%s', $matches[1], str_pad($matches[2], 4, '0', STR_PAD_LEFT));
        }

        foreach ($candidates as $candidate) {
            if (preg_match('/^STU\/(\d{2})\/(\d+)$/i', $candidate, $matches)) {
                $candidates[] = sprintf('STU/%s/%s', $matches[1], str_pad($matches[2], 4, '0', STR_PAD_LEFT));
                $candidates[] = sprintf('STU_%s_%s', $matches[1], str_pad($matches[2], 4, '0', STR_PAD_LEFT));
            }
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    public static function normalizeRegNo(string $value): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', strtolower($value));
    }

    public static function normalizeLabel(string $value): string
    {
        $value = strtolower(trim($value));

        return (string) preg_replace('/[^a-z0-9]/', '', $value);
    }

    /**
     * @param  Collection<int, Student>  $students
     * @return array{0: array<string, Student>, 1: array<string, list<Student>>}
     */
    public static function nameIndex(Collection $students): array
    {
        $byExact = [];
        $byNormalized = [];

        foreach ($students as $student) {
            $exact = strtolower(trim($student->name));
            if ($exact !== '') {
                $byExact[$exact] = $student;
            }

            $normalized = self::normalizeLabel($student->name);
            if ($normalized !== '') {
                $byNormalized[$normalized] ??= [];
                $byNormalized[$normalized][] = $student;
            }
        }

        return [$byExact, $byNormalized];
    }

    /**
     * @param  Collection<int, Student>  $students
     * @return array{student: Student, match: string}|null
     */
    public static function resolveStudent(Collection $students, string $label): ?array
    {
        [$byExact, $byNormalized] = self::nameIndex($students);

        foreach (self::lookupKeysFromZipEntry($label) as $key) {
            $student = self::matchByRegNo($students, $key);
            if ($student) {
                return ['student' => $student, 'match' => 'id'];
            }
        }

        foreach (array_unique(array_filter([
            trim($label),
            pathinfo(str_replace('\\', '/', $label), PATHINFO_FILENAME),
        ])) as $nameKey) {
            $student = self::matchByName($byExact, $byNormalized, $nameKey);
            if ($student) {
                return ['student' => $student, 'match' => 'name'];
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, Student>  $students
     */
    private static function matchByRegNo(Collection $students, string $key): ?Student
    {
        $candidates = self::regNoCandidates($key);
        $normalizedKey = self::normalizeRegNo($key);

        return $students->first(function (Student $student) use ($candidates, $normalizedKey) {
            $identifiers = array_filter([
                $student->reg_no,
                $student->admission_number,
                self::filenameStem($student->reg_no),
            ]);

            foreach ($candidates as $candidate) {
                foreach ($identifiers as $identifier) {
                    if ($identifier !== null && strcasecmp((string) $identifier, $candidate) === 0) {
                        return true;
                    }
                }
            }

            if ($normalizedKey === '') {
                return false;
            }

            foreach ($identifiers as $identifier) {
                if ($identifier !== null && self::normalizeRegNo((string) $identifier) === $normalizedKey) {
                    return true;
                }
            }

            return false;
        });
    }

    /** @param  array<string, Student>  $byExact  @param  array<string, list<Student>>  $byNormalized */
    private static function matchByName(array $byExact, array $byNormalized, string $nameKey): ?Student
    {
        $exact = strtolower(trim($nameKey));
        if ($exact !== '' && isset($byExact[$exact])) {
            return $byExact[$exact];
        }

        $normalized = self::normalizeLabel($nameKey);
        if ($normalized === '' || ! isset($byNormalized[$normalized])) {
            return null;
        }

        return count($byNormalized[$normalized]) === 1
            ? $byNormalized[$normalized][0]
            : null;
    }

    /** @return array{name: string, reg_no: ?string, photo_filename: ?string, photo_filename_by_name: ?string, has_photo: bool}|null */
    public static function namingRow(Student $student): ?array
    {
        $regNo = filled($student->reg_no) ? $student->reg_no : null;

        return [
            'name'                   => $student->name,
            'reg_no'                 => $regNo,
            'photo_filename'         => $regNo ? self::expectedFilename($regNo) : null,
            'photo_filename_by_name' => self::expectedFilenameByName($student->name),
            'has_photo'              => filled($student->photo),
        ];
    }
}
