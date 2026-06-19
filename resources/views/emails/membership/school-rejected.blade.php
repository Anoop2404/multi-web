@extends('emails.layouts.sahodaya')

@section('content')
    <h2 style="margin:0 0 12px;font-size:18px;color:#041525;font-weight:700;">Application not approved</h2>

    <p>Hello,</p>

    <p>
        We regret to inform you that the membership application for
        <strong>{{ $school->name }}</strong> was not approved by <strong>{{ $sahodayaName }}</strong>.
    </p>

    @include('emails.partials.alert-box', [
        'variant' => 'danger',
        'title' => 'Reason provided',
        'message' => e($reason),
    ])

    <p class="muted" style="color:#64748b;font-size:13px;">
        @if(!empty($contactEmail))
            If you believe this was a mistake or need clarification, please contact the Sahodaya office at
            <a href="mailto:{{ $contactEmail }}" style="color:#0f3d7a;">{{ $contactEmail }}</a>.
        @else
            If you believe this was a mistake or need clarification, please contact the Sahodaya office.
        @endif
    </p>
@endsection
