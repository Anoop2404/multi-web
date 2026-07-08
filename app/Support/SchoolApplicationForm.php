<?php

namespace App\Support;

use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;

class SchoolApplicationForm
{
    /** @return array<string, array{label: string, group: string, placeholder?: string, hint?: string, default: array{enabled: bool, required: bool}, locked?: bool}> */
    public static function definitions(): array
    {
        return [
            'school_name' => [
                'label'       => 'School Name',
                'group'       => 'school',
                'placeholder' => 'Enter school name',
                'default'     => ['enabled' => true, 'required' => true],
                'locked'      => true,
            ],
            'school_email' => [
                'label'       => 'Gmail Address (Login)',
                'group'       => 'school',
                'placeholder' => 'your.school@gmail.com',
                'hint'        => 'Use a Gmail address — this will be your login username. You must verify it after registration.',
                'default'     => ['enabled' => true, 'required' => true],
                'locked'      => true,
            ],
            'school_prefix' => [
                'label'       => 'School Code (Prefix)',
                'group'       => 'school',
                'placeholder' => 'e.g. GHS',
                'hint'        => 'Short unique code for your school within this Sahodaya (used in student registration numbers). Must not match another member school.',
                'default'     => ['enabled' => true, 'required' => true],
                'locked'      => true,
            ],
            'phone' => [
                'label'       => 'Phone',
                'group'       => 'school',
                'placeholder' => 'School phone number',
                'default'     => ['enabled' => true, 'required' => true],
            ],
            'cbse_affiliation' => [
                'label'       => 'CBSE Affiliation No.',
                'group'       => 'school',
                'placeholder' => 'e.g. 930319',
                'default'     => ['enabled' => true, 'required' => true],
                'locked'      => true,
            ],
            'highest_class' => [
                'label'       => 'Highest Class',
                'group'       => 'school',
                'default'     => ['enabled' => true, 'required' => true],
            ],
            'website' => [
                'label'       => 'Website',
                'group'       => 'school',
                'placeholder' => 'https://example.com',
                'default'     => ['enabled' => true, 'required' => false],
            ],
            'address' => [
                'label'       => 'Address',
                'group'       => 'school',
                'placeholder' => 'School address',
                'default'     => ['enabled' => true, 'required' => false],
            ],
            'district' => [
                'label'       => 'District',
                'group'       => 'school',
                'placeholder' => 'e.g. Malappuram',
                'default'     => ['enabled' => false, 'required' => false],
            ],
            'principal_name' => [
                'label'       => 'Principal Name',
                'group'       => 'principal',
                'placeholder' => 'Principal full name',
                'hint'        => 'Optional on public registration — schools usually add this later from their admin profile.',
                'default'     => ['enabled' => false, 'required' => false],
            ],
            'principal_email' => [
                'label'       => 'Principal Email',
                'group'       => 'principal',
                'placeholder' => 'principal@school.edu',
                'hint'        => 'Optional on public registration — schools usually add this later from their admin profile.',
                'default'     => ['enabled' => false, 'required' => false],
            ],
            'principal_phone' => [
                'label'       => 'Principal Phone',
                'group'       => 'principal',
                'placeholder' => 'Principal phone number',
                'hint'        => 'Optional on public registration — schools usually add this later from their admin profile.',
                'default'     => ['enabled' => false, 'required' => false],
            ],
            'vice_principal_name' => [
                'label'       => 'Vice Principal Name',
                'group'       => 'leadership',
                'placeholder' => 'Vice principal full name',
                'hint'        => 'Optional on public registration — schools usually add this later from their admin profile.',
                'default'     => ['enabled' => false, 'required' => false],
            ],
            'vice_principal_email' => [
                'label'       => 'Vice Principal Email',
                'group'       => 'leadership',
                'placeholder' => 'vice.principal@school.edu',
                'hint'        => 'Optional on public registration — schools usually add this later from their admin profile.',
                'default'     => ['enabled' => false, 'required' => false],
            ],
            'vice_principal_phone' => [
                'label'       => 'Vice Principal Phone',
                'group'       => 'leadership',
                'placeholder' => 'Vice principal phone',
                'hint'        => 'Optional on public registration — schools usually add this later from their admin profile.',
                'default'     => ['enabled' => false, 'required' => false],
            ],
            'event_coordinator_name' => [
                'label'       => 'Events Coordinator Name',
                'group'       => 'leadership',
                'placeholder' => 'Fest / events coordinator name',
                'hint'        => 'Optional on public registration — schools usually add this later from their admin profile.',
                'default'     => ['enabled' => false, 'required' => false],
            ],
            'event_coordinator_email' => [
                'label'       => 'Events Coordinator Email',
                'group'       => 'leadership',
                'placeholder' => 'events@school.edu',
                'hint'        => 'Optional on public registration — schools usually add this later from their admin profile.',
                'default'     => ['enabled' => false, 'required' => false],
            ],
            'event_coordinator_phone' => [
                'label'       => 'Events Coordinator Phone',
                'group'       => 'leadership',
                'placeholder' => 'Events coordinator phone',
                'hint'        => 'Optional on public registration — schools usually add this later from their admin profile.',
                'default'     => ['enabled' => false, 'required' => false],
            ],
            'password' => [
                'label'       => 'Password',
                'group'       => 'account',
                'placeholder' => 'Choose a password',
                'default'     => ['enabled' => false, 'required' => false],
                'locked'      => true,
            ],
            'password_confirmation' => [
                'label'       => 'Confirm Password',
                'group'       => 'account',
                'placeholder' => 'Re-enter password',
                'default'     => ['enabled' => false, 'required' => false],
                'locked'      => true,
            ],
        ];
    }

