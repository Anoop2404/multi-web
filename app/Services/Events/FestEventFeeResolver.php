<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\FestStateProgram;
use App\Support\FestClassGroupScheme;
use App\Support\FestSportsAgeGroup;
use App\Support\FestTeamSquadRules;

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

        if ($model === 'student_count_slab') {
            $slabs = $schedule['student_count_slabs'] ?? [];
            if ($slabs === []) {
                return 'Stepped fee by total registered students (no slabs configured yet)';
            }

            return 'Stepped fee by total registered students ('.count($slabs).' slab'.(count($slabs) === 1 ? '' : 's').' configured)';
        }

        if (in_array($model, ['sports_composite', 'kalolsavam_composite'], true)) {
            $school = (float) ($schedule['school_registration_flat'] ?? 2000);
            $student = (float) ($schedule['per_student_amount'] ?? 300);
            $quota = (int) ($schedule['included_items_per_student'] ?? 2);

            return "₹{$school} school + ₹{$student}/student + {$quota} free items, then ₹".((float) ($schedule['default_item_fee'] ?? 0))." extra/item";
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
                $row = $input[$level] ?? [];
                $normalized[$level] = [
                    'fee_model' => 'per_student',
                    'individual_amount' => (float) ($row['individual_amount'] ?? $row['per_student_amount'] ?? 500),
                ];

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

        foreach (FestTeamSquadRules::MULTI_PERSON_TYPES as $type) {
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
                'group_item_flat_fee' => isset($input['group_item_flat_fee']) && $input['group_item_flat_fee'] !== ''
                    ? (float) $input['group_item_flat_fee'] : null,
                'group_item_per_participant_rate' => isset($input['group_item_per_participant_rate']) && $input['group_item_per_participant_rate'] !== ''
                    ? (float) $input['group_item_per_participant_rate'] : null,
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
                'team_standby_fee_amount' => isset($input['team_standby_fee_amount']) && $input['team_standby_fee_amount'] !== ''
                    ? (float) $input['team_standby_fee_amount'] : null,
                'group_item_flat_fee' => isset($input['group_item_flat_fee']) && $input['group_item_flat_fee'] !== ''
                    ? (float) $input['group_item_flat_fee'] : null,
                'group_item_per_participant_rate' => isset($input['group_item_per_participant_rate']) && $input['group_item_per_participant_rate'] !== ''
                    ? (float) $input['group_item_per_participant_rate'] : null,
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
                'include_school_registration' => (bool) ($input['include_school_registration'] ?? false),
                'school_registration' => $this->normalizeSchoolRegistration($input['school_registration'] ?? []),
                'per_item_amount' => isset($input['per_item_amount']) && $input['per_item_amount'] !== ''
                    ? (float) $input['per_item_amount'] : 0,
                'charge_standbys' => (bool) ($input['charge_standbys'] ?? false),
                'group_item_flat_fee' => isset($input['group_item_flat_fee']) && $input['group_item_flat_fee'] !== ''
                    ? (float) $input['group_item_flat_fee'] : null,
                'group_item_per_participant_rate' => isset($input['group_item_per_participant_rate']) && $input['group_item_per_participant_rate'] !== ''
                    ? (float) $input['group_item_per_participant_rate'] : null,
            ];

            return $this->applySchoolFeeCap($normalized, $input);
        }

        if ($feeModel === 'per_student') {
            $normalized = [
                'fee_model' => 'per_student',
                'include_school_registration' => (bool) ($input['include_school_registration'] ?? false),
                'school_registration' => $this->normalizeSchoolRegistration($input['school_registration'] ?? []),
                'per_student_amount' => isset($input['per_student_amount']) && $input['per_student_amount'] !== ''
                    ? (float) $input['per_student_amount'] : 0,
            ];

            return $this->applySchoolFeeCap($normalized, $input);
        }

        if ($feeModel === 'student_count_slab') {
            $normalized = [
                'fee_model' => 'student_count_slab',
                'include_school_registration' => (bool) ($input['include_school_registration'] ?? false),
                'school_registration' => $this->normalizeSchoolRegistration($input['school_registration'] ?? []),
                'per_student_amount' => isset($input['per_student_amount']) && $input['per_student_amount'] !== ''
                    ? (float) $input['per_student_amount'] : null,
                'student_count_slabs' => $this->normalizeStudentCountSlabs($input['student_count_slabs'] ?? []),
                // Which count the slab bracket is looked up by: the school's students actually
                // registered for this event (default, unchanged behavior), or the school's whole
                // active-student enrollment regardless of how many it registers for this event.
                // Only the slab bracket lookup switches basis — the per_student_amount surcharge
                // above always stays keyed to actual registered students (see
                // FestSchoolEventFeeService::recalculate()).
                'student_count_slab_basis' => ($input['student_count_slab_basis'] ?? null) === 'school_total_enrollment'
                    ? 'school_total_enrollment' : 'event_registrations',
            ];

            return $this->applySchoolFeeCap($normalized, $input);
        }

        if (in_array($feeModel, ['sports_composite', 'kalolsavam_composite'], true)) {
            return $this->applySchoolFeeCap([
                'fee_model' => $feeModel,
                'school_registration_flat' => isset($input['school_registration_flat']) && $input['school_registration_flat'] !== ''
                    ? (float) $input['school_registration_flat'] : 2000,
                // N-tier school registration map — same shape/fallback as cksc_tiered/item_catalog
                // above (see normalizeSchoolRegistration()); absent/empty means "use the flat
                // amount above", so an event that never configures this keeps today's behavior.
                'school_registration' => $this->normalizeSchoolRegistration($input['school_registration'] ?? []),
                'per_student_amount' => isset($input['per_student_amount']) && $input['per_student_amount'] !== ''
                    ? (float) $input['per_student_amount'] : 300,
                'included_items_per_student' => isset($input['included_items_per_student']) && $input['included_items_per_student'] !== ''
                    ? (int) $input['included_items_per_student'] : 2,
                'default_item_fee' => isset($input['default_item_fee']) && $input['default_item_fee'] !== ''
                    ? (float) $input['default_item_fee'] : null,
                // Phase L — same per-Sahodaya toggle used by the item_catalog model above:
                // whether standby participants count toward the group/team per-participant
                // surcharge (FestSportsCompositeFeeService's team-fee branch).
                'charge_standbys' => (bool) ($input['charge_standbys'] ?? false),
                'group_item_flat_fee' => isset($input['group_item_flat_fee']) && $input['group_item_flat_fee'] !== ''
                    ? (float) $input['group_item_flat_fee'] : null,
                'group_item_per_participant_rate' => isset($input['group_item_per_participant_rate']) && $input['group_item_per_participant_rate'] !== ''
                    ? (float) $input['group_item_per_participant_rate'] : null,
            ], $input);
        }

        return [];
    }

    /**
     * N-tier school registration map, keyed by whatever tier label the school ends up
     * resolving to (see App\Support\SchoolClassCategoryResolver::feeTierFor()) — no
     * longer hard-limited to exactly 'secondary'/'senior_secondary'. Any string key with
     * a numeric amount is kept, so an admin can add/remove tier rows freely from the
     * fee-settings UI while events that only ever submitted the original two keys
     * normalize identically to before.
     *
     * @return array<string, float>
     */
    private function normalizeSchoolRegistration(array $input): array
    {
        $normalized = [];

        foreach ($input as $key => $value) {
            if (! is_string($key) || $key === '' || $value === null || $value === '' || ! is_numeric($value)) {
                continue;
            }
            $normalized[$key] = (float) $value;
        }

        return $normalized;
    }

    /**
     * 'student_count_slab' fee model's slab table — a list of {min_count, max_count,
     * amount} rows, sorted ascending by min_count. max_count === null means "and above".
     * Lives entirely in fee_settings, scoped to one Sahodaya's one event just like every
     * other fee model here — see docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md §7.4.
     *
     * @return array<int, array{min_count: int, max_count: ?int, amount: float}>
     */
    private function normalizeStudentCountSlabs(array $input): array
    {
        $normalized = [];

        foreach ($input as $row) {
            if (! is_array($row) || ! isset($row['amount']) || $row['amount'] === '' || ! is_numeric($row['amount'])) {
                continue;
            }

            $normalized[] = [
                'min_count' => isset($row['min_count']) && $row['min_count'] !== '' ? (int) $row['min_count'] : 0,
                'max_count' => isset($row['max_count']) && $row['max_count'] !== '' && $row['max_count'] !== null
                    ? (int) $row['max_count'] : null,
                'amount' => (float) $row['amount'],
            ];
        }

        usort($normalized, fn ($a, $b) => $a['min_count'] <=> $b['min_count']);

        return array_values($normalized);
    }

    /** @param  array<string, mixed>  $normalized */
    private function applySchoolFeeCap(array $normalized, array $input): array
    {
        if (isset($input['school_fee_cap']) && $input['school_fee_cap'] !== '') {
            $normalized['school_fee_cap'] = (float) $input['school_fee_cap'];
        }

        if (isset($input['school_fee_min']) && $input['school_fee_min'] !== '') {
            $normalized['school_fee_min'] = (float) $input['school_fee_min'];
        }

        if (isset($input['secondary_min_students']) && $input['secondary_min_students'] !== '' && $input['secondary_min_students'] !== null) {
            $normalized['secondary_min_students'] = (int) $input['secondary_min_students'];
        }

        return $normalized;
    }
}
