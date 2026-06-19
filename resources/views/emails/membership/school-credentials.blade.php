@extends('emails.layouts.sahodaya')

@section('content')
    <h2 style="margin:0 0 12px;font-size:18px;color:#041525;font-weight:700;">Your school portal is ready</h2>

    <p>Hello,</p>

    <p>
        <strong>{{ $school->name }}</strong> has been registered with
        <strong>{{ $sahodayaName }}</strong>. Use the credentials below to sign in and complete Gmail verification.
    </p>

    @include('emails.partials.detail-table', ['rows' => [
        'School' => $school->name,
        'Login email (Gmail)' => $user->email,
        'Temporary password' => $plainPassword,
    ]])

    @include('emails.partials.button', [
        'url' => $loginUrl,
        'label' => 'Open school portal',
    ])

    <p style="margin:0 0 10px;font-size:14px;font-weight:700;color:#041525;">Next steps</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 18px;">
        @foreach([
            'Click the Gmail verification link in the separate email we sent you',
            'Open the school portal and sign in with the credentials below',
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
        'title' => 'Keep this email safe',
        'message' => 'Your temporary password is shown above. Do not share it with anyone outside your school administration.',
    ])
@endsection
