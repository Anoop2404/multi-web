<?php

namespace App\Support\Fest;

use App\Support\PersistDefaults;

class FestEventPayload
{
    /** @param  array<string, mixed>  $data */
    public static function applyDefaults(array $data): array
    {
        return PersistDefaults::coalesce($data, [
            'fee_type' => 'none',
        ]);
    }
}
