@extends('emails.layouts.sahodaya')

@section('content')
    <h2 style="margin:0 0 16px;font-size:18px;color:#041525;font-weight:700;">{{ $title ?? 'New Admission Enquiry' }}</h2>
    @if(!empty($body))
        <p>{{ $body }}</p>
    @endif

    @include('emails.partials.detail-table', ['rows' => array_filter([
        'Student name' => $enquiry->student_name,
        'Date of birth' => $enquiry->dob,
        'Class applying' => $enquiry->class_applying,
        'Academic year' => $enquiry->academic_year,
        'Parent / Guardian' => $enquiry->parent_name,
        'Phone' => $enquiry->phone,
        'Email' => $enquiry->email,
        'Address' => $enquiry->address,
        'Message' => $enquiry->message,
    ])])

    <p class="muted" style="color:#64748b;font-size:13px;margin:0;">
        Submitted {{ $enquiry->created_at->format('d M Y, h:i A') }} · {{ $school->name }} Admission Portal
    </p>
@endsection
