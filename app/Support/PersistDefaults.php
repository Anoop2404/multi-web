<?php

namespace App\Support;

class PersistDefaults
{
    /**
     * Replace null values with defaults so explicit NULL is not written to NOT NULL columns.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed|callable>  $defaults
     * @return array<string, mixed>
     */
    public static function coalesce(array $data, array $defaults): array
    {
        foreach ($defaults as $key => $default) {
            if (! array_key_exists($key, $data) || $data[$key] === null) {
                $data[$key] = is_callable($default) ? $default($data) : $default;
            }
        }

        return $data;
    }

    public static function integer(mixed $value, int $default = 0, ?int $min = null, ?int $max = null): int
    {
        if (! filled($value) || ! is_numeric($value)) {
            return $default;
        }

        $integer = (int) $value;

        if ($min !== null && $integer < $min) {
            return $default;
        }

        if ($max !== null && $integer > $max) {
            return $default;
        }

        return $integer;
    }

    /** @param  list<string>  $allowed */
    public static function enum(mixed $value, string $default, array $allowed): string
    {
        if (! filled($value) || ! in_array($value, $allowed, true)) {
            return $default;
        }

        return (string) $value;
    }

    /**
     * @param  list<string>  $fields
     * @param  array<string, bool>  $defaults
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function booleans(array $data, array $fields, array $defaults = []): array
    {
        foreach ($fields as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            if ($data[$field] === null) {
                $data[$field] = $defaults[$field] ?? false;
            } else {
                $data[$field] = filter_var($data[$field], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $data;
    }
}
