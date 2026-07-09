<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\User;

class SchoolContactRequirements
{
    /** @return list<array{key: string, label: string, fields: list<string>, required: bool}> */
    public static function roles(): array
    {
        return [
            [
                'key'      => 'principal',
                'label'    => 'Principal',
                'fields'   => ['principal_name', 'principal_email', 'principal_phone'],
                'required' => true,
            ],
            [
                'key'      => 'vice_principal',
                'label'    => 'Vice Principal',
                'fields'   => ['vice_principal_name', 'vice_principal_email', 'vice_principal_phone'],
                'required' => false,
            ],
            [
                'key'      => 'event_coordinator',
                'label'    => 'Events Coordinator',
                'fields'   => ['event_coordinator_name', 'event_coordinator_email', 'event_coordinator_phone'],
                'required' => true,
            ],
        ];
    }

    /** @return array{complete: bool, pending: list<array<string, mixed>>, summary: string} */
    public static function status(Tenant $school): array
    {
        $payload = $school->application_payload ?? [];
        $pending = [];

        foreach (self::roles() as $role) {
            if (! ($role['required'] ?? true)) {
                continue;
            }

            $missing = [];
            foreach ($role['fields'] as $field) {
                if (! filled($payload[$field] ?? null)) {
                    $missing[] = str_replace('_', ' ', preg_replace('/^(principal|vice_principal|event_coordinator)_/', '', $field));
                }
            }

            if ($missing !== []) {
                $pending[] = [
                    'key'     => $role['key'],
                    'label'   => $role['label'],
                    'missing' => $missing,
                    'status'  => 'pending',
                ];
            }
        }

        $complete = $pending === [];

        return [
            'complete' => $complete,
            'pending'  => $pending,
            'summary'  => $complete
                ? 'All leadership contacts are on file.'
                : implode(', ', array_column($pending, 'label')).' details pending',
        ];
    }

    /** @return list<string> Field keys still missing. */
    public static function missingFieldKeys(Tenant $school): array
    {
        $payload = $school->application_payload ?? [];
        $missing = [];

        foreach (self::roles() as $role) {
            if (! ($role['required'] ?? true)) {
                continue;
            }

            foreach ($role['fields'] as $field) {
                if (! filled($payload[$field] ?? null)) {
                    $missing[] = $field;
                }
            }
        }

        return $missing;
    }

    public static function userRoleLoginLabel(User $user): string
    {
        $role = $user->roles->first()?->name;

        return TenantUserCatalog::roleLabels()[$role] ?? ucfirst(str_replace('_', ' ', (string) $role));
    }
}
