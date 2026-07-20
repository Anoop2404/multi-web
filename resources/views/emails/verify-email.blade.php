@extends('emails.layouts.sahodaya')

@section('content')
    <h2 style="margin:0 0 12px;font-size:18px;color:#041525;font-weight:700;">{{ $title ?? 'Verify your Gmail address' }}</h2>

    <p>Hello {{ $userName ?? 'there' }},</p>

    <p>{{ $body ?? ((!empty($schoolName) ? 'Your school '.$schoolName.' is registered with '.$sahodayaName.'.' : 'Welcome to '.$sahodayaName.'.').' Please confirm your Gmail address to activate your school portal account.') }}</p>

    @include('emails.partials.button', [
        'url' => $verificationUrl,
        'label' => 'Verify Gmail & open portal',
        'variant' => 'primary',
    ])

    @include('emails.partials.alert-box', [
        'variant' => 'warning',
        'title' => 'Security note',
        'message' => 'This link expires in '.($verificationMins ?? 60).' minutes. If you did not register a school, you can safely ignore this email.',
    ])

    <p class="muted" style="color:#64748b;font-size:13px;">
        Button not working? Copy and paste this link into your browser:<br>
        <a href="{{ $verificationUrl }}" style="color:#0f3d7a;word-break:break-all;">{{ $verificationUrl }}</a>
    </p>
@endsection
