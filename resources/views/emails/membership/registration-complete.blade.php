@extends('emails.layouts.sahodaya')

@section('content')
    <h2 style="margin:0 0 12px;font-size:18px;color:#041525;font-weight:700;">{{ $title }}</h2>

    <p>Hello,</p>

    <p>{{ $body }}</p>

    @include('emails.partials.detail-table', ['rows' => array_filter([
        'Academic year'  => $academicYear,
        'Membership No.' => $membershipNo,
        'Receipt No.'    => $receiptNo ?? null,
        'Status'         => 'Complete',
    ])])

    @if(!empty($receiptNo))
        @include('emails.partials.alert-box', [
            'variant' => 'info',
            'title' => 'Official receipt attached',
            'message' => "Receipt No. {$receiptNo} is attached as an HTML file. Open it in your browser and print for your records.",
        ])
    @endif

    @if(!empty($firstApproval))
        @include('emails.partials.alert-box', [
            'variant' => 'success',
            'title' => 'Your portal is ready',
            'message' => 'You can now sign in to manage students, complete future annual registrations, and use all school admin features.',
        ])
    @endif

    @include('emails.partials.button', [
        'url' => $dashboardUrl,
        'label' => 'Open school dashboard',
        'variant' => 'success',
    ])

    @if(!empty($firstApproval) && !empty($loginUrl))
        <p class="muted" style="color:#64748b;font-size:13px;margin:14px 0 0;">
            Sign in at: <a href="{{ $loginUrl }}" style="color:#0f3d7a;word-break:break-all;">{{ $loginUrl }}</a>
        </p>
    @endif
@endsection
