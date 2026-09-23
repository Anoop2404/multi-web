<?php

namespace App\Services\State\Fest;

use App\Models\State\StateAttendance;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestMark;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateQualifierEntry;
use App\Models\State\StateQualifierIntake;
use App\Models\State\StateSahodaya;

/**
 * Phase 2 of the State Kalotsav module — the event Overview.
 *
 * Every figure here is counted from State operational tables only. The State module is explicitly
 * not allowed to reach into Sahodaya tenant databases for its own reporting, and the certified
 * qualifier snapshot is its source of truth, so "how many schools took part" is answered from the
 * snapshot rather than by asking each Sahodaya's database who its schools are.
 *
 * Counts group on the canonical Sahodaya identity (Phase 1) rather than the raw submission key, so
 * a Sahodaya promoted between events appears once, not twice.
 */
class StateFestOverviewService
{
    /** @return array<string, mixed> */
    public function forEvent(StateFestEvent $event): array
    {
        $registrations = StateFestRegistration::where('state_event_id', $event->id);

        $sahodayaIds = (clone $registrations)->whereNotNull('sahodaya_id')->distinct()->pluck('sahodaya_id');
        $directory = StateSahodaya::whereIn('id', $sahodayaIds)->get();

        return [
            'participation' => $this->participation($event, $registrations, $directory, $sahodayaIds),
            'intake'        => $this->intake($event),
            'conduct'       => $this->conduct($event),
        ];
    }

    /** @return array<string, mixed> */
    private function participation(StateFestEvent $event, $registrations, $directory, $sahodayaIds): array
    {
        return [
            'sahodayas'          => $sahodayaIds->count(),
            // "Arrived from outside the platform" — origin, not current tenancy, so a Sahodaya
            // promoted mid-season does not silently move between the two columns.
            'from_outside'       => $directory->where('origin', StateSahodaya::ORIGIN_EXTERNAL)->count(),
            'managed'            => $directory->where('origin', StateSahodaya::ORIGIN_MANAGED)->count(),
            // Schools are counted from the qualifier snapshot the Sahodaya certified, never by
            // querying its tenant database.
            'schools'            => (clone $registrations)->whereNotNull('school_id')->distinct()->count('school_id'),
            'registrations'      => (clone $registrations)->count(),
            'approved'           => (clone $registrations)->where('status', 'approved')->count(),
            'items_entered'      => (clone $registrations)->whereNotNull('item_id')->distinct()->count('item_id'),
        ];
    }

    /** @return array<string, mixed> */
    private function intake(StateFestEvent $event): array
    {
        $intakes = StateQualifierIntake::where('state_program_id', $event->state_program_id)
            ->where('status', '!=', 'draft');

        $entries = StateQualifierEntry::whereIn('intake_id', (clone $intakes)->select('id'));

        return [
            'submissions'       => (clone $intakes)->count(),
            'awaiting_scrutiny' => (clone $intakes)->where('status', 'received')->count(),
            'approved_intakes'  => (clone $intakes)->where('status', 'approved')->count(),
            'rejected_intakes'  => (clone $intakes)->where('status', 'rejected')->count(),
            'entries'           => (clone $entries)->count(),
            'entries_pending'   => (clone $entries)->where('status', 'pending')->count(),
            'entries_approved'  => (clone $entries)->where('status', 'approved')->count(),
            'entries_rejected'  => (clone $entries)->where('status', 'rejected')->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function conduct(StateFestEvent $event): array
    {
        $approved = StateFestRegistration::where('state_event_id', $event->id)->where('status', 'approved')->count();
        $attendanceMarked = StateAttendance::where('state_event_id', $event->id)->distinct()->count('registration_id');
        $marked = StateFestMark::where('state_event_id', $event->id)->distinct()->count('registration_id');

        return [
            'attendance_marked'  => $attendanceMarked,
            'attendance_pending' => max($approved - $attendanceMarked, 0),
            'marks_entered'      => $marked,
            'marks_pending'      => max($approved - $marked, 0),
            'results_published'  => (bool) $event->results_published,
            'scoring_locked'     => (bool) $event->scoring_locked,
            // Deliberately absent until the phases that own them are built, rather than reported as
            // a misleading zero: scheduled/unscheduled items (Phase 5) and certificate progress
            // (Phase 9).
        ];
    }
}
