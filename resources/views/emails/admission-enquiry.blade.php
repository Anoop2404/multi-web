<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: Arial, sans-serif; font-size: 14px; color: #374151; margin: 0; padding: 0; background: #f9fafb; }
    .wrapper { max-width: 560px; margin: 24px auto; background: #fff; border-radius: 8px; border: 1px solid #e5e7eb; overflow: hidden; }
    .header { background: #4f46e5; color: #fff; padding: 20px 28px; }
    .header h1 { margin: 0; font-size: 18px; font-weight: 700; }
    .header p { margin: 4px 0 0; font-size: 13px; opacity: .8; }
    .body { padding: 24px 28px; }
    .row { display: flex; gap: 8px; margin-bottom: 8px; }
    .label { font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: .04em; min-width: 140px; padding-top: 2px; }
    .value { font-size: 14px; color: #111827; }
    .divider { border: none; border-top: 1px solid #e5e7eb; margin: 16px 0; }
    .footer { background: #f3f4f6; padding: 14px 28px; font-size: 12px; color: #9ca3af; }
</style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>New Admission Enquiry</h1>
        <p>{{ $school->name }}</p>
    </div>
    <div class="body">
        <div class="row"><span class="label">Student Name</span><span class="value">{{ $enquiry->student_name }}</span></div>
        <div class="row"><span class="label">Date of Birth</span><span class="value">{{ $enquiry->dob }}</span></div>
        <div class="row"><span class="label">Class Applying</span><span class="value">{{ $enquiry->class_applying }}</span></div>
        <div class="row"><span class="label">Academic Year</span><span class="value">{{ $enquiry->academic_year }}</span></div>
        <hr class="divider">
        <div class="row"><span class="label">Parent / Guardian</span><span class="value">{{ $enquiry->parent_name }}</span></div>
        <div class="row"><span class="label">Phone</span><span class="value">{{ $enquiry->phone }}</span></div>
        @if($enquiry->email)
        <div class="row"><span class="label">Email</span><span class="value">{{ $enquiry->email }}</span></div>
        @endif
        @if($enquiry->address)
        <div class="row"><span class="label">Address</span><span class="value">{{ $enquiry->address }}</span></div>
        @endif
        @if($enquiry->message)
        <hr class="divider">
        <div class="row"><span class="label">Message</span><span class="value">{{ $enquiry->message }}</span></div>
        @endif
    </div>
    <div class="footer">
        Submitted {{ $enquiry->created_at->format('d M Y, h:i A') }} · {{ $school->name }} Admission Portal
    </div>
</div>
</body>
</html>
