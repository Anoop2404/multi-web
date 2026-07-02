<?php

namespace App\Services\Events;

use App\Models\FeeReceipt;
use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Models\FestStateProgram;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use App\Support\TenantStorage;

class FestSchoolEventFeeService
{
    public function __construct(
        private FestEventFeeResolver $feeResolver,
        private FestItemFeeResolver $itemFeeResolver,
    ) {}

    public function feeRequired(FestEvent $event): bool
    {
        $schedule = $this->resolveSchedule($event);

        return ($schedule['fee_model'] ?? 'none') !== 'none';
    }

    /** @return array<string, mixed> */
    public function resolveSchedule(FestEvent $event): array
    {
        $schedule = null;

        if ($event->state_program_id) {
            $program = FestStateProgram::find($event->state_program_id);
            $level = $event->level_round ?? 'sahodaya';
            $levelFees = $program?->level_fees[$level] ?? null;
            if (is_array($levelFees) && filled($levelFees['fee_model'] ?? null)) {
                $schedule = array_merge(
                    config("fest_fees.level_defaults.{$level}", []),
                    $levelFees
                );
            }
        }

        if ($schedule === null) {
            $legacy = $this->feeResolver->resolveForEvent($event);
            if (($legacy['fee_type'] ?? 'none') !== 'none') {
                $schedule = [
                    'fee_model' => 'per_item',
                    'per_item_amount' => (float) ($legacy['fee_amount'] ?? 0),
                ];
            } else {
                // Legacy fee_type none with no fee_settings = no fee until configured in event settings.
                $schedule = ['fee_model' => 'none'];
            }
        }

        if (is_array($event->fee_settings) && filled($event->fee_settings)) {
            $schedule = array_merge($schedule, $event->fee_settings);
        }

        if (($schedule['fee_model'] ?? '') === 'item_catalog') {
            $scheme = \App\Support\FestClassGroupScheme::resolveForEvent($event, $schedule);
            $schedule['class_group_scheme'] = $scheme;
            $schedule['class_group_fees'] = array_merge(
                \App\Support\FestClassGroupScheme::defaultFees($scheme, $event),
                $schedule['class_group_fees'] ?? []
            );
            if ($event->event_type === 'sports') {
                $schedule['age_group_fees'] = array_merge(
                    \App\Support\FestSportsAgeGroup::defaultFees($event->tenant_id),
                    $schedule['age_group_fees'] ?? []
                );
            }
            $schedule['participant_type_fees'] = array_merge(
                config('fest_fees.default_participant_type_fees', []),
                $schedule['participant_type_fees'] ?? []
            );
        }

        return $schedule;
    }

    /** Which fee configuration source is active for this event. */
    public function feeConfigSource(FestEvent $event): string
    {
        if ($event->state_program_id) {
            $program = FestStateProgram::find($event->state_program_id);
            $level = $event->level_round ?? 'sahodaya';
            $levelFees = $program?->level_fees[$level] ?? null;
            if (is_array($levelFees) && filled($levelFees['fee_model'] ?? null)) {
                return 'state_program';
            }
        }

        $legacy = $this->feeResolver->resolveForEvent($event);
        if (($legacy['fee_type'] ?? 'none') !== 'none') {
            return 'legacy';
        }

        if (is_array($event->fee_settings) && filled($event->fee_settings)) {
            return 'event_settings';
        }

        return 'none';
    }

    public function schoolRegistrationAmount(Tenant $school, array $schedule): float
    {
        if (! ($schedule['include_school_registration'] ?? false)) {
            return 0;
        }

        $category = $school->application_payload['institution_level']
            ?? $school->getSetting('institution_level', null);

        if (! $category) {
            \Illuminate\Support\Facades\Log::warning('School institution_level missing; defaulting to secondary fee tier.', [
                'school_id' => $school->id,
            ]);
            $category = 'secondary';
        }

        $amounts = $schedule['school_registration'] ?? [];

        if (isset($schedule['override_amount'])) {
            return (float) $schedule['override_amount'];
        }

        return (float) ($amounts[$category] ?? $amounts['secondary'] ?? 0);
    }

