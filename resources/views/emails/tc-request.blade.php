@extends('emails.layouts.sahodaya')

@section('content')
    <h2 style="margin:0 0 16px;font-size:18px;color:#041525;font-weight:700;">Transfer Certificate Request</h2>

    @include('emails.partials.detail-table', ['rows' => array_filter([
        'Student name' => $tcRequest->student_name,
        'Admission no.' => $tcRequest->admission_number,
        'Class / Division' => $tcRequest->class.($tcRequest->division ? ' – '.$tcRequest->division : ''),
        'Date of birth' => $tcRequest->dob,
        'Parent / Guardian' => $tcRequest->parent_name,
        'Phone' => $tcRequest->phone,
        'Email' => $tcRequest->email,
        'Reason for TC' => $tcRequest->reason,
    ])])

    <p class="muted" style="color:#64748b;font-size:13px;margin:0;">
        Submitted {{ $tcRequest->created_at->format('d M Y, h:i A') }} · {{ $school->name }} TC Portal
    </p>
@endsection
