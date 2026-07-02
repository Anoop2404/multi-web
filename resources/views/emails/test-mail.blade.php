@extends('emails.layouts.sahodaya')

@section('content')
    <h2 style="margin:0 0 12px;font-size:18px;color:#041525;font-weight:700;">ZeptoMail API configured successfully</h2>

    <p>Hello,</p>

    <p>
        This is a test email from the <strong>{{ $sahodayaName }}</strong> membership portal.
    </p>

    @include('emails.partials.alert-box', [
        'variant' => 'success',
        'title' => 'All good!',
        'message' => 'If you received this message, your ZeptoMail API settings are correct. Portal emails are sent via the HTTP API (not SMTP), avoiding the daily SMTP send limit.',
    ])

    @if(!empty($portalUrl))
        @include('emails.partials.button', [
            'url' => $portalUrl,
            'label' => 'Open Sahodaya portal',
            'variant' => 'success',
        ])
    @endif
@endsection
