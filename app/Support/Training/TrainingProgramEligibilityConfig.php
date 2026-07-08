<?php

namespace App\Support\Training;

class TrainingProgramEligibilityConfig
{
    /** @param  array<string, mixed>|null  $raw */
    public static function normalize(?array $raw): array
    {
        $raw = is_array($raw) ? $raw : [];

        return [
            'teaching_type_ids' => array_values(array_filter(array_map('intval', $raw['teaching_type_ids'] ?? []))),
            'subject_ids'       => array_values(array_filter(array_map('intval', $raw['subject_ids'] ?? []))),
        ];
    }
}