    /** @return array<string, array{label: string, group: string, enabled: bool, required: bool, placeholder?: string, hint?: string}> */
    public static function resolve(?SahodayaProfile $profile): array
    {
        $stored = $profile?->application_form_config ?? [];
        $resolved = [];

        foreach (self::definitions() as $key => $def) {
            $override = $stored[$key] ?? [];
            if ($def['locked'] ?? false) {
                $enabled  = true;
                $required = (bool) ($def['default']['required'] ?? true);
            } else {
                $enabled  = (bool) ($override['enabled'] ?? $def['default']['enabled']);
                $required = $enabled && (bool) ($override['required'] ?? $def['default']['required']);
            }

            $resolved[$key] = [
                'label'       => $def['label'],
                'group'       => $def['group'],
                'enabled'     => $enabled,
                'required'    => $required,
                'placeholder' => $def['placeholder'] ?? null,
                'hint'        => $def['hint'] ?? null,
                'locked'      => $def['locked'] ?? false,
            ];
        }

        // Password fields are never collected on the public form
        $resolved['password']['enabled'] = false;
        $resolved['password']['required'] = false;
        $resolved['password_confirmation']['enabled'] = false;
        $resolved['password_confirmation']['required'] = false;

        return $resolved;
    }

    /** @return array<int, string> */
    public static function highestClassOptions(): array
    {
        return array_merge(
            ['Pre-Primary' => 'Pre-Primary', 'LKG' => 'LKG', 'UKG' => 'UKG'],
            collect(range(1, 12))->mapWithKeys(fn (int $n) => ["Class {$n}" => "Class {$n}"])->all()
        );
    }

