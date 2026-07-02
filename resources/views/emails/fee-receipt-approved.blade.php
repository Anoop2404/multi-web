@extends('emails.layouts.sahodaya')

@section('content')
    <h2 style="margin:0 0 12px;font-size:18px;color:#041525;font-weight:700;">{{ $title }}</h2>

    <p>Hello,</p>

    <p>{{ $body }}</p>

    @include('emails.partials.detail-table', ['rows' => array_filter([
        'Fee type'         => $feeTypeLabel,
        'Description'      => $contextTitle,
        'Receipt No.'      => $receiptNo ?? null,
        'Amount'           => $amountFormatted ?? null,
        'Payment date'     => $paymentDate ?? null,
        'Transaction ref.' => $transactionRef ?? null,
        'Status'           => 'Approved',
    ])])

    @if(!empty($receiptNo))
        @include('emails.partials.alert-box', [
            'variant' => 'info',
            'title' => 'Official receipt attached',
            'message' => "Receipt No. {$receiptNo} is attached as an HTML file. Open it in your browser and print for your records.",
        ])
    @endif

    @include('emails.partials.button', [
        'url' => $paymentsUrl ?? $dashboardUrl,
        'label' => 'View payments & receipts',
        'variant' => 'success',
    ])
@endsection
