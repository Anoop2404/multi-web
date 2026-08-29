<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestSchoolFeeSlabSelection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A school's self-selected student-count-strength band, for events whose
 * 'kalolsavam_composite'/'sports_composite' fee schedule sets school_fee_mode to
 * 'student_count_slab' (see FestEventFeeResolver::normalizeEventFeeSettings() and
 * FestSportsCompositeFeeService::schoolRegistrationAmount()). Mirrors
 * FestSchoolPhaseRegionService's lock-on-first-pick / audited-override pattern, scoped to
 * the whole event rather than one phase, since the school registration fee is billed once
 * per event, not once per phase.
 */
class FestSchoolFeeSlabSelectionService
{
    public function select(
        FestEvent $event,
        string $schoolId,
        int $minCount,
        ?int $maxCount,
        ?int $actorId = null,
        bool $override = false,
        ?string $reason = null,
    ): FestSchoolFeeSlabSelection {
        $root = $event->rootEvent();
        $schedule = app(FestSchoolEventFeeService::class)->resolveSchedule($root);
        abort_unless(($schedule['school_fee_mode'] ?? null) === 'student_count_slab', 422, 'This event does not use student-count-slab school fees.');

        $matched = collect($schedule['student_count_slabs'] ?? [])->first(fn ($slab) => (int) ($slab['min_count'] ?? 0) === $minCount
            && (isset($slab['max_count']) && $slab['max_count'] !== null ? (int) $slab['max_count'] : null) === $maxCount);
        abort_if(! $matched, 422, 'That fee band is not configured for this event.');

        $amount = (float) $matched['amount'];

        return DB::transaction(function () use ($root, $schoolId, $minCount, $maxCount, $amount, $actorId, $override, $reason) {
            $selection = FestSchoolFeeSlabSelection::where('event_id', $root->id)
                ->where('school_id', $schoolId)
                ->lockForUpdate()
                ->first();

            $changed = $selection && ((int) $selection->min_count !== $minCount || $selection->max_count !== $maxCount);

            if ($selection && $changed && $selection->isLocked() && ! $override) {
                throw ValidationException::withMessages([
                    'min_count' => 'This fee band is locked because registration has started. Ask an administrator for an audited override.',
                ]);
            }

            $selection ??= new FestSchoolFeeSlabSelection([
                'event_id' => $root->id,
                'school_id' => $schoolId,
                'selected_at' => now(),
                'selected_by' => $actorId,
                // Locked the moment a school makes its first pick, not deferred until their
                // first registration -- a band choice must not be self-service-changeable
                // once made; only the audited admin override ($override above) can move a
                // school afterward. Same rule as FestSchoolPhaseRegionService::select().
                'locked_at' => now(),
            ]);

            $selection->fill([
                'min_count' => $minCount,
                'max_count' => $maxCount,
                'amount' => $amount,
                'changed_at' => $changed ? now() : $selection->changed_at,
                'changed_by' => $changed ? $actorId : $selection->changed_by,
                'change_reason' => $changed ? $reason : $selection->change_reason,
            ]);
            $selection->save();

            app(FestSchoolEventFeeService::class)->recalculate($root, $schoolId);

            return $selection->fresh();
        });
    }

    public function resolve(FestEvent $event, string $schoolId): ?FestSchoolFeeSlabSelection
    {
        $root = $event->rootEvent();

        return FestSchoolFeeSlabSelection::where('event_id', $root->id)
            ->where('school_id', $schoolId)
            ->first();
    }

    public function requireSelection(FestEvent $event, string $schoolId): FestSchoolFeeSlabSelection
    {
        $selection = $this->resolve($event, $schoolId);
        if (! $selection) {
            throw ValidationException::withMessages([
                'min_count' => "Select your school's fee band before registering students.",
            ]);
        }

        return $selection;
    }
}