    /** @return array<string, mixed> */
    public static function validationRules(array $fields, Tenant $sahodaya): array
    {
        $rules = [
            'school_name' => 'required|string|max:255',
        ];

        foreach (self::inputFieldKeys() as $key) {
            if (! ($fields[$key]['enabled'] ?? false)) {
                continue;
            }

            if (in_array($key, ['school_email', 'school_prefix'], true)) {
                continue;
            }

            if ($key === 'cbse_affiliation') {
                $rules[$key] = [
                    'required',
                    'string',
                    'max:100',
                    function (string $attribute, mixed $value, \Closure $fail) use ($sahodaya): void {
                        if (! is_string($value) || $value === '') {
                            return;
                        }

                        if (self::affiliationIsTaken($sahodaya, $value)) {
                            $fail('This CBSE affiliation number is already registered.');
                        }
                    },
                ];
                continue;
            }

            $rule = ($fields[$key]['required'] ? 'required' : 'nullable') . '|';

            $rule .= match ($key) {
                'school_email'  => 'email|max:255',
                'school_prefix' => 'string|alpha_num|max:10',
                'phone'          => 'string|max:30',
                'highest_class'  => 'string|max:50',
                'website'        => 'nullable|string|max:255',
                'address'        => 'string|max:1000',
                'district'       => 'string|max:100',
                'password'       => 'string|min:8|confirmed',
                default          => 'string|max:255',
            };

            $rules[$key] = $rule;
        }

        if ($fields['school_email']['enabled'] ?? true) {
            $rules['school_email'] = [
                ($fields['school_email']['required'] ?? true) ? 'required' : 'nullable',
                'email',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! self::isGmailAddress($value)) {
                        $fail('Login email must be a valid Gmail address (@gmail.com).');
                    }
                },
            ];
        }

        if ($fields['school_prefix']['enabled'] ?? true) {
            $rules['school_prefix'] = self::schoolPrefixRules($sahodaya);
        }

        if (($fields['password']['enabled'] ?? false) && ($fields['password']['required'] ?? false)) {
            $rules['password_confirmation'] = 'required|string|min:8';
        }

        $rules['requested_subdomain'] = [
            'nullable', 'string', 'max:50', 'alpha_dash', 'unique:tenants,subdomain',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value && TenantDomainSync::isReservedSubdomain((string) $value)) {
                    $fail('This subdomain is reserved.');
                }
            },
        ];

        return $rules;
    }

    /** @return array<string, mixed> */
    public static function buildPayload(array $data, array $fields): array
    {
        $payload = ['submitted_at' => now()->toIso8601String()];

        foreach (self::payloadFieldKeys() as $key) {
            if (! ($fields[$key]['enabled'] ?? false)) {
                continue;
            }
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                $payload[$key] = match ($key) {
                    'cbse_affiliation' => self::normalizeAffiliation($data[$key]),
                    'school_prefix'    => strtoupper(trim((string) $data[$key])),
                    default            => $data[$key],
                };
            }
        }

        if (! empty($data['cbse_affiliation'])) {
            $payload['affiliation_number'] = self::normalizeAffiliation($data['cbse_affiliation']);
        }

        // Legacy keys used by admin review and notifications
        if (! empty($payload['school_email'])) {
            $payload['contact_email'] = $payload['school_email'];
        }
        if (! empty($payload['phone'])) {
            $payload['contact_phone'] = $payload['phone'];
        }

        return $payload;
    }

    public static function schoolAffiliation(Tenant $school): ?string
    {
        $payload = $school->application_payload ?? [];

        return $payload['cbse_affiliation']
            ?? $payload['affiliation_number']
            ?? ($school->getSetting('widgets', [])['cbse_affiliation_number'] ?? null);
    }

    public static function normalizeAffiliation(?string $affiliation): ?string
    {
        if ($affiliation === null) {
            return null;
        }

        $normalized = preg_replace('/\s+/', '', strtoupper(trim($affiliation)));

        return $normalized !== '' ? $normalized : null;
    }

    public static function affiliationIsTaken(Tenant $sahodaya, string $affiliation, ?string $exceptSchoolId = null): bool
    {
        $normalized = self::normalizeAffiliation($affiliation);

        if (! $normalized) {
            return false;
        }

        return Tenant::where('parent_id', $sahodaya->id)
            ->where('type', 'school')
            ->whereIn('membership_status', ['pending', 'approved'])
            ->when($exceptSchoolId, fn ($query) => $query->where('id', '!=', $exceptSchoolId))
            ->get()
            ->contains(function (Tenant $school) use ($normalized): bool {
                $existing = self::normalizeAffiliation(self::schoolAffiliation($school));

                return $existing !== null && $existing === $normalized;
            });
    }

    public static function isGmailAddress(string $email): bool
    {
        return (bool) preg_match('/^[^@\s]+@gmail\.com$/i', trim($email));
    }

    public static function prefixIsTaken(Tenant $sahodaya, string $prefix, ?string $exceptSchoolId = null): bool
    {
        $normalized = strtoupper(trim($prefix));

        if ($normalized === '') {
            return false;
        }

        return Tenant::where('parent_id', $sahodaya->id)
            ->where('type', 'school')
            ->when($exceptSchoolId, fn ($q) => $q->where('id', '!=', $exceptSchoolId))
            ->where('school_prefix', $normalized)
            ->exists();
    }

    /** @return array<int, mixed> */
    public static function schoolPrefixRules(Tenant $sahodaya, ?string $exceptSchoolId = null): array
    {
        return [
            'required',
            'string',
            'alpha_num',
            'max:10',
            function (string $attribute, mixed $value, \Closure $fail) use ($sahodaya, $exceptSchoolId): void {
                if (! is_string($value) || $value === '') {
                    return;
                }
                if (self::prefixIsTaken($sahodaya, $value, $exceptSchoolId)) {
                    $fail('This school code is already in use within this Sahodaya.');
                }
            },
        ];
    }

    /** @return array<string, array{enabled: bool, required: bool}> */
    public static function normalizeAdminInput(array $input): array
    {
        $config = [];

        foreach (self::definitions() as $key => $def) {
            if ($def['locked'] ?? false) {
                continue;
            }
            if ($key === 'password_confirmation') {
                continue;
            }

            $config[$key] = [
                'enabled'  => (bool) ($input[$key]['enabled'] ?? $def['default']['enabled']),
                'required' => (bool) ($input[$key]['required'] ?? $def['default']['required']),
            ];
        }

        return $config;
    }

    /** @return list<string> Fields schools may update from their admin panel. */
    public static function editableFieldKeys(): array
    {
        return [
            'school_prefix', 'cbse_affiliation',
            'phone', 'website', 'address', 'district', 'highest_class',
            'principal_name', 'principal_email', 'principal_phone',
            'vice_principal_name', 'vice_principal_email', 'vice_principal_phone',
            'event_coordinator_name', 'event_coordinator_email', 'event_coordinator_phone',
        ];
    }

    /** @return array<string, list<string>> */
    public static function profileSectionFieldKeys(): array
    {
        return [
            'school' => ['school_prefix', 'cbse_affiliation', 'phone', 'website', 'address', 'district', 'highest_class'],
            'principal' => ['principal_name', 'principal_email', 'principal_phone'],
            'leadership' => [
                'vice_principal_name', 'vice_principal_email', 'vice_principal_phone',
                'event_coordinator_name', 'event_coordinator_email', 'event_coordinator_phone',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function schoolProfileValidationRulesForSection(Tenant $school, array $fields, string $section): array
    {
        $allowed = self::profileSectionFieldKeys()[$section] ?? [];
        $all = self::schoolProfileValidationRules($school, $fields);

        return array_intersect_key($all, array_flip($allowed));
    }

    /** @return array<string, mixed> */
    public static function schoolProfileValidationRules(Tenant $school, array $fields): array
    {
        $rules = [];

        foreach (self::editableFieldKeys() as $key) {
            if (! ($fields[$key]['enabled'] ?? false)) {
                continue;
            }

            $required = ($fields[$key]['required'] ?? false) ? 'required' : 'nullable';

            if ($key === 'school_prefix') {
                $rules[$key] = $school->prefixes_locked && filled($school->school_prefix)
                    ? [
                        'nullable',
                        'string',
                        function (string $attribute, mixed $value, \Closure $fail) use ($school): void {
                            if ($value === null || $value === '') {
                                return;
                            }
                            if (strtoupper(trim((string) $value)) !== strtoupper((string) $school->school_prefix)) {
                                $fail('School code is locked because student registration numbers depend on it. Contact Sahodaya admin to change it.');
                            }
                        },
                    ]
                    : [
                        'nullable',
                        'string',
                        'alpha_num',
                        'max:10',
                        function (string $attribute, mixed $value, \Closure $fail) use ($school): void {
                            if (! is_string($value) || $value === '') {
                                return;
                            }
                            if ($school->parent && self::prefixIsTaken($school->parent, $value, $school->id)) {
                                $fail('This school code is already in use within this Sahodaya.');
                            }
                        },
                    ];

                continue;
            }

            if ($key === 'cbse_affiliation') {
                $rules[$key] = [
                    'nullable',
                    'string',
                    'max:100',
                    function (string $attribute, mixed $value, \Closure $fail) use ($school): void {
                        if (! is_string($value) || $value === '') {
                            return;
                        }

                        if ($school->parent && self::affiliationIsTaken($school->parent, $value, $school->id)) {
                            $fail('This CBSE affiliation number is already registered.');
                        }
                    },
                ];

                continue;
            }

            $rules[$key] = $required.'|'.match ($key) {
                'phone', 'principal_phone' => 'string|max:30',
                'highest_class'           => 'string|max:50',
                'website'                 => 'nullable|string|max:255',
                'address'                 => 'string|max:1000',
                'district'                => 'string|max:100',
                'principal_name'          => 'string|max:255',
                'principal_email'         => 'nullable|email|max:255',
                'vice_principal_name'     => 'string|max:255',
                'vice_principal_email'    => 'nullable|email|max:255',
                'vice_principal_phone'    => 'string|max:30',
                'event_coordinator_name'  => 'string|max:255',
                'event_coordinator_email' => 'nullable|email|max:255',
                'event_coordinator_phone' => 'string|max:30',
                default                   => 'string|max:255',
            };
        }

        return $rules;
    }

    /** @return array<string, mixed> */
    public static function mergeProfileUpdate(array $payload, array $data, array $fields): array
    {
        foreach (self::editableFieldKeys() as $key) {
            if (! ($fields[$key]['enabled'] ?? false) || ! array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];
            if ($value === null || $value === '') {
                if ($key === 'school_prefix') {
                    continue;
                }
                unset($payload[$key]);
                if ($key === 'phone') {
                    unset($payload['contact_phone']);
                }
                if ($key === 'cbse_affiliation') {
                    unset($payload['affiliation_number']);
                }
                continue;
            }

            $payload[$key] = match ($key) {
                'school_prefix'    => strtoupper(trim((string) $value)),
                'cbse_affiliation' => self::normalizeAffiliation((string) $value),
                default            => $value,
            };
            if ($key === 'phone') {
                $payload['contact_phone'] = $value;
            }
            if ($key === 'cbse_affiliation') {
                $payload['affiliation_number'] = self::normalizeAffiliation((string) $value);
            }
        }

        $payload['updated_at'] = now()->toIso8601String();

        return $payload;
    }

    /** @return array<string, mixed> */
    public static function accountValidationRules(?User $user): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! self::isGmailAddress($value)) {
                        $fail('Login email must be a valid Gmail address (@gmail.com).');
                    }
                },
                function (string $attribute, mixed $value, \Closure $fail) use ($user): void {
                    if (! is_string($value) || ! $user) {
                        return;
                    }
                    if (User::where('email', strtolower(trim($value)))->where('id', '!=', $user->id)->exists()) {
                        $fail('An account with this Gmail address already exists.');
                    }
                },
            ],
            'current_password' => 'required_with:password|nullable|current_password',
            'password'         => 'nullable|string|min:8|confirmed',
        ];
    }

    /** @return list<string> */
    private static function inputFieldKeys(): array
    {
        return [
            'school_email', 'school_prefix', 'phone', 'cbse_affiliation', 'highest_class', 'website',
            'address', 'district', 'principal_name', 'principal_email', 'principal_phone',
            'vice_principal_name', 'vice_principal_email', 'vice_principal_phone',
            'event_coordinator_name', 'event_coordinator_email', 'event_coordinator_phone',
        ];
    }

    /** @return list<string> */
    private static function payloadFieldKeys(): array
    {
        return [
            'school_email', 'school_prefix', 'phone', 'cbse_affiliation', 'highest_class', 'website',
            'address', 'district', 'principal_name', 'principal_email', 'principal_phone',
            'vice_principal_name', 'vice_principal_email', 'vice_principal_phone',
            'event_coordinator_name', 'event_coordinator_email', 'event_coordinator_phone',
        ];
    }
}
