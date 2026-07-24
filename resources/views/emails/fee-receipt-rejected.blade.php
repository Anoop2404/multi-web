@extends('emails.layouts.sahodaya')

@section('content')
    <h2 style="margin:0 0 12px;font-size:18px;color:#041525;font-weight:700;">{{ $title }}</h2>

    <p>Hello,</p>

    <p>{{ $body }}</p>

    @include('emails.partials.detail-table', ['rows' => array_filter([
        'Fee type'    => $feeTypeLabel,
        'Description' => $contextTitle,
        'Status'      => 'Rejected — re-upload required',
        'Reason'      => $reason ?? null,
    ])])

    @include('emails.partials.alert-box', [
        'variant' => 'warning',
        'title'   => 'Action required',
        'message' => 'Please re-upload your payment proof to resume the approval process.',
    ])

    @include('emails.partials.button', [
        'url'     => $paymentsUrl ?? $dashboardUrl,
        'label'   => 'Re-upload proof',
        'variant' => 'primary',
    ])
@endsection
