<?php

namespace App\Support\Training;

class TrainingProgramEligibilityConfig
{
    /**
     * Normalized eligibility_config shape:
     *
     * [
     *   'teaching_type_ids' => int[],
     *   'subject_ids' => int[],
     *   'excluded_designation_ids' => int[],
     *   'min_experience_years' => ?int,
     *   'prior_training' => [
     *     'required' => bool,
     *     'program_id' => ?int,  // null = any prior completed programme
     *   ],
     *   'region_ids' => int[],  // enforced via school_region_assignments when non-empty
     * ]
     *
     * @param  array<string, mixed>|null  $raw
     * @return array{
     *     teaching_type_ids: list<int>,
     *     subject_ids: list<int>,
     *     excluded_designation_ids: list<int>,
     *     min_experience_years: ?int,
     *     prior_training: array{required: bool, program_id: ?int},
     *     region_ids: list<int>
     * }
     */
    public static function normalize(?array $raw): array
    {
        $raw = is_array($raw) ? $raw : [];

        $prior = is_array($raw['prior_training'] ?? null) ? $raw['prior_training'] : [];
        $priorRequired = (bool) ($prior['required'] ?? $raw['prior_training_required'] ?? false);
        $priorProgramId = $prior['program_id'] ?? $raw['prior_training_program_id'] ?? null;
        $priorProgramId = filled($priorProgramId) ? (int) $priorProgramId : null;

        $minExperience = $raw['min_experience_years'] ?? null;
        $minExperience = filled($minExperience) && (int) $minExperience > 0
            ? (int) $minExperience
            : null;

        // Accept both excluded_designation_ids and legacy designation_exclude alias.
        $excluded = $raw['excluded_designation_ids'] ?? $raw['designation_exclude'] ?? [];

        return [
            'teaching_type_ids' => self::intList($raw['teaching_type_ids'] ?? []),
            'subject_ids' => self::intList($raw['subject_ids'] ?? []),
            'excluded_designation_ids' => self::intList($excluded),
            'min_experience_years' => $minExperience,
            'prior_training' => [
                'required' => $priorRequired,
                'program_id' => $priorRequired ? $priorProgramId : null,
            ],
            'region_ids' => self::intList($raw['region_ids'] ?? []),
        ];
    }

    public static function validationError(?array $raw): ?string
    {
        $config = self::normalize($raw);

        if ($config['min_experience_years'] !== null && $config['min_experience_years'] > 60) {
            return 'Minimum experience years must be 60 or less.';
        }

        if ($config['prior_training']['required']
            && $config['prior_training']['program_id'] !== null
            && $config['prior_training']['program_id'] <= 0) {
            return 'Prior training programme is invalid.';
        }

        return null;
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
