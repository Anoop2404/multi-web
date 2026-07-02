<?php

namespace App\Support\Fest;

use App\Support\PersistDefaults;

class FestEventItemPayload
{
    /** @param  array<string, mixed>  $data */
    public static function applyDefaults(array $data, ?object $existing = null): array
    {
        $defaults = [
            'category'         => 'general',
            'participant_type' => 'individual',
            'gender'           => 'open',
            'class_group'      => 'open',
        ];

        if ($existing !== null) {
            foreach ($defaults as $key => $fallback) {
                if (! array_key_exists($key, $data) || $data[$key] === null) {
                    $data[$key] = $existing->{$key} ?? $fallback;
                }
            }

            return $data;
        }

        return PersistDefaults::coalesce($data, $defaults);
    }
}
