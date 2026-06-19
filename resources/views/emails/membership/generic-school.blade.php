@extends('emails.layouts.sahodaya')

@section('content')
    <h2 style="margin:0 0 12px;font-size:18px;color:#041525;font-weight:700;">{{ $title }}</h2>

    <p>Hello,</p>

    <p>{{ $body }}</p>

    @if(!empty($details))
        @include('emails.partials.detail-table', ['rows' => $details])
    @endif

    @if(!empty($reason))
        @include('emails.partials.alert-box', [
            'variant' => $alertVariant ?? 'warning',
            'title' => $reasonTitle ?? 'Note',
            'message' => e($reason),
        ])
    @endif

    @if(!empty($actionUrl) && !empty($actionLabel))
        @include('emails.partials.button', [
            'url' => $actionUrl,
            'label' => $actionLabel,
            'variant' => $actionVariant ?? 'primary',
        ])
    @endif
@endsection
