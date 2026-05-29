<?php

namespace App\Mail;

use App\Models\TcRequest;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TcRequestReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TcRequest $tcRequest,
        public Tenant $school,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "TC Request – {$this->tcRequest->student_name} (Adm: {$this->tcRequest->admission_number})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tc-request',
        );
    }
}
