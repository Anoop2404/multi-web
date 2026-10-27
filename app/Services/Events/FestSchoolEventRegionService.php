<?php

namespace App\Services\Events;

use App\Models\FestAttendance;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchedule;
use App\Models\FestSchoolEventFee;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Event-specific region choices never change the school's membership region. */
class FestSchoolEventRegionService
{
    public function regions(FestEvent $event)
    {
        $root = $event->rootEvent();

        return app(FestPartitionService::class)->legacyPartitions($root)
            ->loadMissing('region')
            ->filter(fn (FestEvent $child) => $child->tenant_id === $root->tenant_id
                && $child->event_type === $root->event_type
                && $child->region
                && $child->region->tenant_id === $root->tenant_id
                && ($child->region->fest_event_id === null || (int) $child->region->fest_event_id === (int) $root->id))
            ->unique('region_id')->values();
    }

    public function current(FestEvent $event, string $schoolId): ?FestEvent
    {
        $root = $event->rootEvent();
        $key = app(FestSchoolPartitionService::class)->resolvePartitionKey($root, $schoolId);

        return $this->regions($root)->first(fn ($child) => app(FestPartitionService::class)->partitionKey($child) === $key);
    }

    public function select(FestEvent $event, string $schoolId, int $regionId, ?int $actorId = null, bool $override = false, bool $acknowledgePaidInvoice = false): void
    {
        $root = $event->rootEvent();
        abort_if($root->usesPhasedRegionalBilling(), 422, 'Use the phase-specific region selection for this event.');
        $target = $this->regions($root)->firstWhere('region_id', $regionId);
        abort_unless($target, 422, 'That region is not configured for this event.');
        DB::transaction(function () use ($root, $schoolId, $target, $actorId, $override, $acknowledgePaidInvoice) {
            FestEvent::whereKey($root->id)->lockForUpdate()->firstOrFail();
            $current = $this->current($root, $schoolId);
            if ($current && $current->id !== $target->id) {
                if (! $override) {
                    throw ValidationException::withMessages(['region_id' => 'Your event region is locked. Submit a region change request for Sahodaya approval.']);
                }
                if (! $acknowledgePaidInvoice && FestSchoolEventFee::where('event_id', $root->id)->where('school_id', $schoolId)->where('amount_paid', '>', 0)->exists()) {
                    throw ValidationException::withMessages(['region_id' => 'This school has already paid. Confirm that switching regions may change its invoice.']);
                }
                $registrations = FestRegistration::where('event_id', $current->id)->where('school_id', $schoolId)->with(['item', 'participants'])->get();
                $participantIds = $registrations->flatMap->participants->pluck('id');
                foreach ([FestMark::class, FestAttendance::class, FestSchedule::class] as $model) {
                    abort_if($model::whereIn('participant_id', $participantIds)->exists(), 422, 'Region cannot change after attendance, scheduling or mark entry has started.');
                }
                foreach ($registrations as $registration) {
                    $origin = $registration->item?->inherited_from_item_id;
                    abort_unless($origin, 422, 'A registered item is not linked to the shared catalog. Ask Sahodaya to fix its catalog link.');
                    $item = FestEventItem::where('event_id', $target->id)->where('inherited_from_item_id', $origin)->first();
                    abort_unless($item, 422, 'The requested region is missing a registered item.');
                    abort_if(FestRegistration::where('event_id', $target->id)->where('school_id', $schoolId)->where('item_id', $item->id)->active()->exists(), 422, 'This school already has a registration for this item in the requested region.');
                    $registration->update(['event_id' => $target->id, 'item_id' => $item->id]);
                    FestParticipant::where('registration_id', $registration->id)->update(['event_id' => $target->id]);
                    app(FestLevelRegistrationService::class)->syncRegistration($registration->fresh(['participants']));
                }
            }
            app(FestSchoolPartitionService::class)->assign($root, $schoolId, app(FestPartitionService::class)->partitionKey($target), $actorId);
            if ($current && $current->id !== $target->id) {
                app(FestSchoolEventFeeService::class)->recalculate($root, $schoolId);
            }
        });
    }
}
