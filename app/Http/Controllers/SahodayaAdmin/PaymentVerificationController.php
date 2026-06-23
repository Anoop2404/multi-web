<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\SahodayaAdmin\Concerns\BuildsMembershipExports;
use App\Models\MembershipPayment;
use App\Services\Audit\DataChangeLogger;
use App\Services\Membership\FeeReceiptService;
use App\Services\Membership\MembershipNotifier;
use App\Services\Membership\RegistrationStatusService;
use App\Support\AcademicYear;
use App\Support\ExcelExport;
use App\Support\TenantStorage;
use App\Support\TenancyDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentVerificationController extends SahodayaAdminController
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
            $paymentDue = $this->paginatedPaymentDue($this->sahodaya->id, $schoolIds, $year, $filters)
                ->withQueryString();

            return $this->inertia('Sahodaya/Membership/Payments', [
                'payments'     => ['data' => []],
                'paymentDue'   => $paymentDue,
                'activeStatus' => $filters['status'],
                'filters'      => [
                    'search'    => $filters['search'],
                    'date_from' => $filters['date_from'],
                    'date_to'   => $filters['date_to'],
                ],
                'statusCounts' => $statusCounts,
                'summary'      => $summary,
            ]);
        }

        $payments = $this->paymentsQuery($schoolIds, $filters)
            ->with('registration')
            ->paginate(15)
            ->withQueryString();

        return $this->inertia('Sahodaya/Membership/Payments', [
            'payments'     => $payments,
            'paymentDue'   => null,
            'activeStatus' => $filters['status'],
            'filters'      => [
                'search'    => $filters['search'],
                'date_from' => $filters['date_from'],
                'date_to'   => $filters['date_to'],
            ],
            'statusCounts' => $statusCounts,
            'summary'      => $summary,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->paymentListFilters($request);
        $schoolIds = TenancyDatabase::schoolIdsFor($this->sahodaya->id);

        if ($filters['status'] === 'payment-due') {
            $year = AcademicYear::forSahodaya($this->sahodaya->id);
            $items = $this->paymentDueResolver()->items($this->sahodaya->id, $schoolIds, $year, $filters);

            $rows = $items->map(fn (array $item) => [
                $item['school']['name'] ?? '',
                $item['school']['school_prefix'] ?? '',
                $item['academic_year'],
                $item['reg_no'] ?? '',
                $item['registration_status'],
                $item['membership_fee_amount'],
                isset($item['updated_at']) ? date('Y-m-d H:i', strtotime($item['updated_at'])) : '',
            ]);

            return ExcelExport::download('payment-due-'.$year, [
                'School', 'Code', 'Year', 'Reg No', 'Status', 'Fee Due', 'Updated',
            ], $rows);
        }

        $payments = $this->paymentsQuery($schoolIds, $filters)->get();

        $rows = $payments->map(fn (MembershipPayment $p) => [
            $p->school?->name ?? '',
            $p->school?->school_prefix ?? '',
            $p->academic_year,
            $p->amount,
            $p->status,
            $p->payment_method ?? '',
            $p->transaction_ref ?? '',
            $p->created_at?->format('Y-m-d H:i') ?? '',
            $p->verified_at?->format('Y-m-d H:i') ?? '',
            $p->payment_proof_path ?? '',
        ]);

        return ExcelExport::download('payments-'.$filters['status'], [
            'School', 'Code', 'Year', 'Amount', 'Status', 'Method', 'Reference', 'Submitted', 'Verified', 'Proof Path',
        ], $rows);
    }

    public function verify(Request $request, string $tenantId, MembershipPayment $payment, MembershipNotifier $notifier)
    {
        abort_if($payment->school->parent_id !== $this->sahodaya->id, 403);
        abort_unless($payment->status === 'submitted', 403);

        $data = $request->validate([
            'action' => 'required|in:verify,reject',
            'reason' => 'required_if:action,reject|nullable|string|max:1000',
        ]);

        if ($data['action'] === 'verify') {
            $beforeStatus = $payment->status;
            $payment->update([
                'status'              => 'verified',
                'verified_by_user_id' => $request->user()->id,
                'verified_at'         => now(),
            ]);

            app(FeeReceiptService::class)->syncFromMembershipPayment($payment->fresh());

            app(DataChangeLogger::class)->updated(
                $payment,
                "Payment verified for {$payment->school?->name}",
                ['status' => ['old' => $beforeStatus, 'new' => 'verified']],
                $payment->school_id,
                'membership',
            );

            $school = $payment->school;
            $firstMembershipApproval = $school && $school->membership_status === 'pending';

            if ($firstMembershipApproval) {
                $school->update([
                    'membership_status' => 'approved',
                    'is_active'         => true,
                ]);
            }

            $registration = $payment->registration;
            if ($registration) {
                $registration = app(RegistrationStatusService::class)
                    ->ensureMembershipNumber($registration->load('school'));
                $regBefore = $registration->registration_status;
                $registration->update(['registration_status' => 'completed']);
                $registration->refresh();
                app(DataChangeLogger::class)->updated(
                    $registration,
                    "Registration completed for {$payment->school?->name}",
                    ['registration_status' => ['old' => $regBefore, 'new' => 'completed']],
                    $payment->school_id,
                    'membership',
                    ['membership_no' => $registration->reg_no],
                );
                $notifier->registrationCompleted(
                    $payment->school,
                    $payment->academic_year,
                    $registration->reg_no,
                    $firstMembershipApproval,
                );
            } elseif ($firstMembershipApproval) {
                $notifier->schoolApproved($school);
            }
        } else {
            $beforeStatus = $payment->status;
            $payment->update([
                'status'              => 'rejected',
                'rejection_reason'    => $data['reason'],
                'verified_by_user_id' => $request->user()->id,
                'verified_at'         => now(),
            ]);

            app(FeeReceiptService::class)->syncFromMembershipPayment($payment->fresh());

            app(DataChangeLogger::class)->updated(
                $payment,
                "Payment rejected for {$payment->school?->name}",
                ['status' => ['old' => $beforeStatus, 'new' => 'rejected']],
                $payment->school_id,
                'membership',
                ['reason' => $data['reason']],
            );

            $registration = $payment->registration;
            if ($registration) {
                $regBefore = $registration->registration_status;
                $registration->update(['registration_status' => 'payment_rejected']);
                app(DataChangeLogger::class)->updated(
                    $registration,
                    "Registration payment rejected for {$payment->school?->name}",
                    ['registration_status' => ['old' => $regBefore, 'new' => 'payment_rejected']],
                    $payment->school_id,
                    'membership',
                );
            }

            $notifier->paymentRejected($payment->school, $payment->academic_year, $data['reason']);
        }

        return back()->with('success', 'Payment review recorded.');
    }

    public function proof(string $tenantId, string $paymentId)
    {
        $schoolIds = TenancyDatabase::schoolIdsFor($this->sahodaya->id);

        $payment = MembershipPayment::query()
            ->whereIn('school_id', $schoolIds)
            ->with('school')
            ->findOrFail($paymentId);

        abort_unless($payment->payment_proof_path, 404);

        return TenantStorage::downloadResponse($payment->school, $payment->payment_proof_path);
    }
}
