<?php

namespace App\Services\Events;

use App\Models\FeeReceipt;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestItemHead;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Models\FestSchoolEventFeeLine;
use App\Models\FestStateProgram;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use App\Support\TenantStorage;

class FestSchoolEventFeeService
{
    public function __construct(
        private FestEventFeeResolver $feeResolver,
        private FestItemFeeResolver $itemFeeResolver,
        private FestSportsCompositeFeeService $sportsCompositeFeeService,
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

        if ($event->event_type === 'sports') {
            $sportsDefaults = config('fest_fees.level_defaults.sports', []);
            $schedule = array_merge($sportsDefaults, $schedule);
            $schedule['fee_model'] = 'sports_composite';

            // Head = Event: once fees are configured on the sport event (Settings →
            // Fee settings), its unified columns are the single source of truth.
            // Blank columns then mean ₹0 — NOT "fall back to config defaults",
            // otherwise schools see phantom ₹300/₹150 charges the admin never set.
            if ($event->hasSportsFeesConfigured()) {
                $schedule['school_registration_flat'] = (float) ($event->school_registration_fee ?? 0);
                $schedule['per_student_amount'] = (float) ($event->student_registration_fee ?? 0);
                $schedule['team_registration_fee'] = (float) ($event->team_registration_fee ?? 0);
                $schedule['default_item_fee'] = (float) ($event->default_item_fee ?? 0);
                $schedule['extra_item_fee'] = $event->extra_item_fee !== null
                    ? (float) $event->extra_item_fee
                    : ($schedule['extra_item_fee'] ?? null);
                $schedule['included_items_per_student'] = (int) ($event->included_items_per_student ?? 0);
                $schedule['included_teams'] = (int) ($event->included_teams ?? 0);
            }
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

        if (($schedule['fee_model'] ?? 'none') === 'none' && $this->eventHasConfiguredItemFees($event)) {
            $schedule['fee_model'] = 'item_catalog';
            $schedule = $this->applyItemCatalogDefaults($event, $schedule);
        }

        return $schedule;
    }

    private function eventHasConfiguredItemFees(FestEvent $event): bool
    {
        if ($event->event_type === 'sports' && $event->hasSportsFeesConfigured()) {
            return true;
        }

        if (FestEventItem::query()
            ->where('event_id', $event->id)
            ->where(function ($q) {
                $q->where('is_enabled', true)->orWhereNull('is_enabled');
            })
            ->whereNotNull('fee_amount')
            ->where('fee_amount', '>', 0)
            ->exists()) {
            return true;
        }

        return FestItemHead::query()
            ->where('event_id', $event->id)
            ->where(function ($q) {
                $q->where(fn ($inner) => $inner->whereNotNull('default_item_fee')->where('default_item_fee', '>', 0))
                    ->orWhere(fn ($inner) => $inner->whereNotNull('extra_item_fee')->where('extra_item_fee', '>', 0));
            })
            ->exists();
    }

    /** @param  array<string, mixed>  $schedule */
    private function applyItemCatalogDefaults(FestEvent $event, array $schedule): array
    {
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
            ->whereHas('item', fn ($q) => $q->where('is_enabled', true))
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

    /**
     * Whether this event bills sports_composite fees per Event Head.
     * After Head = Event unification, sports always bills at event level (returns false).
     * Legacy true only when heads exist, event fees are not yet on FestEvent, and head_id column exists.
     */
    public function usesPerHeadBilling(FestEvent $event): bool
    {
        if (($this->resolveSchedule($event)['fee_model'] ?? 'none') !== 'sports_composite') {
            return false;
        }

        // Unified sports: sport events always bill at event level (Head = Event).
        // Season hubs never use per-head once child sport events exist.
        if ($event->event_type === 'sports') {
            if ($event->isSportsDisciplineEvent() || $event->hasSportsFeesConfigured()) {
                return false;
            }
            // Transition: season hub only — per-head if heads still hang off the season and no discipline children.
            if ($event->isSportsSeasonEvent()) {
                $hasChildren = FestEvent::where('parent_event_id', $event->id)
                    ->where('partition_role', 'sports_discipline')
                    ->exists();
                if ($hasChildren) {
                    return false;
                }
            }
            if (! Schema::hasColumn('fest_school_event_fees', 'head_id')) {
                return false;
            }

            return FestItemHead::where('event_id', $event->id)->exists();
        }

        if (! Schema::hasColumn('fest_school_event_fees', 'head_id')) {
            return false;
        }

        return FestItemHead::where('event_id', $event->id)->exists();
    }

    /**
     * Recalculate sports_composite at event level (Head = Event).
     * Prefer this over recalculateForHead for sports sport events.
     */
    public function recalculateForSportsEvent(FestEvent $event, string $schoolId): FestSchoolEventFee
    {
        $composite = $this->sportsCompositeFeeService->calculateForEvent($event, $schoolId);
        $total = $composite['school_reg'] + $composite['student_reg'] + $composite['item_fee'] + $composite['team_fee'];

        $record = FestSchoolEventFee::firstOrNew([
            'event_id' => $event->id,
            'school_id' => $schoolId,
            'head_id' => null,
        ]);

        // Prefer null-head row; if only head-scoped rows remain, reuse the first.
        if (! $record->exists && Schema::hasColumn('fest_school_event_fees', 'head_id')) {
            $legacy = FestSchoolEventFee::where('event_id', $event->id)
                ->where('school_id', $schoolId)
                ->orderByRaw('head_id is null desc')
                ->first();
            if ($legacy) {
                $record = $legacy;
                $record->head_id = null;
            }
        }

        if ($record->exists && (float) $record->total_due > 0 && $record->isFullyPaid()) {
            return $record;
        }

        $record->fill([
            'head_id' => null,
            'school_registration_fee' => $composite['school_reg'],
            'student_registration_fee' => $composite['student_reg'],
            'participation_item_count' => $composite['student_count'],
            'participation_fee' => $composite['item_fee'] + $composite['team_fee'],
            'extra_item_fee' => $composite['team_fee'],
            'total_due' => round($total, 2),
        ]);
        $record->save();

        // Derive status from the actual receipt state (approved/uploaded/none) rather
        // than trusting whatever status happens to already be stored — previously a
        // status of 'approved' set while total_due was (incorrectly) 0 would stick
        // around forever afterward, even once the real amount was recalculated and
        // even if the school's uploaded proof was never actually approved by an admin.
        $record->refreshPaidState();

        if ($this->supportsFeeLines()) {
            $this->syncFeeLines($record, $composite['lines']);
        }

        return $record;
    }

    /** Heads under this event that this school has (or previously had) billable activity for. */
    public function headsWithActivityForSchool(FestEvent $event, string $schoolId): \Illuminate\Support\Collection
    {
        return FestItemHead::where('event_id', $event->id)
            ->orderBy('sort_order')
            ->get()
            ->filter(function (FestItemHead $head) use ($event, $schoolId) {
                $hasRegistrations = FestRegistration::where('event_id', $event->id)
                    ->where('school_id', $schoolId)
                    ->whereIn('status', ['submitted', 'approved'])
                    ->whereHas('item', fn ($q) => $q->where('head_id', $head->id))
                    ->exists();

                if ($hasRegistrations) {
                    return true;
                }

                return FestSchoolEventFee::where('event_id', $event->id)
                    ->where('school_id', $schoolId)
                    ->where('head_id', $head->id)
                    ->exists();
            })
            ->values();
    }

    /** Recalculate (and persist) the fee record for one specific Event Head for one school. */
    public function recalculateForHead(FestEvent $event, string $schoolId, FestItemHead $head): FestSchoolEventFee
    {
        $composite = $this->sportsCompositeFeeService->calculateForHead($head, $schoolId);
        $total = $composite['school_reg'] + $composite['student_reg'] + $composite['item_fee'] + $composite['team_fee'];

        $record = FestSchoolEventFee::firstOrNew([
            'event_id' => $event->id,
            'school_id' => $schoolId,
            'head_id' => $head->id,
        ]);

        // Only freeze records that already have a positive balance fully settled.
        if ($record->exists && (float) $record->total_due > 0 && $record->isFullyPaid()) {
            return $record;
        }

        $record->fill([
            'school_registration_fee' => $composite['school_reg'],
            'student_registration_fee' => $composite['student_reg'],
            'participation_item_count' => $composite['student_count'],
            'participation_fee' => $composite['item_fee'] + $composite['team_fee'],
            'extra_item_fee' => $composite['team_fee'],
            'total_due' => round($total, 2),
        ]);
        $record->save();

        // See recalculateForSportsEvent() for why status is derived, not preserved.
        $record->refreshPaidState();

        if ($this->supportsFeeLines()) {
            $this->syncFeeLines($record, $composite['lines']);
        }

        return $record;
    }

    /**
     * Recalculate every head this school has activity under for this event.
     *
     * @return \Illuminate\Support\Collection<int, FestSchoolEventFee>
     */
    public function recalculateAllHeadsForSchool(FestEvent $event, string $schoolId): \Illuminate\Support\Collection
    {
        return $this->headsWithActivityForSchool($event, $schoolId)
            ->map(fn (FestItemHead $head) => $this->recalculateForHead($event, $schoolId, $head))
            ->values();
    }

    /** Is the fee for one specific Event Head fully paid (or not due)? */
    public function isHeadPaid(FestEvent $event, string $schoolId, int $headId): bool
    {
        $head = FestItemHead::find($headId);
        if (! $head || $head->event_id !== $event->id) {
            return true;
        }

        $fee = FestSchoolEventFee::where('event_id', $event->id)
            ->where('school_id', $schoolId)
            ->where('head_id', $headId)
            ->first();

        if (! $fee) {
            $fee = $this->recalculateForHead($event, $schoolId, $head);
        }

        return $fee->isFullyPaid();
    }

    /** Upload a payment proof against one specific Event Head's fee record. */
    public function attachPaymentForHead(
        FestEvent $event,
        string $schoolId,
        int $headId,
        UploadedFile $proof,
        int $userId,
        ?string $transactionRef = null,
        ?string $bankName = null,
        ?float $amount = null,
    ): FestSchoolEventFee {
        $head = FestItemHead::findOrFail($headId);
        abort_if($head->event_id !== $event->id, 403);

        $fee = $this->recalculateForHead($event, $schoolId, $head);
        abort_if($fee->total_due <= 0, 422, 'No fee due for this Event Head.');
        abort_if($fee->isFullyPaid(), 422, 'Fee already fully paid.');

        $outstanding = $fee->outstandingBalance();
        $payAmount = $amount !== null ? round($amount, 2) : $outstanding;
        abort_if($payAmount <= 0, 422, 'Payment amount must be greater than zero.');
        abort_if($payAmount > $outstanding, 422, 'Payment cannot exceed the outstanding balance of ₹'.number_format($outstanding, 2).'.');

        $path = TenantStorage::storeUploadedFile($proof, "fest-payments/{$schoolId}");

        FeeReceipt::supersedePriorForFeeable($fee);

        $receipt = FeeReceipt::create([
            'feeable_type' => FestSchoolEventFee::class,
            'feeable_id' => $fee->id,
            'file_path' => $path,
            'transaction_ref' => $transactionRef,
            'bank_name' => $bankName,
            'payment_date' => now()->toDateString(),
            'amount' => $payAmount,
            'status' => 'uploaded',
            'uploaded_by_user_id' => $userId,
        ]);

        $fee->update([
            'fee_receipt_id' => $receipt->id,
            'status' => 'proof_uploaded',
        ]);

        return $fee->fresh(['feeReceipt']);
    }

    public function recalculate(FestEvent $event, string $schoolId): FestSchoolEventFee
    {
        // Unified sports: always bill at event level when fees are on the event (or can dual-read).
        if ($event->event_type === 'sports'
            && ($this->resolveSchedule($event)['fee_model'] ?? 'none') === 'sports_composite'
            && ! $this->usesPerHeadBilling($event)
        ) {
            return $this->recalculateForSportsEvent($event, $schoolId);
        }

        if ($this->usesPerHeadBilling($event)) {
            return $this->recalculateAggregateForPerHeadEvent($event, $schoolId);
        }

        $schedule = $this->resolveSchedule($event);
        $school = Tenant::findOrFail($schoolId);
        $itemCount = $this->billableItemCount($event, $schoolId, $schedule);
        $studentCount = $this->billableStudentCount($event, $schoolId);
        $feeModel = $schedule['fee_model'] ?? 'none';

        $schoolRegFee = match ($feeModel) {
            'sports_composite' => $this->sportsCompositeFeeService->schoolRegistrationAmount($school, $schedule),
            'cksc_tiered', 'item_catalog' => $this->schoolRegistrationAmount($school, $schedule),
            default => 0,
        };

        $studentRegFee = 0.0;
        $extraItemFee = 0.0;
        $compositeLines = [];
        $useComposite = $feeModel === 'sports_composite' && $this->supportsSportsCompositeSchema();

        if ($useComposite) {
            $composite = $this->sportsCompositeFeeService->calculate($event, $schoolId, $schedule);
            $schoolRegFee = $composite['school_reg'];
            $studentRegFee = $composite['student_reg'];
            $extraItemFee = $composite['extra_item'];
            $participationFee = $studentRegFee + $extraItemFee;
            $participationCount = $composite['student_count'];
            $compositeLines = $composite['lines'];
        } else {
            if ($feeModel === 'sports_composite') {
                $feeModel = 'item_catalog';
                $schoolRegFee = $this->schoolRegistrationAmount($school, $schedule);
            }

            $participationFee = match ($feeModel) {
                'cksc_tiered' => $this->participationFee($itemCount, $schedule),
                'item_catalog' => $this->itemFeeResolver->participationTotal($event, $schoolId, $schedule),
                'per_item' => $itemCount * (float) ($schedule['per_item_amount'] ?? 0),
                'flat_school' => (float) ($schedule['flat_amount'] ?? $schedule['fee_amount'] ?? 0),
                'per_student' => $studentCount * (float) ($schedule['per_student_amount'] ?? 0),
                default => 0,
            };

            $participationCount = match ($feeModel) {
                'per_student' => $studentCount,
                default => $itemCount,
            };
        }

        $subtotal = $schoolRegFee + $participationFee;
        $total = $this->applySchoolFeeCap($subtotal, $schedule);
        $total = $this->applySchoolFeeMin($total, $schedule);

        if ($total < $subtotal && $participationFee > 0) {
            $participationFee = max(0, round($total - $schoolRegFee, 2));
        }

        $record = FestSchoolEventFee::firstOrNew([
            'event_id' => $event->id,
            'school_id' => $schoolId,
        ]);

        // Only freeze records that already have a positive balance fully settled.
        // Zero-total "approved" stubs (created before fees were configured) must be recalculated.
        if ($record->exists && (float) $record->total_due > 0 && $record->isFullyPaid()) {
            return $record;
        }

        $record->fill(array_filter([
            'school_registration_fee' => $schoolRegFee,
            'student_registration_fee' => $this->supportsSportsCompositeSchema() ? $studentRegFee : null,
            'participation_item_count' => $participationCount,
            'participation_fee' => $participationFee,
            'extra_item_fee' => $this->supportsSportsCompositeSchema() ? $extraItemFee : null,
            'total_due' => $total,
        ], fn ($value) => $value !== null));
        $record->save();

        // Derive status from the actual receipt state rather than preserving whatever
        // was stored — see recalculateForSportsEvent() for the incident this fixes.
        $record->refreshPaidState();

        if ($useComposite && $this->supportsFeeLines()) {
            $this->syncFeeLines($record, $compositeLines);
        } elseif ($this->supportsFeeLines()) {
            $record->lines()->delete();
        }

        return $record;
    }

    /**
     * For sports_composite events billed per Event Head, `recalculate()` no longer manages a
     * single payable record — each head has its own fee record, paid independently (see
     * recalculateForHead/attachPaymentForHead/isHeadPaid). This method keeps every per-head
     * record in sync as a side effect, then returns a read-only, head_id=null "rollup" record
     * (total_due = sum of all heads, status reflects whether all heads are settled) purely for
     * legacy callers that still expect a single FestSchoolEventFee back for display purposes
     * (dashboard tiles, reports, invoice generation). This rollup record is NOT itself payable —
     * attachPayment() refuses to accept a proof against it once per-head billing is active.
     */
    private function recalculateAggregateForPerHeadEvent(FestEvent $event, string $schoolId): FestSchoolEventFee
    {
        $headFees = $this->recalculateAllHeadsForSchool($event, $schoolId);

        $totalDue = round((float) $headFees->sum('total_due'), 2);
        $totalPaid = round((float) $headFees->sum('amount_paid'), 2);
        $schoolRegFee = round((float) $headFees->sum('school_registration_fee'), 2);
        $studentRegFee = round((float) $headFees->sum('student_registration_fee'), 2);
        $itemCount = (int) $headFees->sum('participation_item_count');
        $allApproved = $headFees->isNotEmpty() && $headFees->every(fn (FestSchoolEventFee $f) => $f->isFullyPaid());

        $record = FestSchoolEventFee::firstOrNew([
            'event_id' => $event->id,
            'school_id' => $schoolId,
            'head_id' => null,
        ]);

        $record->fill([
            'school_registration_fee' => $schoolRegFee,
            'student_registration_fee' => $this->supportsSportsCompositeSchema() ? $studentRegFee : null,
            'participation_item_count' => $itemCount,
            'participation_fee' => round($totalDue - $schoolRegFee, 2),
            'total_due' => $totalDue,
            'amount_paid' => $totalPaid,
            'status' => $allApproved ? 'approved' : ($totalPaid > 0 ? 'partial' : 'pending'),
        ]);
        $record->save();

        return $record;
    }

    /** @param  list<array{line_type: string, label: string, quantity: int, unit_amount: float, amount: float, meta?: array}>  $lines */
    private function syncFeeLines(FestSchoolEventFee $fee, array $lines): void
    {
        $fee->lines()->delete();

        foreach ($lines as $line) {
            FestSchoolEventFeeLine::create([
                'fest_school_event_fee_id' => $fee->id,
                'line_type' => $line['line_type'],
                'label' => $line['label'],
                'quantity' => $line['quantity'] ?? 1,
                'unit_amount' => $line['unit_amount'] ?? $line['amount'],
                'amount' => $line['amount'],
                'meta' => $line['meta'] ?? null,
            ]);
        }
    }
    /** @return array<string, mixed> */
    public function breakdown(FestEvent $event, FestSchoolEventFee $fee, array $schedule): array
    {
        if ($this->supportsFeeLines()) {
            $fee->loadMissing('lines');
        }
        $items = [];
        if ($fee->school_registration_fee > 0) {
            $items[] = ['label' => 'Optional event registration add-on', 'amount' => (float) $fee->school_registration_fee];
        }

        $feeModel = $schedule['fee_model'] ?? 'none';

        if ($feeModel === 'sports_composite') {
            if ($this->supportsFeeLines()) {
                foreach ($fee->lines as $line) {
                    $items[] = [
                        'label' => $line->label,
                        'amount' => (float) $line->amount,
                        'line_type' => $line->line_type,
                    ];
                }
            }

            if ($items === [] && $fee->total_due > 0) {
                if ($fee->school_registration_fee > 0) {
                    $items[] = ['label' => 'School registration fee', 'amount' => (float) $fee->school_registration_fee, 'line_type' => 'school_reg'];
                }
                if ($this->supportsSportsCompositeSchema() && $fee->student_registration_fee > 0) {
                    $items[] = ['label' => 'Student registration fees', 'amount' => (float) $fee->student_registration_fee, 'line_type' => 'student_reg'];
                }
                if ($this->supportsSportsCompositeSchema() && $fee->extra_item_fee > 0) {
                    $items[] = ['label' => 'Extra item fees', 'amount' => (float) $fee->extra_item_fee, 'line_type' => 'extra_item'];
                }
            }

            return [
                'items' => $items,
                'total' => (float) $fee->total_due,
                'item_count' => $fee->participation_item_count,
                'student_count' => $fee->participation_item_count,
                'included_quota' => (int) ($schedule['included_items_per_student'] ?? 0),
            ];
        }

        if ($feeModel === 'item_catalog' && $fee->participation_item_count > 0) {
            $catalog = $this->itemFeeResolver->participationBreakdown($event, $fee->school_id, $schedule);
            foreach ($catalog['lines'] as $line) {
                $items[] = [
                    'label' => $line['label'],
                    'amount' => (float) $line['amount'],
                    'item_title' => $line['item_title'] ?? null,
                    'head_name' => $line['head_name'] ?? null,
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
        ?float $amount = null,
    ): FestSchoolEventFee {
        abort_if(
            $this->usesPerHeadBilling($event),
            422,
            'This event bills fees per Event Head — upload payment against the specific head, not the whole event.',
        );

        $fee = $this->recalculate($event, $schoolId);
        abort_if($fee->total_due <= 0, 422, 'No fee due for this event.');
        abort_if($fee->isFullyPaid(), 422, 'Fee already fully paid.');

        $outstanding = $fee->outstandingBalance();
        $payAmount = $amount !== null ? round($amount, 2) : $outstanding;
        abort_if($payAmount <= 0, 422, 'Payment amount must be greater than zero.');
        abort_if($payAmount > $outstanding, 422, 'Payment cannot exceed the outstanding balance of ₹'.number_format($outstanding, 2).'.');

        $path = TenantStorage::storeUploadedFile($proof, "fest-payments/{$schoolId}");

        FeeReceipt::supersedePriorForFeeable($fee);

        $receipt = FeeReceipt::create([
            'feeable_type' => FestSchoolEventFee::class,
            'feeable_id' => $fee->id,
            'file_path' => $path,
            'transaction_ref' => $transactionRef,
            'bank_name' => $bankName,
            'payment_date' => now()->toDateString(),
            'amount' => $payAmount,
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

        return $fee->isFullyPaid();
    }

    /**
     * The fee-clearance check to use for a specific registration: for per-head events, only that
     * registration's own Event Head needs to be paid (a school can have Athletics cleared while
     * Chess is still pending); for every other event/fee model this is identical to isPaid().
     */
    public function isPaidForRegistration(FestEvent $event, FestRegistration $registration): bool
    {
        if (! $this->feeRequired($event)) {
            return true;
        }

        $registration->loadMissing('item');
        $headId = $registration->item?->head_id;

        if ($headId && $this->usesPerHeadBilling($event)) {
            return $this->isHeadPaid($event, $registration->school_id, $headId);
        }

        return $this->isPaid($event, $registration->school_id);
    }

    /**
     * Whether an approved payment already exists against the fee record covering this
     * registration (the specific Event Head's record for per-head billing, the single
     * event-wide record otherwise). Used to lock cancellation — per the confirmed product
     * rule, a registration may only be cancelled pre-payment-approval; once any amount has
     * been approved against its fee record, cancellation is no longer allowed.
     */
    public function hasApprovedPaymentForRegistration(FestEvent $event, FestRegistration $registration): bool
    {
        $registration->loadMissing('item');
        $headId = $registration->item?->head_id;

        $query = FestSchoolEventFee::where('event_id', $event->id)
            ->where('school_id', $registration->school_id);

        if ($headId && $this->usesPerHeadBilling($event)) {
            $query->where('head_id', $headId);
        } else {
            $query->whereNull('head_id');
        }

        $fee = $query->first();

        return $fee && (float) $fee->amount_paid > 0;
    }

    private function applySchoolFeeCap(float $total, array $schedule): float
    {
        $cap = isset($schedule['school_fee_cap']) ? (float) $schedule['school_fee_cap'] : null;

        if ($cap !== null && $cap > 0 && $total > $cap) {
            return $cap;
        }

        return $total;
    }

    private function applySchoolFeeMin(float $total, array $schedule): float
    {
        $min = isset($schedule['school_fee_min']) ? (float) $schedule['school_fee_min'] : null;

        if ($min !== null && $min > 0 && $total > 0 && $total < $min) {
            return $min;
        }

        return $total;
    }

    private function supportsSportsCompositeSchema(): bool
    {
        return Schema::hasColumn('fest_school_event_fees', 'student_registration_fee')
            && Schema::hasColumn('fest_school_event_fees', 'extra_item_fee');
    }

    private function supportsFeeLines(): bool
    {
        return Schema::hasTable('fest_school_event_fee_lines');
    }
}
