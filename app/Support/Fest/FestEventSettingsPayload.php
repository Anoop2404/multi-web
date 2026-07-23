<?php

namespace App\Support\Fest;

use App\Support\PersistDefaults;

class FestEventSettingsPayload
{
    /** @param  array<string, mixed>  $data */
    public static function applyDefaults(array $data): array
    {
        return PersistDefaults::booleans($data, [
            'scoring_locked',
            'appeals_open',
            'require_judge_scores_before_publish',
            'require_all_marks_before_publish',
            'schedule_published',
            'certificate_collection_open',
            'registration_locked',
            'record_tracking_enabled',
            'strict_item_payment_gating',
        ], [
            'scoring_locked'                      => false,
            'appeals_open'                        => false,
            'require_judge_scores_before_publish' => false,
            'require_all_marks_before_publish'    => false,
            'schedule_published'                  => false,
            'certificate_collection_open'         => false,
            'registration_locked'                 => false,
            'record_tracking_enabled'             => false,
            'strict_item_payment_gating'           => false,
        ]);
    }
}
