@extends('emails.layouts.sahodaya')

@section('content')
    <h2 style="margin:0 0 14px;font-size:18px;color:#041525;font-weight:700;">
        Official Training Certificate
    </h2>

    <p style="margin:0 0 14px;font-size:15px;color:#334155;line-height:1.6;">
        Dear <strong>{{ $recipientName }}</strong>,
    </p>

    <p style="margin:0 0 18px;font-size:14px;color:#334155;line-height:1.6;">
        Congratulations! Your official training certificate for <strong>"{{ $programTitle }}"</strong> has been generated and is attached to this email as a PDF document.
    </p>

    @include('emails.partials.detail-table', ['rows' => array_filter([
        'Participant'       => $recipientName,
        'Training Program'  => $programTitle,
        'Verification Code' => $certificate->verification_uuid ?? null,
        'Issued Date'       => now()->format('j F Y'),
    ])])

    @include('emails.partials.alert-box', [
        'variant' => 'info',
        'title'   => 'PDF Certificate Attached',
        'message' => 'Your official certificate is attached to this email as a PDF document. You can also view or verify it online anytime.',
    ])

    @if(!empty($printUrl))
        @include('emails.partials.button', [
            'url'     => $printUrl,
            'label'   => 'Verify & View Certificate Online',
            'variant' => 'primary',
        ])
    @endif

    <p style="margin:16px 0 0;font-size:12px;color:#64748b;line-height:1.5;">
        If you have trouble clicking the button above, copy and paste this link into your browser:<br>
        <a href="{{ $printUrl }}" style="color:#0f3d7a;word-break:break-all;">{{ $printUrl }}</a>
    </p>
@endsection
