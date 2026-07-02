<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Hall Ticket {{ $registration->hall_ticket_no }}</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 24px; color: #111; background: #f8fafc; }
        @media print { body { padding: 0; background: #fff; } .no-print { display: none; } }
    </style>
</head>
<body>
@php
    $design = \App\Support\Mcq\McqHallTicketDesign::fromExam($registration->exam);
    $logoUrl = \App\Support\Mcq\McqHallTicketDesign::logoUrl($school ?? $registration->school, $design);
    $sample = [
        'exam_title' => $registration->exam->title,
        'student_name' => $registration->student->name,
        'student_reg_no' => $registration->student->reg_no,
        'school_name' => ($school ?? $registration->school)?->name,
        'hall_ticket_no' => $registration->hall_ticket_no,
        'hall_room' => $registration->hall_room,
        'seat_no' => $registration->seat_no,
        'scheduled_at_label' => $registration->exam->scheduled_at?->format('d M Y, h:i A') ?? 'TBA',
        'hall_instructions' => $registration->exam->hall_instructions,
    ];
@endphp
    @include('mcq.partials.hall-ticket-card', ['design' => $design, 'logoUrl' => $logoUrl, 'sample' => $sample])
    <p class="no-print" style="text-align:center;margin-top:16px;"><button onclick="window.print()">Print</button></p>
</body>
</html>
