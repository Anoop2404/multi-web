@extends('emails.layouts.sahodaya')

@section('content')
    <h2 style="margin:0 0 12px;font-size:18px;color:#041525;font-weight:700;">{{ $title ?? 'Reset your password' }}</h2>

    <p>Hello {{ $userName ?? 'there' }},</p>

    <p>{{ $body ?? 'We received a request to reset the password for your '.(!empty($schoolName) ? $schoolName.' portal account with '.$sahodayaName.'.' : $sahodayaName.' portal account.') }}</p>

    @include('emails.partials.button', [
        'url' => $resetUrl,
        'label' => 'Reset password',
        'variant' => 'primary',
    ])

    @include('emails.partials.alert-box', [
        'variant' => 'warning',
        'title' => 'Security note',
        'message' => 'This link expires in '.($expireMinutes ?? 60).' minutes. If you did not request a password reset, no further action is required.',
    ])

    <p class="muted" style="color:#64748b;font-size:13px;">
        Button not working? Copy and paste this link into your browser:<br>
        <a href="{{ $resetUrl }}" style="color:#0f3d7a;word-break:break-all;">{{ $resetUrl }}</a>
    </p>
@endsection
