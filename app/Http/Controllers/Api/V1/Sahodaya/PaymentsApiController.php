<?php

namespace App\Http\Controllers\Api\V1\Sahodaya;

use App\Http\Controllers\SahodayaAdmin\Concerns\BuildsMembershipExports;
use App\Http\Resources\MembershipPaymentResource;
use App\Http\Resources\PaymentDueItemResource;
use App\Models\MembershipPayment;
use App\Support\AcademicYear;
use App\Services\Audit\DataChangeLogger;
use App\Services\Membership\FeeReceiptService;
use App\Services\Membership\MembershipNotifier;
use App\Services\Membership\RegistrationStatusService;
use App\Support\TenancyDatabase;
use App\Support\TenantStorage;
use Illuminate\Http\Request;

class PaymentsApiController extends SahodayaApiController
{
    use BuildsMembershipExports;

    public function index(Request $request)
    {
        $filters = $this->paymentListFilters($request);
        $schoolIds = TenancyDatabase::schoolIdsFor($this->sahodaya->id);
        $year = AcademicYear::forSahodaya($this->sahodaya->id);

        $base = MembershipPayment::whereIn('school_id', $schoolIds);
        $statusCounts = [
            'payment-due' => $this->unpaidRegistrationsCount($this->sahodaya->id, $schoolIds, $year),
            'submitted'   => (clone $base)->where('status', 'submitted')->count(),
            'verified'    => (clone $base)->where('status', 'verified')->count(),
            'rejected'    => (clone $base)->where('status', 'rejected')->count(),
            'all'         => (clone $base)->count(),
        ];
        $summary = $this->buildPaymentPageSummary($this->sahodaya->id, $schoolIds, $year);

        if ($filters['status'] === 'payment-due') {
            $paymentDue = $this->paginatedPaymentDue(
                $this->sahodaya->id,
                $schoolIds,
                $year,
                $filters,
                $request->integer('per_page', 15),
            );

            return PaymentDueItemResource::collection($paymentDue)->additional([
                'meta' => [
                    'active_status' => $filters['status'],
                    'status_counts' => $statusCounts,
                    'summary'       => $summary,
                ],
            ]);
        }

        $payments = $this->paymentsQuery($schoolIds, $filters)
            ->with('registration')
            ->paginate($request->integer('per_page', 15));

        return MembershipPaymentResource::collection($payments)->additional([
            'meta' => [
                'active_status' => $filters['status'],
                'status_counts' => $statusCounts,
                'summary'       => $summary,
            ],
        ]);
    }

    public function verify(Request $request, string $tenantId, string $paymentId, MembershipNotifier $notifier)
    {
        $schoolIds = TenancyDatabase::schoolIdsFor($this->sahodaya->id);
        $payment = MembershipPayment::whereIn('school_id', $schoolIds)->findOrFail($paymentId);
        abort_unless($payment->status === 'submitted', 403);

        $data = $request->validate([
            'action' => 'required|in:verify,reject',
            'reason' => 'required_if:action,reject|nullable|string|max:1000',
        ]);

        if ($data['action'] === 'verify') {
            $payment->update([
                'status'              => 'verified',
                'verified_by_user_id' => $request->user()->id,
                'verified_at'         => now(),
            ]);

            app(FeeReceiptService::class)->syncFromMembershipPayment($payment->fresh());

            $school = $payment->school;
            if ($school && $school->membership_status === 'pending') {
                $school->update(['membership_status' => 'approved', 'is_active' => true]);
                $notifier->schoolApproved($school);
            }

            $registration = $payment->registration;
            if ($registration) {
                $registration = app(RegistrationStatusService::class)
                    ->ensureMembershipNumber($registration->load('school'));
                $registration->update(['registration_status' => 'completed']);
                $registration->refresh();
                $notifier->paymentVerified($payment->school, $payment->academic_year, $registration->reg_no);
                $notifier->registrationCompleted($payment->school, $payment->academic_year, $registration->reg_no);
            }
        } else {
            $payment->update([
                'status'              => 'rejected',
                'rejection_reason'    => $data['reason'],
                'verified_by_user_id' => $request->user()->id,
                'verified_at'         => now(),
            ]);

            app(FeeReceiptService::class)->syncFromMembershipPayment($payment->fresh());

            $registration = $payment->registration;
            if ($registration) {
                $registration->update(['registration_status' => 'payment_rejected']);
            }

            $notifier->paymentRejected($payment->school, $payment->academic_year, $data['reason']);
        }

        app(DataChangeLogger::class)->updated(
            $payment,
            "Payment {$data['action']} via mobile API",
            ['status' => ['new' => $payment->fresh()->status]],
            $payment->school_id,
            'membership',
        );

        return $this->ok(MembershipPaymentResource::make($payment->fresh()->load('school')));
    }

    public function proof(string $tenantId, string $paymentId)
    {
        $schoolIds = TenancyDatabase::schoolIdsFor($this->sahodaya->id);
        $payment = MembershipPayment::whereIn('school_id', $schoolIds)
            ->with('school')
            ->findOrFail($paymentId);

        abort_unless($payment->payment_proof_path, 404);

        return TenantStorage::downloadResponse($payment->school, $payment->payment_proof_path);
    }
}
