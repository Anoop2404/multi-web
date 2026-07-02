<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\FestStateProgram;
use App\Support\FestClassGroupScheme;
use App\Support\FestSportsAgeGroup;

class FestEventFeeResolver
{
    /** @return array<string, mixed> */
    public function defaultsForLevel(string $level): array
    {
        return config("fest_fees.level_defaults.{$level}", ['fee_model' => 'none']);
    }

    /** @return array<string, mixed> */
    public function resolveForLevel(
        string $level,
        ?array $levelFees = null,
        ?string $legacyFeeType = 'none',
        ?float $legacyFeeAmount = null,
    ): array {
        $levelFee = $levelFees[$level] ?? null;

        if (is_array($levelFee) && filled($levelFee['fee_model'] ?? $levelFee['fee_type'] ?? null)) {
            if (filled($levelFee['fee_model'] ?? null)) {
                return array_merge($this->defaultsForLevel($level), $levelFee);
            }

            return [
                'fee_model' => $levelFee['fee_type'] === 'none' ? 'none' : 'per_item',
                'per_item_amount' => isset($levelFee['fee_amount']) ? (float) $levelFee['fee_amount'] : null,
            ];
        }

        if ($legacyFeeType && $legacyFeeType !== 'none') {
            return [
                'fee_model' => 'per_item',
                'per_item_amount' => $legacyFeeAmount !== null ? (float) $legacyFeeAmount : null,
            ];
        }

        return $this->defaultsForLevel($level);
    }

    /** @return array<string, mixed> */
    public function resolveForProgram(FestStateProgram $program, string $levelRound): array
    {
        return $this->resolveForLevel(
            $levelRound,
            $program->level_fees,
            $program->fee_type,
            $program->fee_amount !== null ? (float) $program->fee_amount : null,
        );
    }

    /** @return array{fee_type: string, fee_amount: ?float} */
    public function resolveForEvent(FestEvent $event): array
    {
        if ($event->state_program_id) {
            $program = FestStateProgram::find($event->state_program_id);
            if ($program) {
                $resolved = $this->resolveForProgram($program, $event->level_round ?? 'sahodaya');
                if (($resolved['fee_model'] ?? 'none') !== 'none') {
                    return [
                        'fee_type' => 'per_item',
                        'fee_amount' => (float) ($resolved['first_item'] ?? $resolved['per_item_amount'] ?? 0),
                    ];
                }
            }
        }

        return [
            'fee_type'   => $event->fee_type ?? 'none',
            'fee_amount' => $event->fee_amount !== null ? (float) $event->fee_amount : null,
        ];
    }

    /** @return array<string, mixed> */
    public function resolveSchoolRoundFromParent(FestEvent $parent): array
    {
        if ($parent->state_program_id) {
            $program = FestStateProgram::find($parent->state_program_id);
            if ($program) {
                return $this->resolveForProgram($program, 'school');
            }
        }

        return $this->defaultsForLevel('school');
    }

    public function feeRequired(FestEvent $event): bool
    {
        return app(FestSchoolEventFeeService::class)->feeRequired($event);
    }

    public function amountDue(FestEvent $event, FestRegistration $registration): float
    {
        $fee = app(FestSchoolEventFeeService::class)->recalculate($event, $registration->school_id);

        return (float) $fee->total_due;
    }

    public function feeSummary(FestEvent $event, ?int $participantCount = 1): string
    {
        $schedule = app(FestSchoolEventFeeService::class)->resolveSchedule($event);
        $model = $schedule['fee_model'] ?? 'none';

        if ($model === 'none') {
            return 'No fee';
        }

        if ($model === 'cksc_tiered') {
            $first = (float) ($schedule['first_item'] ?? 350);
            $add = (float) ($schedule['additional_item'] ?? 100);
            $parts = ["₹{$first} first item", "₹{$add} each additional item"];
            if ($schedule['include_school_registration'] ?? false) {
                $sec = (float) ($schedule['school_registration']['secondary'] ?? 5000);
                array_unshift($parts, "₹{$sec} optional school registration add-on");
            }

            return implode(' + ', $parts);
        }

        if ($model === 'item_catalog') {
            return 'Per-item fees from class category / item overrides';
        }

        if ($model === 'flat_school') {
            $amount = (float) ($schedule['flat_amount'] ?? 0);

            return "₹{$amount} flat per school";
        }

        if ($model === 'per_item') {
            $amount = (float) ($schedule['per_item_amount'] ?? 0);

            return "₹{$amount} per registered item";
        }

        if ($model === 'per_student') {
            $amount = (float) ($schedule['per_student_amount'] ?? 0);

            return "₹{$amount} per participating student";
        }

        return 'Fee applies per school for this event';
    }

