<?php

namespace App\Services\Training;

use App\Models\Tenant;
use App\Models\TrainingInvoice;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Models\TrainingSchoolFee;
use App\Support\TenantBranding;
use App\Support\TenantStorage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class TrainingInvoiceService
{
    /** Create or refresh a school-batch invoice after fee sync. */
    public function ensureForSchoolFee(TrainingSchoolFee $schoolFee): ?TrainingInvoice
    {
        $schoolFee->loadMissing(['program', 'school']);
        $program = $schoolFee->program;
        if (! $program || (float) $schoolFee->total_due <= 0) {
            return null;
        }

        return $this->upsertInvoice(
            program: $program,
            schoolId: $schoolFee->school_id,
            registrationId: null,
            schoolFeeId: $schoolFee->id,
            amount: (float) $schoolFee->total_due,
            markPaid: $schoolFee->isFullyPaid(),
        );
    }

    /**
     * Create or refresh a per-teacher invoice (flat fee).
     * Called when a teacher fee receipt is created, or on demand for download.
     */
    public function ensureForRegistration(TrainingRegistration $registration): ?TrainingInvoice
    {
        $registration->loadMissing(['program', 'teacher', 'school']);
        $program = $registration->program;
        if (! $program || $program->usesSchoolBatchFee()) {
            return null;
        }

        $amount = $registration->feeTotalDue();
        if ($amount <= 0) {
            return null;
        }

        return $this->upsertInvoice(
            program: $program,
            schoolId: $registration->school_id,
            registrationId: $registration->id,
            schoolFeeId: null,
            amount: $amount,
            markPaid: $registration->isFullyPaid(),
        );
    }

    public function markPaidForSchoolFee(TrainingSchoolFee $schoolFee): void
    {
        TrainingInvoice::where('school_fee_id', $schoolFee->id)
            ->where('status', '!=', TrainingInvoice::STATUS_PAID)
            ->update(['status' => TrainingInvoice::STATUS_PAID]);
    }

    public function markPaidForRegistration(TrainingRegistration $registration): void
    {
        TrainingInvoice::where('registration_id', $registration->id)
            ->where('status', '!=', TrainingInvoice::STATUS_PAID)
            ->update(['status' => TrainingInvoice::STATUS_PAID]);
    }

    public function download(TrainingInvoice $invoice, ?Tenant $sahodaya = null): Response
    {
        $invoice = $this->refreshPdf($invoice, $sahodaya);
        $slug = str($invoice->invoice_number)->slug()->toString();

        return Pdf::loadView('training.invoice', $this->viewData($invoice, $sahodaya))
            ->setPaper('a4', 'portrait')
            ->download("training-invoice-{$slug}.pdf");
    }

    public function refreshPdf(TrainingInvoice $invoice, ?Tenant $sahodaya = null): TrainingInvoice
    {
        $invoice->loadMissing(['program', 'school', 'registration.teacher', 'schoolFee']);
        $pdf = Pdf::loadView('training.invoice', $this->viewData($invoice, $sahodaya))
            ->setPaper('a4', 'portrait');

        $relative = sprintf(
            'training-invoices/%s/%s.pdf',
            $invoice->program_id,
            $invoice->invoice_number,
        );

        TenantStorage::put($relative, $pdf->output());

        $invoice->update(['pdf_path' => $relative]);

        return $invoice->fresh();
    }

    private function upsertInvoice(
        TrainingProgram $program,
        ?string $schoolId,
        ?int $registrationId,
        ?int $schoolFeeId,
        float $amount,
        bool $markPaid,
    ): TrainingInvoice {
        return DB::transaction(function () use ($program, $schoolId, $registrationId, $schoolFeeId, $amount, $markPaid) {
            $existing = null;
            if ($schoolFeeId) {
                $existing = TrainingInvoice::where('school_fee_id', $schoolFeeId)->lockForUpdate()->first();
            } elseif ($registrationId) {
                $existing = TrainingInvoice::where('registration_id', $registrationId)->lockForUpdate()->first();
            }

            $status = $markPaid
                ? TrainingInvoice::STATUS_PAID
                : TrainingInvoice::STATUS_ISSUED;

            if ($existing) {
                $existing->update([
                    'program_id' => $program->id,
                    'school_id'  => $schoolId,
                    'amount'     => round($amount, 2),
                    'status'     => $existing->isPaid() ? TrainingInvoice::STATUS_PAID : $status,
                    'issued_at'  => $existing->issued_at ?? now(),
                ]);

                return $existing->fresh();
            }

            return TrainingInvoice::create([
                'program_id'       => $program->id,
                'school_id'        => $schoolId,
                'registration_id'  => $registrationId,
                'school_fee_id'    => $schoolFeeId,
                'invoice_number'   => $this->nextInvoiceNumber($program, $registrationId, $schoolFeeId),
                'amount'           => round($amount, 2),
                'status'           => $status,
                'issued_at'        => now(),
            ]);
        });
    }

    private function nextInvoiceNumber(TrainingProgram $program, ?int $registrationId, ?int $schoolFeeId): string
    {
        if ($schoolFeeId) {
            return sprintf('TRN-INV-%d-SF%d', $program->id, $schoolFeeId);
        }

        if ($registrationId) {
            return sprintf('TRN-INV-%d-R%d', $program->id, $registrationId);
        }

        $seq = TrainingInvoice::where('program_id', $program->id)->count() + 1;

        return sprintf('TRN-INV-%d-%04d', $program->id, $seq);
    }

    /** @return list<array{description: string, amount: float}> */
    private function schoolFeeLineItems(TrainingSchoolFee $schoolFee): array
    {
        $unit = (float) ($schoolFee->program?->fee_amount ?? 0);
        $count = (int) $schoolFee->teacher_count;
        $total = (float) $schoolFee->total_due;

        $items = [[
            'description' => sprintf(
                'School batch training fee · %d teacher(s) × ₹%s',
                $count,
                number_format($unit, 2),
            ),
            'amount' => $count * $unit,
        ]];

        $base = round($count * $unit, 2);
        if ($total > $base + 0.009) {
            $items[] = [
                'description' => 'Late fee / penalty',
                'amount'      => round($total - $base, 2),
            ];
        }

        return $items;
    }

    /** @return list<array{description: string, amount: float}> */
    private function registrationLineItems(TrainingRegistration $registration, float $amount): array
    {
        $teacher = $registration->teacher?->name ?? 'Teacher';

        return [[
            'description' => sprintf('Training fee · %s', $teacher),
            'amount'      => $amount,
        ]];
    }

    /** @return array<string, mixed> */
    private function viewData(TrainingInvoice $invoice, ?Tenant $sahodaya = null): array
    {
        $invoice->loadMissing(['program', 'school', 'registration.teacher', 'schoolFee']);
        $program = $invoice->program;
        $school = $invoice->school;
        $sahodaya ??= $school?->parent_id
            ? Tenant::find($school->parent_id)
            : ($program?->tenant_id ? Tenant::find($program->tenant_id) : null);

        $lineItems = $invoice->school_fee_id && $invoice->schoolFee
            ? $this->schoolFeeLineItems($invoice->schoolFee)
            : ($invoice->registration
                ? $this->registrationLineItems($invoice->registration, (float) $invoice->amount)
                : [['description' => 'Training fee', 'amount' => (float) $invoice->amount]]);

        return [
            'invoice'     => $invoice,
            'program'     => $program,
            'school'      => $school,
            'sahodaya'    => $sahodaya,
            'orgName'     => $sahodaya?->name ?? 'Sahodaya',
            'logoSrc'     => $sahodaya ? TenantBranding::logoEmbedSrc($sahodaya) : null,
            'invoiceNo'   => $invoice->invoice_number,
            'lineItems'   => $lineItems,
            'amount'      => (float) $invoice->amount,
            'status'      => $invoice->status,
            'participant' => $invoice->registration?->teacher?->name,
            'teacherCount'=> $invoice->schoolFee?->teacher_count,
            'generatedAt' => now()->timezone(config('app.timezone'))->format('d M Y · h:i A'),
        ];
    }
}
