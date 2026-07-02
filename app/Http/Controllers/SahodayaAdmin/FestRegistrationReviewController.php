<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\EventLifecycleGate;
use App\Services\Events\FestEventNotifier;
use App\Services\Events\FestParticipationPolicyService;
use App\Services\Events\FestRegistrationApprovalService;
use App\Services\Events\FestRegistrationBulkService;
use App\Support\FestPageActivity;
use App\Services\Events\FestRegistrationCreateService;
use App\Services\Events\FestRegistrationImportService;
use App\Services\Events\FestRegistrationEligibilityService;
use App\Services\Events\FestRegistrationService;
use App\Services\Events\FestSchoolEventFeeService;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Http\Request;

class FestRegistrationReviewController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event, Request $request)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $event->load(['items' => fn ($q) => $q->where('is_enabled', true)->orderBy('title')]);

        $feeService = app(FestSchoolEventFeeService::class);

        $registrations = FestRegistration::where('event_id', $event->id)
            ->with(['item', 'participants.student', 'participants.teacher', 'participants.group'])
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

        return $this->inertia('Sahodaya/Events/Registrations', $this->withEventActivity($event, FestPageActivity::REGISTRATIONS, [
            'event'              => $event,
            'registrations'      => $registrations,
            'schools'            => $schools,
            'feeRequired'        => $feeService->feeRequired($event),
            'registerStudents'   => $registerStudents,
            'registerSchoolId'   => $registerSchoolId,
            'eventItems'         => $event->items->values(),
        ]));
    }

    public function storeOnBehalf(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'school_id'        => 'required|exists:tenants,id',
            'item_id'          => 'required|exists:fest_event_items,id',
            'team_name'        => 'nullable|string|max:255',
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

        $registration = app(FestRegistrationCreateService::class)->createForSchool(
            $event,
            $item,
            $school,
            $data['student_ids'],
            $data['standby_ids'] ?? [],
            $data['team_name'] ?? null,
            skipSchoolClosedCheck: true,
        );

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

    public function approve(Request $request, string $tenantId, FestEvent $event, FestRegistration $registration, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->event_id !== $event->id, 403);

        EventLifecycleGate::allowRegistrationReview($event, $request->boolean('override_lifecycle'));

        $policy = app(FestParticipationPolicyService::class)->resolveForEvent($event);
        $feeService = app(FestSchoolEventFeeService::class);

        if (($policy['require_fee_before_approval'] ?? true) && $feeService->feeRequired($event)) {
            abort_unless(
                $feeService->isPaid($event, $registration->school_id),
                422,
                'School event fee must be approved before registration approval.'
            );
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

        $registration->update(['status' => 'rejected']);
        app(FestSchoolEventFeeService::class)->recalculate($event, $registration->school_id);
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
            'Cannot cancel after results are published.'
        );

        app(FestRegistrationService::class)->cancel($registration, $event);
        $audit->festRegistrationCancelled($registration);

        return back()->with('success', 'Registration cancelled.');
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
            'override_lifecycle' => 'nullable|boolean',
        ]);

        $result = $bulk->approveMany(
            $event,
            $data['registration_ids'] ?? [],
            $data['school_id'] ?? null,
            (bool) ($data['override_lifecycle'] ?? false),
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
            'override_lifecycle' => 'nullable|boolean',
        ]);

        $result = $bulk->rejectMany(
            $event,
            $data['registration_ids'] ?? [],
            $data['school_id'] ?? null,
            (bool) ($data['override_lifecycle'] ?? false),
        );

        $audit->festEvent($event, FestPageActivity::REGISTRATIONS, 'fest.registrations.bulk_rejected', "Rejected {$result['rejected']} registration(s)", [
            'rejected' => $result['rejected'],
        ]);

        return back()->with('success', "Rejected {$result['rejected']} registration(s).");
    }

    public function importTemplate(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['school_id', 'school_prefix', 'item_id', 'item_title', 'reg_no', 'team_name', 'role']);
            fputcsv($out, ['', 'SCH001', '123', 'Mono Act', 'S2024001', '', 'performer']);
            fclose($out);
        }, "fest-cluster-registration-{$event->id}-template.csv", ['Content-Type' => 'text/csv']);
    }

    public function importStore(Request $request, string $tenantId, FestEvent $event, FestRegistrationImportService $importService, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $request->validate(['file' => 'required|file|mimes:csv,txt|max:5120']);

        $result = $importService->importClusterFromCsv(
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
}
