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

        $event->load(['items' => fn ($q) => $q->where('is_enabled', true)->orderBy('title')]);

        $headId = $this->resolveHeadQueryParam($request->query('head_id') ?? $request->query('head'));
        $itemId = $request->integer('item_id') ?: null;
        $itemIds = $this->itemIdsForHeadFilter($event, $headId, $itemId);

        $feeService = app(FestSchoolEventFeeService::class);

        $registrations = FestRegistration::where('event_id', $event->id)
            ->when($itemIds !== null, fn ($q) => $q->whereIn('item_id', $itemIds))
            ->with(['item', 'participants.student', 'participants.teacher', 'participants.group'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->input('search').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->whereHas('participants.student', fn ($s) => $s
                        ->where('name', 'like', $term)
                        ->orWhere('reg_no', 'like', $term)
                        ->orWhere('admission_number', 'like', $term))
                        ->orWhereHas('participants.teacher', fn ($t) => $t
                            ->where('name', 'like', $term)
                            ->orWhere('reg_no', 'like', $term));
                });
            })
            ->latest()
            ->get();

        $schools = Tenant::where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->orderBy('name')
            ->pluck('name', 'id');

        $registerStudents = [];
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
        }

        $selectedHeadId = match (true) {
            $headId === 0 => 'other',
            $headId !== null => $headId,
            default => null,
        };

        $schoolRegions = [];
        if ($event->event_type === 'kalolsavam') {
            $schoolRegions = SchoolRegionAssignment::query()
                ->where('school_region_assignments.tenant_id', $this->sahodaya->id)
                ->where('school_region_assignments.academic_year', AcademicYear::forSahodaya($this->sahodaya->id))
                ->join('regions', 'regions.id', '=', 'school_region_assignments.region_id')
                ->pluck('regions.name', 'school_region_assignments.school_id')
                ->all();
        }

        return $this->inertia('Sahodaya/Events/Registrations', $this->withEventActivity($event, FestPageActivity::REGISTRATIONS, [
            'event'              => $event,
            'registrations'      => $registrations,
            'schools'            => $schools,
            'schoolRegions'      => $schoolRegions,
            'feeRequired'        => $feeService->feeRequired($event),
            'registerStudents'   => $registerStudents,
            'registerSchoolId'   => $registerSchoolId,
            'eventItems'         => $event->items->values(),
            'filters'            => ['search' => $request->input('search', '')],
            'selectedHeadId'     => $selectedHeadId,
            'selectedItemId'     => $itemId,
            'competitionUrl'     => "/sahodaya-admin/{$this->sahodaya->id}/events/{$event->id}/competition",
        ]));
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
            app(FestRegistrationApprovalService::class)->approve($registration->load(['participants', 'item', 'event']));
            app(FestEventNotifier::class)->registrationApproved($registration);
            $audit->festRegistrationApproved($registration);
            $message = 'Registration created and approved for '.$school->name.'.';
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
        abort_if($registration->event_id !== $event->id, 403);

        EventLifecycleGate::allowRegistrationReview($event, $request->boolean('override_lifecycle'));

        $policy = app(FestParticipationPolicyService::class)->resolveForEvent($event);
        $feeService = app(FestSchoolEventFeeService::class);

        if (($policy['require_fee_before_approval'] ?? true) && $feeService->feeRequired($event)) {
            abort_unless(
                $feeService->isPaidForRegistration($event, $registration),
                422,
                'The Event Head fee for this registration must be approved before registration approval.'
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
        abort_if($registration->event_id !== $event->id, 403);

        EventLifecycleGate::allowRegistrationReview($event, $request->boolean('override_lifecycle'));

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

        $registration->loadMissing('item');
        $headId = $registration->item?->head_id;

        $registration->update(['status' => 'rejected']);
        app(FestSchoolEventFeeService::class)->recalculate($event, $registration->school_id);

        if ($headId) {
            app(FestRegistrationApprovalService::class)->promoteNextWaitlisted($event, (int) $headId);
        }

        app(FestEventNotifier::class)->registrationRejected($registration);
        $audit->festRegistrationRejected($registration);

        return back()->with('success', 'Registration rejected.');
    }

    public function cancel(Request $request, string $tenantId, FestEvent $event, FestRegistration $registration, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->event_id !== $event->id, 403);

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
        abort_if($registration->event_id !== $event->id, 403);

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
        abort_if($registration->event_id !== $event->id, 403);
        abort_if($performer->registration_id !== $registration->id || $standby->registration_id !== $registration->id, 403);

        app(FestRegistrationService::class)->substitutePerformer($performer, $standby);

        return back()->with('success', 'Participant substituted.');
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
        ]);

        $result = $bulk->rejectMany(
            $event,
            $data['registration_ids'] ?? [],
            $data['school_id'] ?? null,
            (bool) ($data['override_lifecycle'] ?? false),
            $data['item_id'] ?? null,
        );

        $audit->festEvent($event, FestPageActivity::REGISTRATIONS, 'fest.registrations.bulk_rejected', "Rejected {$result['rejected']} registration(s)", [
            'rejected' => $result['rejected'],
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

    public function printApproved(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $schoolId = $request->input('school_id') ?: null;
        $itemId = $request->integer('item_id') ?: null;
        $search = $request->input('search');

        $query = FestRegistration::where('event_id', $event->id)
            ->where('status', 'approved')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->when($itemId, fn ($q) => $q->where('item_id', $itemId))
            ->with(['item', 'participants.student', 'participants.teacher', 'participants.group', 'school']);

        if ($search) {
            $term = '%'.$search.'%';
            $query->where(function ($inner) use ($term) {
                $inner->whereHas('participants.student', fn ($s) => $s
                    ->where('name', 'like', $term)
                    ->orWhere('reg_no', 'like', $term))
                    ->orWhereHas('participants.teacher', fn ($t) => $t
                        ->where('name', 'like', $term));
            });
        }

        $registrations = $query->latest()->get();
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
