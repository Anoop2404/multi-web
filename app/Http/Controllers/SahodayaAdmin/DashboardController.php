<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\SahodayaAdmin\Concerns\BuildsMembershipExports;
use App\Models\AuditLog;
use App\Models\Circular;
use App\Models\FestAppeal;
use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Models\McqExam;
use App\Models\McqSchoolFee;
use App\Models\MembershipPayment;
use App\Models\OfficeBearers;
use App\Models\Registration;
use App\Models\SchoolYearSubmission;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\TrainingProgram;
use App\Services\Membership\SahodayaSetupService;
use App\Support\AcademicYear;
use App\Support\TenancyDatabase;

class DashboardController extends SahodayaAdminController
{
    use BuildsMembershipExports;

    public function index(SahodayaSetupService $setup)
    {
        if (! $this->isStaff && $setup->shouldPromptWizard($this->sahodaya)) {
            return redirect("/sahodaya-admin/{$this->sahodaya->id}/setup");
        }

        $schoolIds = TenancyDatabase::schoolIdsFor($this->sahodaya->id);
        $year = AcademicYear::forSahodaya($this->sahodaya->id);
        $fees = $this->paymentFeeSummary($this->sahodaya->id, $schoolIds, $year);
        $paymentSummary = $this->paymentStatusSummary($this->sahodaya->id, $schoolIds, $year);

        $approvedSchoolIds = Tenant::query()
            ->where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->pluck('id')
            ->all();

        $activeStatuses = ['published', 'registration_open', 'ongoing'];
        $festEventIds = FestEvent::where('tenant_id', $this->sahodaya->id)->pluck('id');

        $actionQueue = array_filter([
            'pending_school_applications' => Tenant::query()
                ->where('parent_id', $this->sahodaya->id)
                ->where('type', 'school')
                ->where('membership_status', 'pending')
                ->count(),
            'membership_data_pending' => Registration::query()
                ->whereIn('school_id', $schoolIds)
                ->where('academic_year', $year)
                ->whereIn('registration_status', ['data_pending', 'data_rejected'])
                ->count(),
            'membership_payments' => MembershipPayment::whereIn('school_id', $schoolIds)
                ->where('status', 'submitted')
                ->count(),
            'fest_fee_proofs' => FestSchoolEventFee::whereIn('event_id', $festEventIds)
                ->where('status', 'proof_uploaded')
                ->count(),
            'mcq_fee_proofs' => McqSchoolFee::query()
                ->whereHas('exam', fn ($q) => $q->where('tenant_id', $this->sahodaya->id))
                ->where('status', 'proof_uploaded')
                ->count(),
            'fest_appeals' => FestAppeal::whereIn('event_id', $festEventIds)
                ->where('status', 'pending')
                ->count(),
            'fest_registrations_review' => FestRegistration::whereIn('event_id', $festEventIds)
                ->where('status', 'submitted')
                ->count(),
        ], fn (int $count) => $count > 0);

        $base = "/sahodaya-admin/{$this->sahodaya->id}";
        $appealsEventId = FestAppeal::whereIn('event_id', $festEventIds)
            ->where('status', 'pending')
            ->value('event_id');
        $reviewEventId = FestRegistration::whereIn('event_id', $festEventIds)
            ->where('status', 'submitted')
            ->value('event_id');

        $actionQueueLinks = array_filter([
            'fest_appeals' => $appealsEventId ? "{$base}/events/{$appealsEventId}/appeals" : null,
            'fest_registrations_review' => $reviewEventId ? "{$base}/events/{$reviewEventId}/registrations" : null,
        ]);

        $stats = [
            'approved_schools'   => count($approvedSchoolIds),
            'pending_schools'    => Tenant::query()
                ->where('parent_id', $this->sahodaya->id)
                ->where('type', 'school')
                ->where('membership_status', 'pending')
                ->count(),
            'registered_schools' => count($schoolIds),
            'total_students'     => $approvedSchoolIds === []
                ? 0
                : Student::query()
                    ->whereIn('tenant_id', $approvedSchoolIds)
                    ->where('status', 'active')
                    ->count(),
            'pending_payments'   => $paymentSummary['payments_pending_verification'],
            'payment_due'        => $paymentSummary['payment_not_done'],
            'pending_amount'   => $fees['pending_amount'],
            'approved_amount'  => $fees['approved_amount'],
            'payment_due_amount' => $fees['payment_due_amount'],
            'payment_not_done' => $paymentSummary['payment_not_done'],
            'payment_not_done_amount' => $paymentSummary['payment_not_done_amount'],
            'payments_pending_verification' => $paymentSummary['payments_pending_verification'],
            'payments_pending_verification_amount' => $paymentSummary['payments_pending_verification_amount'],
            'office_bearers'     => OfficeBearers::where('tenant_id', $this->sahodaya->id)->count(),
            'circulars'          => Circular::where('tenant_id', $this->sahodaya->id)->count(),
            'fest_events'        => $festEventIds->count(),
            'active_fest_events' => FestEvent::where('tenant_id', $this->sahodaya->id)
                ->whereIn('status', $activeStatuses)
                ->count(),
            'fest_registrations' => $festEventIds->isEmpty()
                ? 0
                : FestRegistration::whereIn('event_id', $festEventIds)->count(),
            'mcq_exams'          => McqExam::where('tenant_id', $this->sahodaya->id)->count(),
            'training_programs'  => TrainingProgram::where('tenant_id', $this->sahodaya->id)->count(),
        ];

        $recentCirculars = Circular::where('tenant_id', $this->sahodaya->id)
            ->orderByDesc('issued_date')
            ->limit(5)
            ->get(['id', 'title', 'category', 'issued_date']);

        $activeEvents = FestEvent::where('tenant_id', $this->sahodaya->id)
            ->whereIn('status', $activeStatuses)
            ->withCount('registrations')
            ->orderByDesc('event_start')
            ->limit(6)
            ->get(['id', 'title', 'event_type', 'status', 'event_start']);

        $festOps = [
            'programs' => [
                ['slug' => 'kalotsav', 'prefix' => 'kalotsav', 'label' => 'Kalotsav', 'icon' => '🏆'],
                ['slug' => 'sports-meet', 'prefix' => 'sports', 'label' => 'Sports Meet', 'icon' => '🏅'],
                ['slug' => 'kids-fest', 'prefix' => 'kids-fest', 'label' => 'Kids Fest', 'icon' => '🎈'],
                ['slug' => 'teacher-fest', 'prefix' => 'teacher-fest', 'label' => 'Teacher Fest', 'icon' => '👩‍🏫'],
                ['slug' => 'custom', 'prefix' => 'programs/custom', 'label' => 'Custom Events', 'icon' => '📅'],
            ],
        ];

        $dashboardExtras = app(\App\Services\Events\ProgramHubDataService::class)
            ->sahodayaDashboardExtras($this->sahodaya);

        $recentActivity = AuditLog::query()
            ->where('properties->tenant_id', $this->sahodaya->id)
            ->latest()
            ->limit(5)
            ->get(['id', 'action', 'description', 'category', 'created_at'])
            ->map(fn (AuditLog $log) => [
                'id'          => $log->id,
                'action'      => $log->action,
                'description' => $log->description,
                'category'    => $log->category,
                'created_at'  => $log->created_at?->toIso8601String(),
            ])
            ->all();

        return $this->inertia('Sahodaya/Dashboard', compact(
            'stats',
            'actionQueue',
            'actionQueueLinks',
            'recentCirculars',
            'activeEvents',
            'festOps',
            'dashboardExtras',
            'recentActivity',
        ));
    }
}
