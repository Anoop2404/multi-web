<?php

namespace App\Services\Fees;

use App\Models\FeeReceipt;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Models\McqRegistration;
use App\Models\McqSchoolFee;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\TrainingRegistration;
use App\Models\TrainingSchoolFee;
use App\Services\Events\FestSchoolEventFeeService;
use App\Support\IndianAmountInWords;
use App\Support\SahodayaReceiptNumberAllocator;
use App\Support\TenantStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class ProgramFeeReceiptService
{
    public function issueMcqSchoolBatch(McqSchoolFee $schoolFee, FeeReceipt $receipt): FeeReceipt
    {
        $schoolFee->loadMissing(['exam', 'school']);
        $sahodaya = Tenant::find($schoolFee->school?->parent_id);
        abort_unless($sahodaya, 422, 'School Sahodaya not found.');

        return $this->issueOnApprove($receipt, $sahodaya, 'Talent Search', function (string $receiptNo) use ($schoolFee, $receipt, $sahodaya) {
            return View::make('receipts.program-fee-official', $this->buildViewData(
                receipt: $receipt,
                sahodaya: $sahodaya,
                school: $schoolFee->school,
                receiptNo: $receiptNo,
                receiptTitle: 'Talent Search Exam Fee Receipt',
                contextLabel: $schoolFee->exam?->title ?? 'Talent Search Exam',
                detailLines: $this->filterDetailLines([
                    ['label' => 'Exam level', 'value' => $schoolFee->exam?->exam_level ? 'Level '.$schoolFee->exam->exam_level : null],
                    ['label' => 'Students covered', 'value' => (string) $schoolFee->student_count],
                    ['label' => 'Fee type', 'value' => 'Batch payment'],
                ]),
            ))->render();
        });
    }

    public function issueMcqRegistration(McqRegistration $registration, FeeReceipt $receipt): FeeReceipt
    {
        $registration->loadMissing(['exam', 'student', 'school']);
        $sahodaya = Tenant::find($registration->school?->parent_id);
        abort_unless($sahodaya, 422, 'School Sahodaya not found.');

        return $this->issueOnApprove($receipt, $sahodaya, 'Talent Search', function (string $receiptNo) use ($registration, $receipt, $sahodaya) {
            return View::make('receipts.program-fee-official', $this->buildViewData(
                receipt: $receipt,
                sahodaya: $sahodaya,
                school: $registration->school,
                receiptNo: $receiptNo,
                receiptTitle: 'Talent Search Exam Fee Receipt',
                contextLabel: $registration->exam?->title ?? 'Talent Search Exam',
                detailLines: $this->filterDetailLines([
                    ['label' => 'Student', 'value' => $registration->student?->name],
                    ['label' => 'Reg. No.', 'value' => $registration->student?->reg_no],
                ]),
            ))->render();
        });
    }

    public function issueTraining(TrainingRegistration $registration, FeeReceipt $receipt): FeeReceipt
    {
        $registration->loadMissing(['program', 'teacher', 'school']);
        $sahodaya = Tenant::find($registration->school?->parent_id);
        abort_unless($sahodaya, 422, 'School Sahodaya not found.');

        return $this->issueOnApprove($receipt, $sahodaya, 'TRN', function (string $receiptNo) use ($registration, $receipt, $sahodaya) {
            return View::make('receipts.program-fee-official', $this->buildViewData(
                receipt: $receipt,
                sahodaya: $sahodaya,
                school: $registration->school,
                receiptNo: $receiptNo,
                receiptTitle: 'Training Program Fee Receipt',
                contextLabel: $registration->program?->title ?? 'Training Program',
                detailLines: $this->filterDetailLines([
                    ['label' => 'Participant', 'value' => $registration->teacher?->name],
                ]),
            ))->render();
        });
    }

    public function issueTrainingSchoolBatch(TrainingSchoolFee $schoolFee, FeeReceipt $receipt): FeeReceipt
    {
        $schoolFee->loadMissing(['program', 'school']);
        $sahodaya = Tenant::find($schoolFee->school?->parent_id);
        abort_unless($sahodaya, 422, 'School Sahodaya not found.');

        return $this->issueOnApprove($receipt, $sahodaya, 'TRN', function (string $receiptNo) use ($schoolFee, $receipt, $sahodaya) {
            return View::make('receipts.program-fee-official', $this->buildViewData(
                receipt: $receipt,
                sahodaya: $sahodaya,
                school: $schoolFee->school,
                receiptNo: $receiptNo,
                receiptTitle: 'Training Program Batch Fee Receipt',
                contextLabel: $schoolFee->program?->title ?? 'Training Program',
                detailLines: $this->filterDetailLines([
                    ['label' => 'Teachers covered', 'value' => (string) $schoolFee->teacher_count],
                    ['label' => 'Fee type', 'value' => 'School batch payment'],
                ]),
            ))->render();
        });
    }

    /** @param callable(string): string $htmlBuilder */
    public function issueOnApprove(FeeReceipt $receipt, Tenant $sahodaya, string $prefix, callable $htmlBuilder): FeeReceipt
    {
        if ($receipt->receipt_number && $receipt->generated_receipt_path) {
            return $receipt;
        }

        return DB::transaction(function () use ($receipt, $sahodaya, $prefix, $htmlBuilder) {
            $receiptNo = $receipt->receipt_number ?: $this->formatNumber(
                $prefix,
                app(SahodayaReceiptNumberAllocator::class)->next($sahodaya->id),
            );

            $path = $receipt->generated_receipt_path ?: $this->storeHtml(
                $sahodaya,
                $prefix,
                $receiptNo,
                $htmlBuilder($receiptNo),
            );

            $receipt->update([
                'receipt_number'         => $receiptNo,
                'generated_receipt_path' => $path,
            ]);

            return $receipt->fresh();
        });
    }

    public function readOrGenerate(FeeReceipt $receipt): ?string
    {
        $stored = $this->readGeneratedReceipt($receipt);
        if ($stored) {
            return $stored;
        }

        if ($receipt->status !== 'approved') {
            return null;
        }

        $receipt->loadMissing('feeable');
        $feeable = $receipt->feeable;

        $updated = match ($receipt->feeable_type) {
            McqSchoolFee::class => $this->issueMcqSchoolBatch($feeable, $receipt),
            McqRegistration::class => $this->issueMcqRegistration($feeable, $receipt),
            TrainingRegistration::class => $this->issueTraining($feeable, $receipt),
            TrainingSchoolFee::class => $this->issueTrainingSchoolBatch($feeable, $receipt),
            default => null,
        };

        return $updated ? $this->readGeneratedReceipt($updated) : null;
    }

    public function renderFestSchoolEventFee(FestSchoolEventFee $schoolFee): ?string
    {
        $schoolFee->loadMissing(['feeReceipt', 'event', 'school']);
        $receipt = $schoolFee->feeReceipt;

        if (! $receipt || $receipt->status !== 'approved') {
            return null;
        }

        $school = $schoolFee->school;
        $sahodaya = $school?->parent_id ? Tenant::find($school->parent_id) : null;
        $event = $schoolFee->event;

        if (! $school || ! $sahodaya || ! $event) {
            return null;
        }

        $registrations = FestRegistration::query()
            ->where('event_id', $event->id)
            ->where('school_id', $school->id)
            ->whereIn('status', ['submitted', 'approved'])
            ->with('item')
            ->get();

        $feeService = app(FestSchoolEventFeeService::class);

        return View::make('receipts.fest-fee-official', [
            'receipt'       => $receipt,
            'schoolFee'     => $schoolFee,
            'breakdown'     => $feeService->breakdown($event, $schoolFee, $feeService->resolveSchedule($event)),
            'registrations' => $registrations,
            'event'         => $event,
            'school'        => $school,
            'sahodaya'      => $sahodaya,
        ])->render();
    }

    public function readGeneratedReceipt(FeeReceipt $receipt): ?string
    {
        $path = $receipt->generated_receipt_path;
        if (! $path) {
            return null;
        }

        $disk = TenantStorage::uploadDisk();

        return Storage::disk($disk)->exists($path)
            ? Storage::disk($disk)->get($path)
            : null;
    }

    public function schoolIdForReceipt(FeeReceipt $receipt): ?string
    {
        $receipt->loadMissing('feeable');

        $feeable = $receipt->feeable;
        if (! $feeable) {
            return null;
        }

        return match (true) {
            $feeable instanceof McqSchoolFee,
            $feeable instanceof McqRegistration,
            $feeable instanceof TrainingRegistration,
            $feeable instanceof TrainingSchoolFee,
            $feeable instanceof FestSchoolEventFee,
            $feeable instanceof FestRegistration => (string) $feeable->school_id,
            $feeable instanceof \App\Models\MembershipPayment => (string) $feeable->school_id,
            default => $feeable->school_id ?? null,
        };
    }

    public function formatNumber(string $prefix, int $sequence): string
    {
        return $prefix.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /** @param list<array{label: string, value: string|null}> $lines */
    /** @return list<array{label: string, value: string}> */
    private function filterDetailLines(array $lines): array
    {
        return array_values(array_filter(
            $lines,
            fn (array $line) => filled($line['value'] ?? null),
        ));
    }

    /** @param list<array{label: string, value: string}> $detailLines */
    /** @return array<string, mixed> */
    private function buildViewData(
        FeeReceipt $receipt,
        Tenant $sahodaya,
        Tenant $school,
        string $receiptNo,
        string $receiptTitle,
        string $contextLabel,
        array $detailLines = [],
    ): array {
        $amount = (float) $receipt->amount;

        return [
            'receipt'       => $receipt,
            'receiptNo'     => $receiptNo,
            'sahodaya'      => $sahodaya,
            'school'        => $school,
            'receiptTitle'  => $receiptTitle,
            'contextLabel'  => $contextLabel,
            'detailLines'   => $detailLines,
            'amountWords'   => IndianAmountInWords::rupees($amount),
        ];
    }

    private function storeHtml(Tenant $sahodaya, string $prefix, string $receiptNo, string $html): string
    {
        $relative = 'sahodaya/'.$sahodaya->id.'/fee-receipts/'.$prefix.'/receipt-'.$receiptNo.'.html';
        $disk = TenantStorage::uploadDisk();
        Storage::disk($disk)->put($relative, $html);

        return $relative;
    }
}
