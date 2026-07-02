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
            'scope'              => 'all',
            'assignment_type'    => 'all',
            'class_category_ids' => [],
            'master_class_ids'   => [],
            'class_groups'       => [],
            'gender'             => 'open',
        ];
    }

    /** @return array<string, mixed> */
    public static function normalize(?array $config): array
    {
        $config = $config ?? [];

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

        return [
            'scope'              => $scope,
            'assignment_type'    => $assignmentType,
            'class_category_ids' => $categoryIds,
            'master_class_ids'   => $masterClassIds,
            'class_groups'       => $classGroups,
            'gender'             => in_array($config['gender'] ?? 'open', ['open', 'male', 'female'], true)
                ? ($config['gender'] ?? 'open')
                : 'open',
        ];
    }

    public static function validationError(?array $config): ?string
    {
        $config = self::normalize($config);

        if ($config['assignment_type'] === 'category' && $config['class_category_ids'] === []) {
            return 'Select at least one class category.';
        }

        if ($config['assignment_type'] === 'class' && $config['master_class_ids'] === []) {
            return 'Select at least one class.';
        }

        return null;
    }

    public static function summaryLabel(?array $config, string $sahodayaId): string
    {
        $config = self::normalize($config);

        if ($config['assignment_type'] === 'all' && $config['class_groups'] === []) {
            return $config['gender'] === 'open'
                ? 'All classes'
                : ucfirst($config['gender']).' only';
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

        return implode(' · ', $parts);
    }
}
