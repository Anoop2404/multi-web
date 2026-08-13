@extends('emails.layouts.sahodaya')

@section('content')
    <h2 style="margin:0 0 12px;font-size:18px;color:#041525;font-weight:700;">{{ $title ?? 'Your Teacher Portal Credentials' }}</h2>

    <p>Hello {{ $teacher->name }},</p>

    <p>Your Teacher Portal login account for <strong>{{ $school->name }}</strong> has been set up. You can log in using the credentials below:</p>

    @include('emails.partials.detail-table', ['rows' => [
        'Teacher Name' => $teacher->name,
        'School' => $school->name,
        'Username / Login Code' => $username,
        'Email' => $teacher->email,
        'Temporary Password' => $plainPassword,
    ]])

    @include('emails.partials.button', [
        'url' => $loginUrl,
        'label' => 'Open Teacher Portal',
    ])

    <p style="margin:0 0 10px;font-size:14px;font-weight:700;color:#041525;">Next steps</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 18px;">
        @foreach([
            'Click the button above or visit the Teacher Portal login page',
            'Sign in using your Username / Login Code or Email and Temporary Password',
            'Change your password after your first login',
        ] as $i => $step)
        <tr>
            <td width="28" valign="top" style="padding:0 0 10px;font-size:13px;font-weight:700;color:#0f3d7a;">{{ $i + 1 }}.</td>
            <td valign="top" style="padding:0 0 10px;font-size:14px;color:#334155;">{{ $step }}</td>
        </tr>
        @endforeach
    </table>

    @include('emails.partials.alert-box', [
        'variant' => 'warning',
        'title' => 'Security Notice',
        'message' => 'Keep your login credentials secure. Do not share your password with anyone.',
    ])
@endsection
