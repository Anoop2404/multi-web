<?php

namespace App\Support\Training;

use App\Support\PersistDefaults;

class TrainingProgramPayload
{
    /** @param  array<string, mixed>  $data */
    public static function applyDefaults(array $data): array
    {
        $data = PersistDefaults::coalesce($data, [
            'fee_type' => 'none',
        ]);

        $fee = (float) ($data['fee_amount'] ?? 0);
        if ($fee <= 0) {
            $data['fee_type'] = 'none';
            $data['fee_amount'] = null;
        } elseif (($data['fee_type'] ?? 'none') === 'none') {
            $data['fee_type'] = 'flat';
        }

        return $data;
    }
}
