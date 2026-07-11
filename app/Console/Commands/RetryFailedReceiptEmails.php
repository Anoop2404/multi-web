<?php

namespace App\Console\Commands;

use App\Models\FeeReceipt;
use App\Models\FestSchoolEventFee;
use App\Models\McqSchoolFee;
use App\Models\MembershipPayment;
use App\Models\Tenant;
use App\Models\TrainingRegistration;
use App\Services\Fees\FeeReceiptEmailTracker;
use App\Services\Fees\OfflineProgramFeeOrchestrator;
use App\Services\Fees\ProgramFeeReceiptService;
use App\Services\Membership\MembershipNotifier;
use App\Services\Membership\MembershipReceiptService;
use App\Support\ProgramRouteMap;
use Illuminate\Console\Command;

class RetryFailedReceiptEmails extends Command
{
    protected $signature = 'erp:retry-failed-receipt-emails {--tenant= : Sahodaya tenant id} {--limit=50}';

    protected $description = 'Retry sending receipt emails that previously failed';

    public function handle(
        FeeReceiptEmailTracker $tracker,
        OfflineProgramFeeOrchestrator $orchestrator,
        ProgramFeeReceiptService $receiptService,
        MembershipReceiptService $membershipReceipts,
        MembershipNotifier $notifier,
    ): int {
        $limit = (int) $this->option('limit');
        $tenantId = $this->option('tenant');

        $query = FeeReceipt::query()
            ->where('status', 'approved')
            ->where('receipt_email_status', 'failed')
            ->orderBy('reviewed_at');

        $retried = 0;

        foreach ($query->limit($limit)->get() as $receipt) {
            try {
                if ($this->retryReceipt($receipt, $tracker, $orchestrator, $receiptService, $membershipReceipts, $notifier)) {
                    $retried++;
                    $this->line("Retried receipt #{$receipt->receipt_number}");
                }
            } catch (\Throwable $e) {
                $tracker->markFailed($receipt, $e->getMessage());
                $this->warn("Failed receipt {$receipt->id}: {$e->getMessage()}");
            }
        }

        $this->info("Retried {$retried} receipt email(s).");

        return self::SUCCESS;
    }

    private function retryReceipt(
        FeeReceipt $receipt,
        FeeReceiptEmailTracker $tracker,
        OfflineProgramFeeOrchestrator $orchestrator,
        ProgramFeeReceiptService $receiptService,
        MembershipReceiptService $membershipReceipts,
        MembershipNotifier $notifier,
    ): bool {
        $tracker->incrementResend($receipt);
        $tracker->markQueued($receipt);

        if ($receipt->feeable_type === (new MembershipPayment)->getMorphClass()) {
            $payment = MembershipPayment::with(['school', 'registration'])->find($receipt->feeable_id);
            if (! $payment?->school) {
                return false;
            }
            $html = $membershipReceipts->readGeneratedReceipt($receipt);
            $notifier->registrationCompleted(
                $payment->school,
                $payment->academic_year,
                $payment->registration?->reg_no ?? '—',
                false,
                $html,
                $receipt->receipt_number,
            );
            $tracker->markSent($receipt->fresh());

            return true;
        }

        $schoolId = $receiptService->schoolIdForReceipt($receipt);
        $school = $schoolId ? Tenant::find($schoolId) : null;
        if (! $school) {
            return false;
        }

        if ($receipt->feeable_type === (new FestSchoolEventFee)->getMorphClass()) {
            $fee = FestSchoolEventFee::with('event')->find($receipt->feeable_id);
            $slug = ProgramRouteMap::slugFromEventType($fee?->event?->event_type) ?? 'kalotsav';
            $html = $receiptService->renderFestSchoolEventFee($fee);

            return $orchestrator->notifyApproved(
                $school,
                $receipt,
                ProgramRouteMap::labelForSlug($slug).' fee',
                $fee?->event?->title ?? 'Fest',
                $html,
                "programs/{$slug}/registration",
            );
        }

        if ($receipt->feeable_type === (new McqSchoolFee)->getMorphClass()) {
            $fee = McqSchoolFee::with('exam')->find($receipt->feeable_id);

            return $orchestrator->notifyApproved(
                $school,
                $receipt,
                'Talent Search exam fee',
                $fee?->exam?->title ?? 'Talent Search Exam',
            );
        }

        if ($receipt->feeable_type === (new TrainingRegistration)->getMorphClass()) {
            $reg = TrainingRegistration::with('program')->find($receipt->feeable_id);

            return $orchestrator->notifyApproved(
                $school,
                $receipt,
                'Training fee',
                $reg?->program?->title ?? 'Training Program',
            );
        }

        if ($receipt->feeable_type === (new \App\Models\TrainingSchoolFee)->getMorphClass()) {
            $fee = \App\Models\TrainingSchoolFee::with('program')->find($receipt->feeable_id);

            return $orchestrator->notifyApproved(
                $school,
                $receipt,
                'Training batch fee',
                $fee?->program?->title ?? 'Training Program',
            );
        }

        return false;
    }
}
