<?php

namespace App\Services\Mcq;

use App\Models\McqRegistration;
use App\Models\Tenant;
use App\Support\TenantBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/** Per-registration Talent Search fee invoice (DomPDF). */
class McqRegistrationInvoiceService
{
    public function download(McqRegistration $registration, ?Tenant $sahodaya = null): Response
    {
        $registration->loadMissing(['exam', 'student', 'teacher', 'school', 'feeReceipt']);
        $exam = $registration->exam;
        abort_unless($exam, 404);

        $school = $registration->school;
        $sahodaya ??= $school?->parent_id ? Tenant::find($school->parent_id) : Tenant::find($exam->tenant_id);
        abort_unless($sahodaya, 422, 'Sahodaya not found.');

        $studentFee = (float) ($exam->fee_amount ?? 0);
        $discount = $exam->schoolDiscountAmount();
        $payable = $exam->schoolPayablePerStudent();

        $invoiceNo = sprintf(
            'MCQ-INV-%s-%s',
            $exam->code ?: $exam->id,
            $registration->hall_ticket_no ?: $registration->id,
        );

        $pdf = Pdf::loadView('mcq.registration-invoice', [
            'registration' => $registration,
            'exam'         => $exam,
            'school'       => $school,
            'sahodaya'     => $sahodaya,
            'orgName'      => $sahodaya->name,
            'logoSrc'      => TenantBranding::logoEmbedSrc($sahodaya),
            'invoiceNo'    => $invoiceNo,
            'studentFee'   => $studentFee,
            'discount'     => $discount,
            'payable'      => $payable,
            'participant'  => $registration->participantName(),
            'regNo'        => $registration->student?->reg_no
                ?? $registration->teacher?->employee_code
                ?? $registration->teacher?->reg_no
                ?? $registration->hall_ticket_no,
            'feeStatus'    => $registration->feeReceipt?->status ?? $registration->approval_status,
            'generatedAt'  => now()->timezone(config('app.timezone'))->format('d M Y · h:i A'),
        ])->setPaper('a4', 'portrait');

        $slug = str($exam->title)->slug()->limit(40, '')->toString();
        $who = str($registration->participantName() ?: 'participant')->slug()->limit(30, '')->toString();

        return $pdf->download("{$slug}-invoice-{$who}.pdf");
    }
}
