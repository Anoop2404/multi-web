<?php

namespace App\Mail;

use App\Models\AdmissionEnquiry;
use App\Models\Tenant;
use App\Support\Mail\EmailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdmissionEnquiryReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AdmissionEnquiry $enquiry,
        public Tenant $school,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New Admission Enquiry – {$this->enquiry->student_name}",
        );
    }

    public function content(): Content
    {
        $sahodaya = $this->school->parent_id
            ? Tenant::query()->find($this->school->parent_id)
            : null;

        return new Content(
            view: 'emails.admission-enquiry',
            with: array_merge(
                EmailBranding::forTenant($sahodaya ?? $this->school),
                [
                    'headerTitle'    => 'New Admission Enquiry',
                    'headerSubtitle' => $this->school->name,
                    'headerEyebrow'  => 'Admissions',
                    'footerNote'     => 'Submitted via '.$this->school->name.' Admission Portal',
                ],
            ),
        );
    }
}