    public function participationFee(int $itemCount, array $schedule): float
    {
        if ($itemCount <= 0) {
            return 0;
        }

        $first = (float) ($schedule['first_item'] ?? 350);
        $additional = (float) ($schedule['additional_item'] ?? 100);

        return $first + max(0, $itemCount - 1) * $additional;
    }

    public function billableItemCount(FestEvent $event, string $schoolId, array $schedule = []): int
    {
        $count = FestRegistration::where('event_id', $event->id)
            ->where('school_id', $schoolId)
            ->whereIn('status', ['submitted', 'approved'])
            ->count();

        if (! ($schedule['charge_standbys'] ?? false)) {
            return $count;
        }

        $standbys = FestParticipant::query()
            ->whereHas('registration', fn ($q) => $q
                ->where('event_id', $event->id)
                ->where('school_id', $schoolId)
                ->whereIn('status', ['submitted', 'approved']))
            ->where('participant_role', 'standby')
            ->count();

        return $count + $standbys;
    }

    public function standbyParticipantCount(FestEvent $event, string $schoolId): int
    {
        return FestParticipant::query()
            ->whereHas('registration', fn ($q) => $q
                ->where('event_id', $event->id)
                ->where('school_id', $schoolId)
                ->whereIn('status', ['submitted', 'approved']))
            ->where('participant_role', 'standby')
            ->count();
    }

    public function billableStudentCount(FestEvent $event, string $schoolId): int
    {
        return FestParticipant::query()
            ->whereHas('registration', fn ($q) => $q
                ->where('event_id', $event->id)
                ->where('school_id', $schoolId)
                ->whereIn('status', ['submitted', 'approved']))
            ->where('participant_role', '!=', 'standby')
            ->where(fn ($q) => $q->whereNotNull('student_id')->orWhereNotNull('teacher_id'))
            ->get(['student_id', 'teacher_id'])
            ->map(fn (FestParticipant $p) => $p->student_id ?? $p->teacher_id)
            ->unique()
            ->filter()
            ->count();
    }

    public function recalculate(FestEvent $event, string $schoolId): FestSchoolEventFee
    {
        $schedule = $this->resolveSchedule($event);
        $school = Tenant::findOrFail($schoolId);
        $itemCount = $this->billableItemCount($event, $schoolId, $schedule);
        $studentCount = $this->billableStudentCount($event, $schoolId);
        $feeModel = $schedule['fee_model'] ?? 'none';

        $schoolRegFee = in_array($feeModel, ['cksc_tiered', 'item_catalog'], true)
            ? $this->schoolRegistrationAmount($school, $schedule)
            : 0;

        $participationFee = match ($feeModel) {
            'cksc_tiered' => $this->participationFee($itemCount, $schedule),
            'item_catalog' => $this->itemFeeResolver->participationTotal($event, $schoolId, $schedule),
            'per_item' => $itemCount * (float) ($schedule['per_item_amount'] ?? 0),
            'flat_school' => (float) ($schedule['flat_amount'] ?? $schedule['fee_amount'] ?? 0),
            'per_student' => $studentCount * (float) ($schedule['per_student_amount'] ?? 0),
            default => 0,
        };

        $subtotal = $schoolRegFee + $participationFee;
        $total = $this->applySchoolFeeCap($subtotal, $schedule);

        if ($total < $subtotal && $participationFee > 0) {
            $participationFee = max(0, round($total - $schoolRegFee, 2));
        }

        $record = FestSchoolEventFee::firstOrNew([
            'event_id' => $event->id,
            'school_id' => $schoolId,
        ]);

        if ($record->exists && $record->status === 'approved') {
            return $record;
        }

        $participationCount = match ($feeModel) {
            'per_student' => $studentCount,
            default => $itemCount,
        };

        $record->fill([
            'school_registration_fee' => $schoolRegFee,
            'participation_item_count' => $participationCount,
            'participation_fee' => $participationFee,
            'total_due' => $total,
            'status' => $record->fee_receipt_id ? ($record->status ?? 'proof_uploaded') : 'pending',
        ]);

        if ($total <= 0) {
            $record->status = 'approved';
        }

        $record->save();

        return $record;
    }

