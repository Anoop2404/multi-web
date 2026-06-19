@extends('emails.layouts.sahodaya')

@section('content')
    <h2 style="margin:0 0 12px;font-size:18px;color:#041525;font-weight:700;">New school application</h2>

    <p>Hello,</p>

    <p>
        A new school has submitted a membership application and is awaiting your review on the Sahodaya admin panel.
    </p>

    @include('emails.partials.detail-table', ['rows' => array_merge(
        $applicationDetails ?? [],
        ['Status' => 'Pending review'],
    )])

    @if(!empty($reviewUrl))
        @include('emails.partials.button', [
            'url' => $reviewUrl,
            'label' => 'Review application',
        ])
    @endif
@endsection