    public function levelLabel(FestEvent $event): string
    {
        $level = $event->level_round ?? 'sahodaya';

        return config("fest_fees.level_labels.{$level}", ucfirst($level));
    }

    public function payerLabel(FestEvent $event): string
    {
        $level = $event->level_round ?? 'sahodaya';

        return config("fest_fees.payer_labels.{$level}", 'School pays Sahodaya');
    }

    /** @return array<string, array<string, mixed>> */
    public function normalizeLevelFees(array $input, array $conductLevels, ?string $tenantId = null): array
    {
        $normalized = [];

        foreach ($conductLevels as $level) {
            if ($level === 'state') {
                continue;
            }

            $defaults = $this->defaultsForLevel($level);
            $row = $input[$level] ?? [];
            $feeModel = $row['fee_model'] ?? $row['fee_type'] ?? $defaults['fee_model'] ?? 'none';

            if ($feeModel === 'none' || $feeModel === '') {
                $normalized[$level] = ['fee_model' => 'none'];

                continue;
            }

            if ($feeModel === 'cksc_tiered') {
                $normalized[$level] = [
                    'fee_model' => 'cksc_tiered',
                    'include_school_registration' => (bool) ($row['include_school_registration'] ?? false),
                    'school_registration' => $row['school_registration'] ?? $defaults['school_registration'] ?? [],
                    'first_item' => isset($row['first_item']) ? (float) $row['first_item'] : ($defaults['first_item'] ?? 350),
                    'additional_item' => isset($row['additional_item']) ? (float) $row['additional_item'] : ($defaults['additional_item'] ?? 100),
                    'charge_standbys' => (bool) ($row['charge_standbys'] ?? $defaults['charge_standbys'] ?? false),
                ];

                continue;
            }

            if ($feeModel === 'item_catalog') {
                $scheme = FestClassGroupScheme::isValid($row['class_group_scheme'] ?? null)
                    ? $row['class_group_scheme']
                    : FestClassGroupScheme::defaultScheme();

                $normalized[$level] = [
                    'fee_model' => 'item_catalog',
                    'class_group_scheme' => $scheme,
                    'include_school_registration' => (bool) ($row['include_school_registration'] ?? false),
                    'school_registration' => $row['school_registration'] ?? $defaults['school_registration'] ?? [],
                    'class_group_fees' => $this->normalizeClassGroupFees($row['class_group_fees'] ?? [], $scheme),
                    'age_group_fees' => $this->normalizeAgeGroupFees($row['age_group_fees'] ?? [], $tenantId),
                    'participant_type_fees' => $this->normalizeParticipantTypeFees($row['participant_type_fees'] ?? []),
                    'default_item_fee' => isset($row['default_item_fee']) ? (float) $row['default_item_fee'] : null,
                ];

                continue;
            }

            $normalized[$level] = [
                'fee_model' => $feeModel,
                'flat_amount' => isset($row['fee_amount']) ? (float) $row['fee_amount'] : null,
                'per_item_amount' => isset($row['fee_amount']) ? (float) $row['fee_amount'] : null,
            ];
        }

        return $normalized;
    }

    /** @return array<string, float> */
    public function normalizeClassGroupFees(array $input, ?string $scheme = null): array
    {
        $defaults = FestClassGroupScheme::defaultFees($scheme);
        $normalized = [];

        foreach (FestClassGroupScheme::KEYS as $group) {
            $value = $input[$group] ?? $defaults[$group] ?? null;
            if ($value !== null && $value !== '') {
                $normalized[$group] = (float) $value;
            }
        }

        return $normalized;
    }

    /** @return array<string, float> */
    public function normalizeAgeGroupFees(array $input, ?string $tenantId = null): array
    {
        $defaults = FestSportsAgeGroup::defaultFees($tenantId);
        $normalized = [];

        foreach (FestSportsAgeGroup::KEYS as $group) {
            $value = $input[$group] ?? $defaults[$group] ?? null;
            if ($value !== null && $value !== '') {
                $normalized[$group] = (float) $value;
            }
        }

        return $normalized;
    }

