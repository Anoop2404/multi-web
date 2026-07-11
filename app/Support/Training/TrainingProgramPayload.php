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
            'certificate_type' => 'participation',
        ]);

        $fee = (float) ($data['fee_amount'] ?? 0);
        if ($fee <= 0) {
            $data['fee_type'] = 'none';
            $data['fee_amount'] = null;
        } elseif (($data['fee_type'] ?? 'none') === 'none') {
            $data['fee_type'] = 'flat';
        } elseif (! in_array($data['fee_type'] ?? 'none', ['none', 'flat', 'school'], true)) {
            $data['fee_type'] = 'flat';
        }

        return $data;
    }
}