    /** @return array<string, mixed> */
    public function breakdown(FestEvent $event, FestSchoolEventFee $fee, array $schedule): array
    {
        $items = [];
        if ($fee->school_registration_fee > 0) {
            $items[] = ['label' => 'Optional event registration add-on', 'amount' => (float) $fee->school_registration_fee];
        }

        $feeModel = $schedule['fee_model'] ?? 'none';

        if ($feeModel === 'item_catalog' && $fee->participation_item_count > 0) {
            $catalog = $this->itemFeeResolver->participationBreakdown($event, $fee->school_id, $schedule);
            foreach ($catalog['lines'] as $line) {
                $items[] = [
                    'label' => $line['label'].' — ₹'.number_format($line['amount'], 2),
                    'amount' => (float) $line['amount'],
                ];
            }
        } elseif ($fee->participation_item_count > 0 && $feeModel === 'cksc_tiered') {
            $first = (float) ($schedule['first_item'] ?? 350);
            $additional = (float) ($schedule['additional_item'] ?? 100);
            $count = $fee->participation_item_count;

            if ($count >= 1) {
                $items[] = ['label' => 'First item', 'amount' => $first];
            }
            if ($count > 1) {
                $items[] = [
                    'label' => 'Additional items ('.($count - 1).' × ₹'.$additional.')',
                    'amount' => ($count - 1) * $additional,
                ];
            }
        } elseif ($feeModel === 'per_student' && $fee->participation_fee > 0) {
            $studentCount = $fee->participation_item_count;
            $rate = (float) ($schedule['per_student_amount'] ?? 0);
            $items[] = [
                'label' => "Participating students ({$studentCount} × ₹{$rate})",
                'amount' => (float) $fee->participation_fee,
            ];
        } elseif ($fee->participation_fee > 0) {
            $label = match ($feeModel) {
                'flat_school' => 'Flat school fee',
                'per_item' => 'Participation fees ('.$fee->participation_item_count.' item(s))',
                default => 'Participation fees ('.$fee->participation_item_count.' item(s))',
            };
            $items[] = [
                'label' => $label,
                'amount' => (float) $fee->participation_fee,
            ];
        }

        return [
            'items' => $items,
            'total' => (float) $fee->total_due,
            'item_count' => $fee->participation_item_count,
        ];
    }

    public function attachPayment(
        FestEvent $event,
        string $schoolId,
        UploadedFile $proof,
        int $userId,
        ?string $transactionRef = null,
        ?string $bankName = null,
    ): FestSchoolEventFee {
        $fee = $this->recalculate($event, $schoolId);
        abort_if($fee->total_due <= 0, 422, 'No fee due for this event.');
        abort_if($fee->status === 'approved', 422, 'Fee already approved.');

        $path = TenantStorage::storeUploadedFile($proof, "fest-payments/{$schoolId}");

        $receipt = FeeReceipt::create([
            'feeable_type' => FestSchoolEventFee::class,
            'feeable_id' => $fee->id,
            'file_path' => $path,
            'transaction_ref' => $transactionRef,
            'bank_name' => $bankName,
            'payment_date' => now()->toDateString(),
            'amount' => $fee->total_due,
            'status' => 'uploaded',
            'uploaded_by_user_id' => $userId,
        ]);

        $fee->update([
            'fee_receipt_id' => $receipt->id,
            'status' => 'proof_uploaded',
        ]);

        return $fee->fresh(['feeReceipt']);
    }

    public function isPaid(FestEvent $event, string $schoolId): bool
    {
        if (! $this->feeRequired($event)) {
            return true;
        }

        $fee = FestSchoolEventFee::where('event_id', $event->id)
            ->where('school_id', $schoolId)
            ->first();

        if (! $fee) {
            $fee = $this->recalculate($event, $schoolId);
        }

        if ($fee->total_due <= 0) {
            return true;
        }

        return $fee->status === 'approved';
    }

    private function applySchoolFeeCap(float $total, array $schedule): float
    {
        $cap = isset($schedule['school_fee_cap']) ? (float) $schedule['school_fee_cap'] : null;

        if ($cap !== null && $cap > 0 && $total > $cap) {
            return $cap;
        }

        return $total;
    }
}
