<?php

namespace App\Support;

class SchoolDetailFields
{
    /** @return list<array{label: string, value: string}> */
    public static function fromPayload(array $payload): array
    {
        $labels = collect(SchoolApplicationForm::definitions())
            ->mapWithKeys(fn ($def, $key) => [$key => $def['label']]);

        $extraLabels = [
            'contact_email'      => 'Contact Email',
            'contact_phone'      => 'Contact Phone',
            'affiliation_number' => 'Affiliation No.',
            'submitted_at'       => 'Application Submitted',
            'rejection_reason'   => 'Rejection Reason',
            'principal_name'     => 'Principal Name',
            'principal_email'    => 'Principal Email',
            'principal_phone'    => 'Principal Phone',
        ];

        $skip = ['school_name', 'password', 'password_confirmation'];
        $fields = [];

        foreach ($payload as $key => $value) {
            if (in_array($key, $skip, true) || $value === null || $value === '') {
                continue;
            }

            $display = is_string($value) && str_contains($value, 'T') && strlen($value) > 18
                ? date('d M Y, H:i', strtotime($value))
                : (string) $value;

            $fields[] = [
                'label' => $labels[$key] ?? $extraLabels[$key] ?? ucwords(str_replace('_', ' ', $key)),
                'value' => $display,
            ];
        }

        return $fields;
    }
}
