<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\FestPageActivity;
use App\Models\FestAppeal;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\FestAppealWildcardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FestAppealController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $appeals = FestAppeal::where('event_id', $event->id)
            ->with(['participant.student', 'participant.registration.item', 'participant.registration.school', 'student', 'item', 'grantedRegistration.school'])
            ->latest()
            ->get();

        // For the "create wildcard entry" form below — same disambiguating fields as
        // the school-admin equivalent (FestEventPortalController::appeals()), since
        // many items share a title across category/gender/type. Schools list is every
        // school under this Sahodaya; that form fetches each school's own students
        // on demand (studentsForWildcard()) rather than preloading all of them here,
        // which at Sahodaya scale (hundreds of schools) would be enormous.
        $classGroupLabels = \App\Support\FestClassGroupScheme::labels(null, $event->rootEvent());
        $wildcardItems = FestEventItem::with('event:id,tenant_id')
            ->where('event_id', $event->id)->where('is_enabled', true)
            ->orderBy('display_order')
            ->get(['id', 'event_id', 'title', 'item_code', 'category', 'class_group', 'age_group', 'gender', 'participant_type'])
            ->map(fn (FestEventItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'category_label' => \App\Support\FestItemCategoryLabel::resolve($item, $classGroupLabels),
                'type_label' => \App\Support\FestItemCategoryLabel::typeLabel($item->participant_type),
                'gender_label' => \App\Support\FestItemCategoryLabel::genderLabel($item->gender),
                'item_code' => $item->item_code,
            ]);
        $wildcardSchools = Tenant::where('parent_id', $this->sahodaya->id)->where('type', 'school')
            ->orderBy('name')->get(['id', 'name']);

        $disqualified = FestParticipant::whereHas('registration', fn ($q) => $q
            ->whereIn('event_id', $event->reportableEventIds()))
            ->whereNotNull('disqualified_at')
            ->with(['student', 'teacher', 'registration.item', 'registration.school'])
            ->get();

        $disqualifyCandidates = FestParticipant::whereHas('registration', fn ($q) => $q
            ->whereIn('event_id', $event->reportableEventIds())
            ->where('status', 'approved'))
            ->whereNull('disqualified_at')
            ->with(['student', 'teacher', 'registration.item', 'registration.school'])
            ->get()
            ->map(fn (FestParticipant $p) => [
                'id'    => $p->id,
                'label' => trim(($p->student?->reg_no ? $p->student->reg_no.' · ' : '')
                    .($p->student?->name ?? $p->teacher?->name ?? 'Participant')
                    .' — '.($p->registration?->school?->name ?? '')
                    .' · '.($p->registration?->item?->title ?? '')),
            ])
            ->values()
            ->all();

        return $this->inertia('Sahodaya/Events/Appeals', $this->withEventActivity($event, FestPageActivity::APPEALS, [
            'event'                => $event,
            'appeals'              => $appeals,
            'disqualified'         => $disqualified,
            'disqualifyCandidates' => $disqualifyCandidates,
            'wildcardItems'        => $wildcardItems,
            'wildcardSchools'      => $wildcardSchools,
        ]));
    }

    /**
     * The wildcard form's school picker fetches each school's students on demand
     * from here instead of index() preloading every school's roster up front —
     * same student fields the school-admin's own preloaded list already sends
     * (id/name/reg_no), just scoped per-school and fetched lazily.
     */
    public function studentsForWildcard(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate(['school_id' => 'required|string']);
        $school = Tenant::where('parent_id', $this->sahodaya->id)->where('type', 'school')->findOrFail($data['school_id']);

        return response()->json(
            Student::where('tenant_id', $school->id)->where('status', 'active')
                ->orderBy('name')->get(['id', 'name', 'reg_no', 'admission_number'])
        );
    }

    /**
     * The school-admin equivalent (FestEventPortalController::storeAppeal) always
     * lands a wildcard as 'pending' for a Sahodaya admin to review separately —
     * appropriate when a SCHOOL is asking for an exception. Here the Sahodaya admin
     * is both the requester and the authority, on a school's behalf, so there's no
     * one else left to review it: create it and grant it in the same action,
     * inside one transaction (FestAppealWildcardService::resolve() nests its own,
     * which Laravel turns into a savepoint) so a failed grant rolls back the
     * appeal record too instead of leaving an orphaned row behind.
     */
    public function storeWildcard(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit, FestAppealWildcardService $wildcards)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if(! $event->appeals_open, 422, 'Appeals are not open for this event.');

        $data = $request->validate([
            'school_id'   => 'required|string',
            'appeal_type' => 'required|in:sahodaya_wildcard,state_wildcard',
            'student_id'  => 'required|exists:students,id',
            'item_id'     => 'required|exists:fest_event_items,id',
            'reason'      => 'required|string|max:2000',
        ]);

        $school = Tenant::where('parent_id', $this->sahodaya->id)->where('type', 'school')->findOrFail($data['school_id']);

        $student = Student::findOrFail($data['student_id']);
        abort_if($student->tenant_id !== $school->id, 422, 'Student does not belong to the selected school.');

        $item = FestEventItem::findOrFail($data['item_id']);
        abort_if($item->event_id !== $event->id, 422, 'Item does not belong to this event.');

        $duplicate = FestAppeal::where('event_id', $event->id)
            ->where('student_id', $student->id)
            ->where('item_id', $item->id)
            ->whereIn('appeal_type', FestAppeal::WILDCARD_TYPES)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();
        abort_if($duplicate, 422, 'An active wildcard appeal already exists for this student and item.');

        $appeal = DB::transaction(function () use ($event, $data, $student, $item, $request, $wildcards) {
            $appeal = FestAppeal::create([
                'event_id'             => $event->id,
                'appeal_type'          => $data['appeal_type'],
                'student_id'           => $student->id,
                'item_id'              => $item->id,
                'reason'               => $data['reason'],
                'fee_amount'           => $event->appeal_fee_amount,
                'status'               => 'pending',
                'submitted_by_user_id' => $request->user()->id,
            ]);

            return $wildcards->resolve($appeal, 'approved', 'Created and approved directly by Sahodaya admin.', $request->user()->id);
        });

        $audit->festAppealResolved($appeal, 'approved');

        return back()->with('success', 'Wildcard entry created and approved for '.$student->name.'.');
    }

    public function resolve(Request $request, string $tenantId, FestEvent $event, FestAppeal $appeal, PlatformAuditLogger $audit, FestAppealWildcardService $wildcards)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($appeal->event_id !== $event->id, 403);

        // Prevent re-resolving an already-resolved appeal (approved ↔ rejected flip-flop).
        abort_if($appeal->status !== 'pending', 422, 'Only pending appeals can be resolved.');

        $data = $request->validate([
            'status'          => 'required|in:approved,rejected',
            'resolution_note' => 'nullable|string|max:1000',
        ]);

        $wildcards->resolve($appeal, $data['status'], $data['resolution_note'] ?? null, $request->user()->id);

        $audit->festAppealResolved($appeal, $data['status']);

        return back()->with('success', 'Appeal '.$data['status'].'.');
    }

    public function markFeePaid(string $tenantId, FestEvent $event, FestAppeal $appeal, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($appeal->event_id !== $event->id, 403);

        // Only pending appeals should have their fee tracked — rejected/approved appeals
        // should not have their fee status mutated retroactively.
        abort_if($appeal->status !== 'pending', 422, 'Fee can only be marked paid for pending appeals.');

        $appeal->update(['fee_paid_at' => now()]);

        $audit->festAppealResolved($appeal, 'fee_paid');

        return back()->with('success', 'Appeal fee marked as paid.');
    }

    public function disqualify(Request $request, string $tenantId, FestEvent $event, FestParticipant $participant, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_unless(in_array($participant->registration->event_id, $event->reportableEventIds(), true), 403);

        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $participant->update([
            'disqualified_at'          => now(),
            'disqualification_reason'  => $data['reason'],
        ]);

        $audit->festEvent($event, FestPageActivity::APPEALS, 'fest.participant.disqualified', 'Participant disqualified', [
            'participant_id' => $participant->id,
        ]);

        return back()->with('success', 'Participant disqualified.');
    }

    public function reinstate(string $tenantId, FestEvent $event, FestParticipant $participant, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_unless(in_array($participant->registration->event_id, $event->reportableEventIds(), true), 403);

        $participant->update([
            'disqualified_at'         => null,
            'disqualification_reason' => null,
        ]);

        $audit->festEvent($event, FestPageActivity::APPEALS, 'fest.participant.reinstated', 'Disqualification removed', [
            'participant_id' => $participant->id,
        ]);

        return back()->with('success', 'Disqualification removed.');
    }
}
