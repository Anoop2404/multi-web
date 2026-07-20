@extends('emails.layouts.sahodaya')

@section('content')
    <h2 style="margin:0 0 12px;font-size:18px;color:#041525;font-weight:700;">{{ $title ?? 'Membership approved' }}</h2>

    <p>Hello,</p>

    <p>{{ $body ?? 'Great news! Your school '.$school->name.' has been approved as a member of '.$sahodayaName.'.' }}</p>

    @include('emails.partials.alert-box', [
        'variant' => 'success',
        'title' => 'You can now use the school portal',
        'message' => 'Sign in to manage students, complete annual membership registration, and access all school admin features for your Sahodaya cluster.',
    ])

    @if(!empty($loginUrl))
        @include('emails.partials.button', [
            'url' => $loginUrl,
            'label' => 'Sign in to school portal',
            'variant' => 'success',
        ])
    @endif
@endsection
