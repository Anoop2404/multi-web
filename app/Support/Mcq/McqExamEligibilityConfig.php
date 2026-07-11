<?php

namespace App\Support\Mcq;

use App\Models\ClassCategory;
use App\Models\MasterClass;

class McqExamEligibilityConfig
{
    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'audience'                   => 'students',
            'scope'                      => 'all',
            'assignment_type'            => 'all',
            'class_category_ids'         => [],
            'master_class_ids'           => [],
            'class_groups'               => [],
            'gender'                     => 'open',
            'teaching_type_ids'          => [],
            'subject_ids'                => [],
            'excluded_designation_ids'   => [],
            'min_experience_years'       => null,
            'allow_teacher_self_registration' => true,
        ];
    }

    /** @return array<string, mixed> */
    public static function normalize(?array $config): array
    {
        $config = $config ?? [];

        $audience = $config['audience'] ?? 'students';
        if (! in_array($audience, ['students', 'teachers', 'both'], true)) {
            $audience = 'students';
        }

        $categoryIds = collect($config['class_category_ids'] ?? [])
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $masterClassIds = collect($config['master_class_ids'] ?? [])
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $classGroups = collect($config['class_groups'] ?? [])
            ->filter(fn ($g) => filled($g))
            ->map(fn ($g) => (string) $g)
            ->unique()
            ->values()
            ->all();

        $assignmentType = $config['assignment_type'] ?? null;
        if (! in_array($assignmentType, ['all', 'category', 'class'], true)) {
            if ($categoryIds !== [] && $masterClassIds === []) {
                $assignmentType = 'category';
            } elseif ($masterClassIds !== [] && $categoryIds === []) {
                $assignmentType = 'class';
            } elseif ($categoryIds !== [] || $masterClassIds !== [] || $classGroups !== []) {
                $assignmentType = $categoryIds !== [] ? 'category' : 'class';
            } else {
                $assignmentType = 'all';
            }
        }

        if ($assignmentType === 'all') {
            $categoryIds = [];
            $masterClassIds = [];
        } elseif ($assignmentType === 'category') {
            $masterClassIds = [];
        } elseif ($assignmentType === 'class') {
            $categoryIds = [];
        }

        $scope = $assignmentType === 'all' && $classGroups === [] ? 'all' : 'filtered';

        $minExperience = $config['min_experience_years'] ?? null;
        $minExperience = filled($minExperience) && (int) $minExperience > 0
            ? (int) $minExperience
            : null;

        return [
            'audience'                 => $audience,
            'scope'                    => $scope,
            'assignment_type'          => $assignmentType,
            'class_category_ids'       => $categoryIds,
            'master_class_ids'         => $masterClassIds,
            'class_groups'             => $classGroups,
            'gender'                   => in_array($config['gender'] ?? 'open', ['open', 'male', 'female'], true)
                ? ($config['gender'] ?? 'open')
                : 'open',
            'teaching_type_ids'        => self::intList($config['teaching_type_ids'] ?? []),
            'subject_ids'              => self::intList($config['subject_ids'] ?? []),
            'excluded_designation_ids' => self::intList(
                $config['excluded_designation_ids'] ?? $config['designation_exclude'] ?? []
            ),
            'min_experience_years'     => $minExperience,
            'allow_teacher_self_registration' => array_key_exists('allow_teacher_self_registration', $config)
                ? (bool) $config['allow_teacher_self_registration']
                : true,
        ];
    }

    public static function allowsStudents(?array $config): bool
    {
        $audience = self::normalize($config)['audience'];

        return in_array($audience, ['students', 'both'], true);
    }

    public static function allowsTeachers(?array $config): bool
    {
        $audience = self::normalize($config)['audience'];

        return in_array($audience, ['teachers', 'both'], true);
    }

    public static function allowTeacherSelfRegistration(?array $config): bool
    {
        return (bool) self::normalize($config)['allow_teacher_self_registration'];
    }

    public static function validationError(?array $config): ?string
    {
        $config = self::normalize($config);

        if ($config['assignment_type'] === 'category' && $config['class_category_ids'] === []
            && self::allowsStudents($config)) {
            return 'Select at least one class category.';
        }

        if ($config['assignment_type'] === 'class' && $config['master_class_ids'] === []
            && self::allowsStudents($config)) {
            return 'Select at least one class.';
        }

        if ($config['min_experience_years'] !== null && $config['min_experience_years'] > 60) {
            return 'Minimum experience years must be 60 or less.';
        }

        return null;
    }

    public static function summaryLabel(?array $config, string $sahodayaId): string
    {
        $config = self::normalize($config);

        $audienceLabel = match ($config['audience']) {
            'teachers' => 'Teachers',
            'both'     => 'Students & teachers',
            default    => 'Students',
        };

        if ($config['audience'] === 'teachers') {
            $parts = [$audienceLabel];
            if ($config['min_experience_years'] !== null) {
                $parts[] = "≥{$config['min_experience_years']} yrs experience";
            }
            if ($config['teaching_type_ids'] !== []) {
                $parts[] = count($config['teaching_type_ids']).' teaching type(s)';
            }

            return implode(' · ', $parts);
        }

        if ($config['assignment_type'] === 'all' && $config['class_groups'] === []) {
            $base = $config['gender'] === 'open'
                ? 'All classes'
                : ucfirst($config['gender']).' only';

            return $config['audience'] === 'both' ? "{$audienceLabel} · {$base}" : $base;
        }

        $parts = [];

        if ($config['assignment_type'] === 'category' && $config['class_category_ids'] !== []) {
            $labels = ClassCategory::whereIn('id', $config['class_category_ids'])
                ->pluck('label')
                ->take(3)
                ->implode(', ');
            $extra = count($config['class_category_ids']) > 3
                ? ' +'.(count($config['class_category_ids']) - 3).' more'
                : '';
            $parts[] = 'Categories: '.$labels.$extra;
        }

        if ($config['assignment_type'] === 'class' && $config['master_class_ids'] !== []) {
            $names = MasterClass::whereIn('id', $config['master_class_ids'])
                ->pluck('name')
                ->take(4)
                ->implode(', ');
            $extra = count($config['master_class_ids']) > 4
                ? ' +'.(count($config['master_class_ids']) - 4).' more'
                : '';
            $parts[] = 'Classes: '.$names.$extra;
        }

        if ($config['class_groups'] !== []) {
            $parts[] = 'Groups: '.implode(', ', array_map('strtoupper', $config['class_groups']));
        }

        if ($parts === []) {
            $parts[] = 'All classes';
        }

        if ($config['gender'] !== 'open') {
            $parts[] = ucfirst($config['gender']).' only';
        }

        if ($config['audience'] === 'both') {
            array_unshift($parts, $audienceLabel);
        }

        return implode(' · ', $parts);
    }

    /** @param  mixed  $values
     * @return list<int>
     */
    private static function intList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $values),
            fn (int $id) => $id > 0
        )));
    }
}
