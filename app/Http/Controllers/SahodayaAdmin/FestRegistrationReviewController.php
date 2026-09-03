<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\SahodayaAdmin\Concerns\BuildsItemHeadReportContext;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Models\SchoolRegionAssignment;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\EventLifecycleGate;
use App\Services\Events\FestEventNotifier;
use App\Services\Events\FestParticipationPolicyService;
use App\Services\Events\FestRegistrationApprovalService;
use App\Services\Events\FestMandatoryItemService;
use App\Support\ExcelExport;
use App\Services\Events\FestRegistrationBulkService;
use App\Support\FestClassGroupScheme;
use App\Support\FestItemCategoryLabel;
use App\Support\FestPageActivity;
use App\Services\Events\FestRegistrationCreateService;
use App\Services\Events\FestRegistrationImportService;
use App\Services\Events\FestRegistrationEligibilityService;
use App\Services\Events\FestRegistrationService;
use App\Services\Events\FestSchoolEventFeeService;
use App\Services\Audit\PlatformAuditLogger;
use App\Support\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FestRegistrationReviewController extends SahodayaAdminController
{
    use BuildsItemHeadReportContext;

    public function index(string $tenantId, FestEvent $event, Request $request)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $event->load(['items' => fn ($q) => $q->where('is_enabled', true)->orderBy('title')->with('phase:id,source_phase_id')]);
        $event->setRelation('items', \App\Services\Events\FestHeadItemNavigationService::filterToOwnPhase($event->items, $event));

        $headId = $this->resolveHeadQueryParam($request->query('head_id') ?? $request->query('head'));
        $itemId = $request->integer('item_id') ?: null;
        $itemIds = $this->itemIdsForHeadFilter($event, $headId, $itemId);
        $filterSchoolId = $request->input('school_id') ?: null;
        $filterStatus = $request->input('status') ?: null;
        $filterRegionId = $request->input('region_id') ?: null;
        $filterClassGroup = $request->input('class_group') ?: null;

        if ($filterClassGroup) {
            $classGroupItemIds = $event->items->where('class_group', $filterClassGroup)->pluck('id')->all();
            $itemIds = $itemIds === null ? $classGroupItemIds : array_values(array_intersect($itemIds, $classGroupItemIds));
        }

        // When a region filter is active, resolve school IDs in that region and
        // restrict the query to only those schools.
        $regionSchoolIds = null;
        if ($filterRegionId) {
            $year = AcademicYear::forSahodaya($this->sahodaya->id);
            $regionSchoolIds = SchoolRegionAssignment::forTenant($this->sahodaya->id)
                ->forYear($year)
                ->where('region_id', $filterRegionId)
                ->pluck('school_id')
                ->all();
            // Also apply to school_id filter so they work together.
            if ($filterSchoolId && ! in_array($filterSchoolId, $regionSchoolIds, true)) {
                $filterSchoolId = null;
            }
        }

        $feeService = app(FestSchoolEventFeeService::class);

        // Previously ->get() with no limit — an event with a large Sahodaya (many
        // schools, thousands of students) could return thousands of rows on a single
        // page load. school_id/item_id/status/search now all run as real query
        // constraints (school_id and status were client-side-only filters before, doing
        // nothing to reduce what got fetched) and the result set is paginated.
        // See docs/SCALE_AND_PAGINATION_PLAN.md §2.
        $scopedQuery = fn () => $this->scopedRegistrationsQuery($event, $itemIds, $filterSchoolId, $request->input('search'), $regionSchoolIds);

        $registrations = $scopedQuery()
            ->when($filterStatus, fn ($q) => $q->where('status', $filterStatus))
            ->with(['item', 'participants.student', 'participants.teacher', 'participants.group'])
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $classGroupLabels = FestClassGroupScheme::labels(null, $event->rootEvent());
        $artsCategoryLabels = config('fest_item_taxonomy.arts_category', []);
        $registrations->getCollection()->each(function (FestRegistration $reg) use ($classGroupLabels, $artsCategoryLabels) {
            if ($reg->item) {
                $reg->item->setAttribute('category_label', FestItemCategoryLabel::resolve($reg->item, $classGroupLabels, $artsCategoryLabels));
            }
        });

        $classGroupOptions = $event->items
            ->pluck('class_group')
            ->filter(fn ($cg) => $cg && $cg !== 'open')
            ->unique()
            ->map(fn ($cg) => ['value' => $cg, 'label' => FestClassGroupScheme::resolveItemLabel($classGroupLabels, $cg)])
            ->sortBy('label')
            ->values();

        // Count of registrations matching the school/item/search filters (deliberately
        // ignoring the on-screen status filter — the number that matters for bulk
        // approve/reject is always "how many submitted ones match", regardless of what
        // status the admin happens to be viewing right now) so the "select all N
        // matching this filter" action can show an accurate count without ever loading
        // every row. Backs the redesigned select-all below instead of the old client-side
        // "select everything currently in memory", which silently becomes page-scoped
        // once this list is paginated.
        $pendingMatchingCount = $scopedQuery()->where('status', 'submitted')->count();

        $schools = Tenant::where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->orderBy('name')
            ->pluck('name', 'id');

        // Registrations.vue resolves every registration row's school NAME by looking it
        // up in a schools map — previously the same approved-only $schools map above,
        // which is meant to gate which schools a NEW "register on behalf" entry can be
        // created for. A school whose membership_status later changes away from
        // 'approved' (rejected, expired, pending renewal, ...) dropped out of that map
        // entirely, so every one of its EXISTING registrations silently rendered as a
        // raw UUID instead of a name — confirmed live with a school whose membership was
        // rejected after it had already-approved fest registrations. Display needs every
        // school that could possibly own a registration here, not just currently-approved
        // ones; only the "create a new registration for..." dropdown should stay
        // approved-only.
        $schoolNames = Tenant::where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->orderBy('name')
            ->pluck('name', 'id');

        $registerStudents = [];
        $existingSchoolRegistrations = [];
        $registerSchoolId = $request->input('school_id');
        if ($registerSchoolId && $schools->has($registerSchoolId)) {
            $students = Student::where('tenant_id', $registerSchoolId)
                ->active()
                ->with('schoolClass')
                ->orderBy('name')
                ->get();
            $registerStudents = app(FestRegistrationEligibilityService::class)
                ->annotateStudents($students, $event)
                ->values()
                ->all();

            $existingSchoolRegistrations = \App\Models\FestRegistration::whereIn('event_id', $event->reportableEventIds())
                ->where('school_id', $registerSchoolId)
                ->with([
                    'item:id,title,category,class_group,age_group,gender,item_code',
                    'participants' => fn ($q) => $q->with(['student:id,name,reg_no', 'teacher:id,name,reg_no']),
                ])
                ->get()
                ->map(fn (\App\Models\FestRegistration $r) => [
                    'id'          => $r->id,
                    'item_id'     => $r->item_id,
                    'status'      => $r->status,
                    'team_name'   => $r->team_name,
                    'performers'  => $r->participants
                        ->where('participant_role', 'performer')
                        ->map(fn (FestParticipant $p) => [
                            'id'     => $p->id,
                            'name'   => $p->student?->name ?? $p->teacher?->name ?? 'Participant #'.$p->id,
                            'reg_no' => $p->student?->reg_no ?? $p->teacher?->reg_no ?? null,
                        ])->values()->all(),
                    'standbys'    => $r->participants
                        ->where('participant_role', 'standby')
                        ->map(fn (FestParticipant $p) => [
                            'id'     => $p->id,
                            'name'   => $p->student?->name ?? $p->teacher?->name ?? 'Participant #'.$p->id,
                            'reg_no' => $p->student?->reg_no ?? $p->teacher?->reg_no ?? null,
                        ])->values()->all(),
                ])->values()->all();
        }

        $selectedHeadId = match (true) {
            $headId === 0 => 'other',
            $headId !== null => $headId,
            default => null,
        };

        $schoolRegions = [];
        $regionOptions = collect();
        if (in_array($event->event_type, ['kalolsavam', 'sports', 'english_fest', 'science_fest', 'kids_fest', 'teacher_fest'], true)) {
            $schoolRegions = SchoolRegionAssignment::query()
                ->where('school_region_assignments.tenant_id', $this->sahodaya->id)
                ->where('school_region_assignments.academic_year', AcademicYear::forSahodaya($this->sahodaya->id))
                ->join('regions', 'regions.id', '=', 'school_region_assignments.region_id')
                ->pluck('regions.name', 'school_region_assignments.school_id')
                ->all();

            $regionOptions = \App\Models\Region::forTenant($this->sahodaya->id)
                ->active()
                ->globalOnly()
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        $childEvents = $this->scopedChildEventOptions($event);

        return $this->inertia('Sahodaya/Events/Registrations', $this->withEventActivity($event, FestPageActivity::REGISTRATIONS, [
            'event'                        => $event,
            'registrations'                => $registrations,
            'pendingMatchingCount'         => $pendingMatchingCount,
            'schools'                    => $schools,
            'schoolNames'                => $schoolNames,
            'schoolRegions'              => $schoolRegions,
            'regionOptions'              => $regionOptions,
            'childEvents'                => $childEvents,
            'feeRequired'                => $feeService->feeRequired($event),
            'registerStudents'           => $registerStudents,
            'existingSchoolRegistrations' => $existingSchoolRegistrations,
            'registerSchoolId'           => $registerSchoolId,
            'eventItems'                 => $this->itemsWithCategoryLabel($event),
            'classGroupOptions'          => $classGroupOptions,
            'filters'                    => [
                'search'      => $request->input('search', ''),
                'school_id'   => $filterSchoolId ?? '',
                'status'      => $filterStatus ?? '',
                'region_id'   => $filterRegionId ?? '',
                'class_group' => $filterClassGroup ?? '',
            ],
            'selectedHeadId'             => $selectedHeadId,
            'selectedItemId'             => $itemId,
            'competitionUrl'             => "/sahodaya-admin/{$this->sahodaya->id}/events/{$event->id}/competition",
        ]));
    }

    /**
     * Shared school_id/item_id(s)/search scoping used by both index() and
     * printApproved() — kept in one place so the two don't drift (see
     * docs/SCALE_AND_PAGINATION_PLAN.md §4). Callers add their own ->where('status', ...)
     * and eager-loads on top since those differ between the two.
     *
     * @param  ?list<int>  $itemIds
     */
    /**
     * The event's enabled items (same set as $event->items after the eager-load in
     * index()) with a display-only 'category_label' attribute added — class/age
     * bracket if the item has one, else its arts genre, else null. Used by
     * Registrations.vue's item pickers so admins can tell same-named items in
     * different categories apart.
     */
    private function itemsWithCategoryLabel(FestEvent $event)
    {
        $classGroupLabels = FestClassGroupScheme::labels(null, $event->rootEvent());
        $artsCategoryLabels = config('fest_item_taxonomy.arts_category', []);

        return $event->items->values()->map(function (FestEventItem $item) use ($classGroupLabels, $artsCategoryLabels) {
            $row = $item->toArray();
            $row['category_label'] = FestItemCategoryLabel::resolve($item, $classGroupLabels, $artsCategoryLabels);

            return $row;
        });
    }

    /**
     * @param  ?list<int>  $itemIds
     * @param  ?list<string>  $regionSchoolIds  school IDs scoped to a region filter
     */
    private function scopedRegistrationsQuery(FestEvent $event, ?array $itemIds, ?string $schoolId, ?string $search, ?array $regionSchoolIds = null)
    {
        $scopedItemIds = $itemIds === null ? null : $event->reportableItemIds($itemIds);

        return FestRegistration::whereIn('event_id', $event->reportableEventIds())
            ->when($scopedItemIds !== null, fn ($q) => $q->whereIn('item_id', $scopedItemIds))
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->when($regionSchoolIds !== null && $schoolId === null, fn ($q) => $q->whereIn('school_id', $regionSchoolIds))
            ->when(filled($search), function ($q) use ($search) {
                $term = '%'.$search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->whereHas('participants.student', fn ($s) => $s
                        ->where('name', 'like', $term)
                        ->orWhere('reg_no', 'like', $term)
                        ->orWhere('admission_number', 'like', $term))
                        ->orWhereHas('participants.teacher', fn ($t) => $t
                            ->where('name', 'like', $term)
                            ->orWhere('reg_no', 'like', $term));
                });
            });
    }

    public function storeOnBehalf(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'school_id'        => 'required|exists:tenants,id',
            'item_id'          => 'required|exists:fest_event_items,id',
            'team_name'        => 'nullable|string|max:255',
            'coach_name'       => 'nullable|string|max:255',
            'coach_phone'      => 'nullable|string|max:40',
            'manager_name'     => 'nullable|string|max:255',
            'manager_phone'    => 'nullable|string|max:40',
            'student_ids'      => 'required|array|min:1',
            'student_ids.*'    => 'integer|exists:students,id',
            'standby_ids'      => 'nullable|array|max:2',
            'standby_ids.*'    => 'integer|exists:students,id',
            'auto_approve'     => 'nullable|boolean',
        ]);

        $school = Tenant::where('id', $data['school_id'])
            ->where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->firstOrFail();

        $item = FestEventItem::where('id', $data['item_id'])->where('event_id', $event->id)->firstOrFail();

        try {
            $registration = app(FestRegistrationCreateService::class)->createForSchool(
                $event,
                $item,
                $school,
                $data['student_ids'],
                $data['standby_ids'] ?? [],
                $data['team_name'] ?? null,
                skipSchoolClosedCheck: true,
                teamContacts: [
                    'coach_name' => $data['coach_name'] ?? null,
                    'coach_phone' => $data['coach_phone'] ?? null,
                    'manager_name' => $data['manager_name'] ?? null,
                    'manager_phone' => $data['manager_phone'] ?? null,
                ],
            );
        } catch (ValidationException $e) {
            return back()
                ->withInput()
                ->with('error', $this->validationFailureMessage($e));
        }

        if ($request->boolean('auto_approve')) {
            $policy = app(FestParticipationPolicyService::class)->resolveForEvent($event);
            $feeService = app(FestSchoolEventFeeService::class);

            if (($policy['require_fee_before_approval'] ?? false) && $feeService->feeRequired($event) && ! $feeService->isPaidForRegistration($event, $registration)) {
                $audit->festEvent($event, FestPageActivity::REGISTRATIONS, 'fest.registration.on_behalf', "Registration entered for {$school->name}: {$item->title}");
                $message = 'Registration created for '.$school->name.' — pending fee payment before approval.';
            } else {
                app(FestRegistrationApprovalService::class)->approve($registration->load(['participants', 'item', 'event']));
                app(FestEventNotifier::class)->registrationApproved($registration);
                $audit->festRegistrationApproved($registration);
                $message = 'Registration created and approved for '.$school->name.'.';
            }
        } else {
            $audit->festEvent($event, FestPageActivity::REGISTRATIONS, 'fest.registration.on_behalf', "Registration entered for {$school->name}: {$item->title}");
            $message = 'Registration submitted for '.$school->name.' — pending approval.';
        }

        return back()->with('success', $message);
    }

    public function importForm(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        return $this->inertia('Sahodaya/Events/Registrations/Import', $this->withEventActivity($event, FestPageActivity::REGISTRATIONS_IMPORT, [
            'event' => $event,
        ]));
    }

    private function validationFailureMessage(ValidationException $e): string
    {
        $messages = collect($e->errors())->flatten()->filter()->values();

        return $messages->first() ?: $e->getMessage();
    }

    public function approve(Request $request, string $tenantId, FestEvent $event, FestRegistration $registration, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        // Registrations for a partitioned hub are actually created against the school's
        // assigned REGION child event (see FestRegistrationCreateService), not the hub
        // itself — a strict $registration->event_id !== $event->id check 403'd every
        // individual approve/reject/cancel/substitute action against a child-event
        // registration, even though the review page (list()) already lists them via
        // reportableEventIds(). Bulk actions (FestRegistrationBulkService) already used
        // reportableEventIds() correctly; this brings the individual actions in line.
        abort_unless(in_array($registration->event_id, $event->reportableEventIds(), true), 403);

        // LIFE-03 fix (functional audit, 2026-08-11/12): this endpoint had no
        // guard against the registration's current status — unlike
        // FestRegistrationBulkService::approveMany(), which only ever targets
        // 'submitted' rows, a repeat click (or a stale/replayed request)
        // against an already-approved/rejected/withdrawn/waitlisted
        // registration would silently re-run approval side effects (chest
        // number re-assignment, a second "approved" notification/audit
        // entry). Matches the bulk service's scope exactly.
        abort_unless($registration->status === 'submitted', 422, 'This registration is not awaiting approval — it is already '.$registration->status.'.');

        EventLifecycleGate::allowRegistrationReview($event, $request->boolean('override_lifecycle'), $registration->item);

        $policy = app(FestParticipationPolicyService::class)->resolveForEvent($event);
        $feeService = app(FestSchoolEventFeeService::class);

        if (! $request->boolean('override_lifecycle') && ($policy['require_fee_before_approval'] ?? false) && $feeService->feeRequired($event)) {
            $feeLabel = $feeService->usesPerHeadBilling($event) ? 'Event Head fee' : 'Event fee';
            abort_unless(
                $feeService->isPaidForRegistration($event, $registration),
                422,
                "The {$feeLabel} for this registration must be paid and approved before registration approval."
            );
        }

        $mandatoryService = app(FestMandatoryItemService::class);
        $missingMandatory = $mandatoryService->missingForSchool($event, $registration->school_id)
            ->filter(fn ($item) => (int) $item->id !== (int) $registration->item_id);
        if ($missingMandatory->isNotEmpty()) {
            abort(422, 'Mandatory items not registered: '.$missingMandatory->pluck('title')->join(', '));
        }

        app(FestRegistrationApprovalService::class)->approve($registration->load(['participants', 'item', 'event']));

        app(FestEventNotifier::class)->registrationApproved($registration);
        $audit->festRegistrationApproved($registration);

        return back()->with('success', 'Registration approved.');
    }

    public function reject(Request $request, string $tenantId, FestEvent $event, FestRegistration $registration, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        // Registrations for a partitioned hub are actually created against the school's
        // assigned REGION child event (see FestRegistrationCreateService), not the hub
        // itself — a strict $registration->event_id !== $event->id check 403'd every
        // individual approve/reject/cancel/substitute action against a child-event
        // registration, even though the review page (list()) already lists them via
        // reportableEventIds(). Bulk actions (FestRegistrationBulkService) already used
        // reportableEventIds() correctly; this brings the individual actions in line.
        abort_unless(in_array($registration->event_id, $event->reportableEventIds(), true), 403);

        EventLifecycleGate::allowRegistrationReview($event, $request->boolean('override_lifecycle'), $registration->item);

        // The UI only ever shows "Reject" for a still-submitted registration (never an
        // approved/paid one — see Registrations.vue), but this endpoint doesn't otherwise
        // check status, so a direct request against an approved+paid registration would
        // silently reject it with no reason and no FestFeeCredit — the same "money just
        // disappears" gap the docs flagged for bulk rejection, before rejectMany() was fixed
        // to only ever target 'submitted' rows. Block it here instead of duplicating that
        // fix: an already-approved, paid registration must go through cancelWithRefund(),
        // which requires a reason and creates the credit.
        abort_if(
            app(FestSchoolEventFeeService::class)->hasApprovedPaymentForRegistration($event, $registration),
            422,
            'This registration already has an approved payment — use "Cancel & refund" instead, which requires a reason and credits the school.'
        );

        // LIFE-03 fix (functional audit, 2026-08-11/12): the payment check
        // above only ever covered the approved-and-paid case; it did not stop
        // this endpoint from re-rejecting an already-rejected/withdrawn/
        // waitlisted registration, silently overwriting rejection_reason/
        // rejected_at/rejected_by_user_id and re-firing the rejection
        // notification and audit log. Matches
        // FestRegistrationBulkService::rejectMany()'s 'submitted'-only scope.
        abort_unless($registration->status === 'submitted', 422, 'This registration is not awaiting approval — it is already '.$registration->status.'.');

        // Note on LIFE-04 (functional audit, 2026-08-11/12): a results-published
        // guard is deliberately NOT duplicated here — EventLifecycleGate::
        // allowRegistrationReview() above already blocks this once results are
        // published, unless the caller explicitly passes override_lifecycle
        // (an intentional admin escape hatch for late corrections). Adding an
        // unconditional check here would silently remove that override
        // capability. FestRegistrationService::cancel() has no equivalent
        // override concept, so it gets its own unconditional guard instead —
        // see the comment there.

        $data = $request->validate(['rejection_reason' => 'required|string|max:500']);
        $reason = $data['rejection_reason'];

        $registration->loadMissing('item', 'participants');
        $headId = $registration->item?->head_id;

        $registration->update([
            'status'              => 'rejected',
            'rejection_reason'    => $reason ?: null,
            'rejected_at'         => now(),
            'rejected_by_user_id' => $request->user()->id,
        ]);

        // Free up the per-student registration fee if this was the student's last active
        // item — must run BEFORE recalculate() so the composite fee model sees it. See
        // FestLevelRegistrationService::deactivateIfNoActiveItems(). Mirrors the same fix in
        // FestRegistrationBulkService::rejectMany(), needed here too since this single-item
        // reject path doesn't go through that service.
        $levelService = app(\App\Services\Events\FestLevelRegistrationService::class);
        foreach ($registration->participants->pluck('student_id')->filter()->unique() as $studentId) {
            $levelService->deactivateIfNoActiveItems($event, $studentId);
        }

        app(FestSchoolEventFeeService::class)->recalculate($event, $registration->school_id);

        if ($headId) {
            app(FestRegistrationApprovalService::class)->promoteNextWaitlisted($event, (int) $headId);
        }

        // LIFE-06 fix — see FestQualificationService::revokeQualificationsForRegistration().
        app(\App\Services\Events\FestQualificationService::class)->revokeQualificationsForRegistration($registration);

        app(FestEventNotifier::class)->registrationRejected($registration, $reason);
        $audit->festRegistrationRejected($registration);

        return back()->with('success', 'Registration rejected.');
    }

    public function cancel(Request $request, string $tenantId, FestEvent $event, FestRegistration $registration, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        // Registrations for a partitioned hub are actually created against the school's
        // assigned REGION child event (see FestRegistrationCreateService), not the hub
        // itself — a strict $registration->event_id !== $event->id check 403'd every
        // individual approve/reject/cancel/substitute action against a child-event
        // registration, even though the review page (list()) already lists them via
        // reportableEventIds(). Bulk actions (FestRegistrationBulkService) already used
        // reportableEventIds() correctly; this brings the individual actions in line.
        abort_unless(in_array($registration->event_id, $event->reportableEventIds(), true), 403);

        abort_unless(
            app(FestRegistrationService::class)->canAdminCancel($registration, $event),
            422,
            'Cannot cancel — results are published or the fee for this registration has already been paid and approved.'
        );

        app(FestRegistrationService::class)->cancel($registration, $event);
        $audit->festRegistrationCancelled($registration);

        return back()->with('success', 'Registration cancelled.');
    }

    /**
     * Cancel a registration that already has an approved payment — the case cancel() above
     * deliberately refuses. See FestRegistrationService::cancelWithRefund() and
     * docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §4/§9.4. A distinct action rather than a
     * change to cancel()'s existing behavior, so nothing about the default cancel flow changes.
     */
    public function cancelWithRefund(Request $request, string $tenantId, FestEvent $event, FestRegistration $registration)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        // Registrations for a partitioned hub are actually created against the school's
        // assigned REGION child event (see FestRegistrationCreateService), not the hub
        // itself — a strict $registration->event_id !== $event->id check 403'd every
        // individual approve/reject/cancel/substitute action against a child-event
        // registration, even though the review page (list()) already lists them via
        // reportableEventIds(). Bulk actions (FestRegistrationBulkService) already used
        // reportableEventIds() correctly; this brings the individual actions in line.
        abort_unless(in_array($registration->event_id, $event->reportableEventIds(), true), 403);

        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        abort_unless(
            app(FestRegistrationService::class)->canAdminCancelWithRefund($registration, $event),
            422,
            'Cannot cancel — results are published, the registration is already closed, or it was never paid (use the regular cancel action instead).'
        );

        app(FestRegistrationService::class)->cancelWithRefund($registration, $event, $data['reason']);

        return back()->with('success', 'Registration cancelled and any applicable fee credit recorded.');
    }

    public function substitute(string $tenantId, FestEvent $event, FestRegistration $registration, FestParticipant $performer, FestParticipant $standby)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        // Registrations for a partitioned hub are actually created against the school's
        // assigned REGION child event (see FestRegistrationCreateService), not the hub
        // itself — a strict $registration->event_id !== $event->id check 403'd every
        // individual approve/reject/cancel/substitute action against a child-event
        // registration, even though the review page (list()) already lists them via
        // reportableEventIds(). Bulk actions (FestRegistrationBulkService) already used
        // reportableEventIds() correctly; this brings the individual actions in line.
        abort_unless(in_array($registration->event_id, $event->reportableEventIds(), true), 403);
        abort_if($performer->registration_id !== $registration->id || $standby->registration_id !== $registration->id, 403);

        app(FestRegistrationService::class)->substitutePerformer($performer, $standby);

        return back()->with('success', 'Participant substituted.');
    }

    /**
     * The school's students eligible to be added to this registration's item — used by the
     * "Manage participants" add-student picker. Unlike the on-behalf registration picker
     * (which only does a rough client-side eligibility approximation), this runs the real
     * FestRegistrationEligibilityService check server-side.
     */
    public function eligibleStudents(Request $request, string $tenantId, FestEvent $event, FestRegistration $registration)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_unless(in_array($registration->event_id, $event->reportableEventIds(), true), 403);

        $registration->loadMissing('item', 'participants');
        $item = $registration->item;
        abort_if(! $item, 422, 'This registration has no item.');

        $existingStudentIds = $registration->participants->pluck('student_id')->filter()->all();

        $search = trim((string) $request->query('search', ''));
        $studentQuery = Student::where('tenant_id', $registration->school_id)->active()->with('schoolClass')->orderBy('name');
        if ($existingStudentIds) {
            $studentQuery->whereNotIn('id', $existingStudentIds);
        }
        if ($search !== '') {
            $term = strtolower($search);
            $studentQuery->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"])
                  ->orWhereRaw('LOWER(reg_no) LIKE ?', ["%{$term}%"]);
            });
        }

        $students = $studentQuery->limit(500)->get();

        $eligibilityService = app(FestRegistrationEligibilityService::class);
        $annotated = $eligibilityService->annotateStudents($students, $event, $registration->school_id)->values();
        $eligibleIds = $eligibilityService->filterEligibleForItem($annotated, $event, $item)->pluck('id')->all();

        $rows = $annotated->map(function (array $row) use ($eligibleIds) {
            $row['eligible'] = in_array($row['id'], $eligibleIds, true);

            return $row;
        })->values();

        return response()->json(['students' => $rows]);
    }

    public function addParticipant(Request $request, string $tenantId, FestEvent $event, FestRegistration $registration)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_unless(in_array($registration->event_id, $event->reportableEventIds(), true), 403);

        $data = $request->validate([
            'student_id' => 'required|integer|exists:students,id',
            'role'       => 'required|in:performer,standby',
        ]);

        $student = Student::where('id', $data['student_id'])->where('tenant_id', $registration->school_id)->firstOrFail();

        app(FestRegistrationService::class)->addParticipant($registration, $event, $student, $data['role']);

        return back()->with('success', "Added {$student->name} to the registration.");
    }

    public function removeParticipant(string $tenantId, FestEvent $event, FestRegistration $registration, FestParticipant $participant)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_unless(in_array($registration->event_id, $event->reportableEventIds(), true), 403);
        abort_if($participant->registration_id !== $registration->id, 403);

        app(FestRegistrationService::class)->removeParticipant($participant, $event);

        return back()->with('success', 'Participant removed.');
    }

    public function bulkApprove(Request $request, string $tenantId, FestEvent $event, FestRegistrationBulkService $bulk, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'registration_ids'   => 'nullable|array',
            'registration_ids.*' => 'integer|exists:fest_registrations,id',
            'school_id'          => 'nullable|exists:tenants,id',
            'item_id'            => 'nullable|integer|exists:fest_event_items,id',
            'override_lifecycle' => 'nullable|boolean',
        ]);

        $result = $bulk->approveMany(
            $event,
            $data['registration_ids'] ?? [],
            $data['school_id'] ?? null,
            (bool) ($data['override_lifecycle'] ?? false),
            $data['item_id'] ?? null,
        );

        $audit->festEvent($event, FestPageActivity::REGISTRATIONS, 'fest.registrations.bulk_approved', "Approved {$result['approved']} registration(s)", [
            'approved' => $result['approved'],
            'skipped'  => $result['skipped'],
        ]);

        $message = "Approved {$result['approved']} registration(s).";
        if ($result['skipped'] > 0) {
            $message .= " {$result['skipped']} skipped.";
        }

        return back()
            ->with($result['approved'] > 0 ? 'success' : 'error', $message)
            ->with('importErrors', array_slice($result['errors'], 0, 20));
    }

    public function bulkReject(Request $request, string $tenantId, FestEvent $event, FestRegistrationBulkService $bulk, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'registration_ids'   => 'nullable|array',
            'registration_ids.*' => 'integer|exists:fest_registrations,id',
            'school_id'          => 'nullable|exists:tenants,id',
            'item_id'            => 'nullable|integer|exists:fest_event_items,id',
            'override_lifecycle' => 'nullable|boolean',
            'rejection_reason'   => 'nullable|string|max:500',
        ]);

        $result = $bulk->rejectMany(
            $event,
            $data['registration_ids'] ?? [],
            $data['school_id'] ?? null,
            (bool) ($data['override_lifecycle'] ?? false),
            $data['item_id'] ?? null,
            $data['rejection_reason'] ?? '',
        );

        $audit->festEvent($event, FestPageActivity::REGISTRATIONS, 'fest.registrations.bulk_rejected', "Rejected {$result['rejected']} registration(s)", [
            'rejected' => $result['rejected'],
            'reason'   => $data['rejection_reason'] ?? null,
        ]);

        return back()->with('success', "Rejected {$result['rejected']} registration(s).");
    }

    public function importTemplate(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        return ExcelExport::download("fest-cluster-registration-{$event->id}-template", [
            'school_id', 'school_prefix', 'item_id', 'item_title', 'reg_no', 'team_name', 'role',
        ], [
            ['', 'SCH001', '123', 'Mono Act', 'S2024001', '', 'performer'],
        ]);
    }

    public function importStore(Request $request, string $tenantId, FestEvent $event, FestRegistrationImportService $importService, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $request->validate(['file' => 'required|file|mimes:csv,txt,xls,xlsx|max:5120']);

        $result = $importService->importClusterFromSpreadsheet(
            $event,
            $this->sahodaya->id,
            $request->file('file')->getRealPath(),
        );

        $audit->festEvent($event, FestPageActivity::REGISTRATIONS_IMPORT, 'fest.registrations.imported', "Imported {$result['imported']} registration(s)", [
            'imported' => $result['imported'],
            'skipped'  => $result['skipped'],
        ]);

        $message = "Imported {$result['imported']} registration(s).";
        if ($result['skipped'] > 0) {
            $message .= " {$result['skipped']} skipped.";
        }

        return redirect("/sahodaya-admin/{$tenantId}/events/{$event->id}/registrations/import")
            ->with($result['imported'] > 0 ? 'success' : 'error', $message)
            ->with('importErrors', array_slice($result['errors'], 0, 20));
    }

    public function printApproved(Request $request, string $tenantId, string $event)
    {
        // See BoardResultVerificationController::downloadPdf() — implicit route-model
        // binding was found to unreliably deliver the resolved model to PDF/file-download
        // controller methods in production. Resolving manually avoids that failure.
        $event = FestEvent::findOrFail($event);
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $schoolId = $request->input('school_id') ?: null;
        $itemId = $request->integer('item_id') ?: null;
        $search = $request->input('search');
        $filterRegionId = $request->input('region_id') ?: null;

        $regionSchoolIds = null;
        if ($filterRegionId) {
            $year = AcademicYear::forSahodaya($this->sahodaya->id);
            $regionSchoolIds = SchoolRegionAssignment::forTenant($this->sahodaya->id)
                ->forYear($year)
                ->where('region_id', $filterRegionId)
                ->pluck('school_id')
                ->all();
        }

        $registrations = $this->scopedRegistrationsQuery($event, $itemId ? [$itemId] : null, $schoolId, $search, $regionSchoolIds)
            ->where('status', 'approved')
            ->with(['item', 'participants.student', 'participants.teacher', 'participants.group', 'school'])
            ->latest()
            ->get();
        $numbering = app(\App\Services\Events\FestNumberingService::class);
        $schools = Tenant::where('parent_id', $this->sahodaya->id)->pluck('name', 'id');

        $rows = [];
        foreach ($registrations as $reg) {
            $isGroup = $reg->item ? $numbering->isGroupItem($reg->item) : false;
            $schoolName = $schools[$reg->school_id] ?? $reg->school_id;

            foreach ($reg->participants as $p) {
                if ($p->participant_role === 'standby') {
                    continue;
                }

                $chest = ($isGroup && $p->group_id && $p->group)
                    ? $p->group->chest_no
                    : $numbering->effectiveChestNumber($p);

                $rows[] = [
                    'chest_no'         => $chest,
                    'participant_name' => $p->student?->name ?? $p->teacher?->name ?? $p->group?->team_name ?? 'Participant',
                    'school_name'      => $schoolName,
                    'item_title'       => $reg->item?->title ?? '—',
                    'fest_id'          => $p->level_registration_number ?? $p->student?->reg_no ?? '—',
                    'is_team'          => $isGroup,
                ];
            }
        }

        usort($rows, function ($a, $b) {
            return strcmp($a['item_title'], $b['item_title'])
                ?: ((int) ($a['chest_no'] ?? 999999) <=> (int) ($b['chest_no'] ?? 999999));
        });

        $logoSrc = \App\Support\TenantBranding::logoEmbedSrc($this->sahodaya);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('fest.reports.approved-registrations', [
            'event'    => $event,
            'sahodaya' => $this->sahodaya,
            'rows'     => $rows,
            'logoSrc'  => $logoSrc,
        ]);

        return $pdf->stream("approved-registrations-event-{$event->id}.pdf");
    }
}
