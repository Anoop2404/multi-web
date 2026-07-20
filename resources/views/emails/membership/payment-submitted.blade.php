@extends('emails.layouts.sahodaya')

@section('content')
    <h2 style="margin:0 0 12px;font-size:18px;color:#041525;font-weight:700;">{{ $title ?? 'Payment proof submitted' }}</h2>

    <p>Hello,</p>

    <p>{{ $body ?? $school->name.' uploaded membership payment proof for '.$academicYear.'. Please review and verify the payment.' }}</p>

    @include('emails.partials.detail-table', ['rows' => array_filter([
        'School' => $school->name,
        'Academic year' => $academicYear,
        'Amount' => ($amount ?? null) !== null ? '₹'.number_format((float) $amount, 2) : null,
        'Payment method' => $paymentMethod ?? null,
        'Transaction ref.' => $transactionRef ?? null,
    ])])

    @include('emails.partials.button', [
        'url' => $paymentsUrl,
        'label' => 'Review payment proof',
    ])
@endsection
