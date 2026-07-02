<?php

namespace App\Support\Fest;

use App\Support\PersistDefaults;

class FestParticipationPolicyPayload
{
    /** @param  array<string, mixed>  $data */
    public static function applyDefaults(array $data): array
    {
        return PersistDefaults::booleans($data, [
            'one_entry_per_item_per_school',
            'count_submitted_registrations',
            'require_fee_before_approval',
        ], [
            'one_entry_per_item_per_school' => true,
            'count_submitted_registrations' => true,
            'require_fee_before_approval'   => true,
        ]);
    }
}