    /** @return array<string, float> */
    public function normalizeParticipantTypeFees(array $input): array
    {
        $normalized = [];

        foreach (['group', 'team'] as $type) {
            if (isset($input[$type]) && $input[$type] !== '') {
                $normalized[$type] = (float) $input[$type];
            }
        }

        return $normalized;
    }

    /** @return array<string, mixed> */
    public function normalizeEventFeeSettings(array $input, ?string $tenantId = null): array
    {
        $feeModel = $input['fee_model'] ?? null;

        if ($feeModel === 'none' || $feeModel === '') {
            return ['fee_model' => 'none'];
        }

        if ($feeModel === 'cksc_tiered') {
            return $this->applySchoolFeeCap([
                'fee_model' => 'cksc_tiered',
                'include_school_registration' => (bool) ($input['include_school_registration'] ?? false),
                'school_registration' => $this->normalizeSchoolRegistration($input['school_registration'] ?? []),
                'first_item' => isset($input['first_item']) && $input['first_item'] !== ''
                    ? (float) $input['first_item'] : 350,
                'additional_item' => isset($input['additional_item']) && $input['additional_item'] !== ''
                    ? (float) $input['additional_item'] : 100,
                'charge_standbys' => (bool) ($input['charge_standbys'] ?? false),
            ], $input);
        }

        if ($feeModel === 'item_catalog') {
            $scheme = FestClassGroupScheme::isValid($input['class_group_scheme'] ?? null)
                ? $input['class_group_scheme']
                : FestClassGroupScheme::defaultScheme();

            return $this->applySchoolFeeCap([
                'fee_model' => 'item_catalog',
                'class_group_scheme' => $scheme,
                'include_school_registration' => (bool) ($input['include_school_registration'] ?? false),
                'school_registration' => $this->normalizeSchoolRegistration($input['school_registration'] ?? []),
                'class_group_fees' => $this->normalizeClassGroupFees($input['class_group_fees'] ?? [], $scheme),
                'age_group_fees' => $this->normalizeAgeGroupFees($input['age_group_fees'] ?? [], $tenantId),
                'participant_type_fees' => $this->normalizeParticipantTypeFees($input['participant_type_fees'] ?? []),
                'default_item_fee' => isset($input['default_item_fee']) && $input['default_item_fee'] !== ''
                    ? (float) $input['default_item_fee'] : null,
                'charge_standbys' => (bool) ($input['charge_standbys'] ?? false),
            ], $input);
        }

        if ($feeModel === 'flat_school') {
            $normalized = [
                'fee_model' => 'flat_school',
                'flat_amount' => isset($input['flat_amount']) && $input['flat_amount'] !== ''
                    ? (float) $input['flat_amount'] : 0,
            ];

            return $this->applySchoolFeeCap($normalized, $input);
        }

        if ($feeModel === 'per_item') {
            $normalized = [
                'fee_model' => 'per_item',
                'per_item_amount' => isset($input['per_item_amount']) && $input['per_item_amount'] !== ''
                    ? (float) $input['per_item_amount'] : 0,
            ];

            return $this->applySchoolFeeCap($normalized, $input);
        }

        if ($feeModel === 'per_student') {
            $normalized = [
                'fee_model' => 'per_student',
                'per_student_amount' => isset($input['per_student_amount']) && $input['per_student_amount'] !== ''
                    ? (float) $input['per_student_amount'] : 0,
            ];

            return $this->applySchoolFeeCap($normalized, $input);
        }

        return [];
    }

    /** @return array<string, float> */
    private function normalizeSchoolRegistration(array $input): array
    {
        $normalized = [];

        foreach (['secondary', 'senior_secondary'] as $key) {
            if (isset($input[$key]) && $input[$key] !== '') {
                $normalized[$key] = (float) $input[$key];
            }
        }

        return $normalized;
    }

    /** @param  array<string, mixed>  $normalized */
    private function applySchoolFeeCap(array $normalized, array $input): array
    {
        if (isset($input['school_fee_cap']) && $input['school_fee_cap'] !== '') {
            $normalized['school_fee_cap'] = (float) $input['school_fee_cap'];
        }

        return $normalized;
    }
}
