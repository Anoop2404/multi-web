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
    $exam = $registration->exam;
    $design = \App\Support\Mcq\McqHallTicketDesign::fromExam($exam);
    $logoUrl = \App\Support\Mcq\McqHallTicketDesign::logoUrl($school ?? $registration->school, $design);
    $participant = \App\Support\Mcq\McqHallTicketDesign::participantFields($registration);
    $sample = [
        'exam_title' => $exam->title,
        'participant_type' => $participant['type'],
        'participant_name' => $participant['name'],
        'secondary_label' => $participant['secondary_label'],
        'secondary_value' => $participant['secondary_value'],
        'tertiary_label' => $participant['tertiary_label'],
        'tertiary_value' => $participant['tertiary_value'],
        'photo_src' => $participant['photo'],
        'school_name' => ($school ?? $registration->school)?->name,
        'hall_ticket_no' => $registration->hall_ticket_no,
        'hall_room' => $registration->hall_room,
        'seat_no' => $registration->seat_no,
        'venue' => $exam->venue,
        'scheduled_at_label' => \App\Support\Mcq\McqHallTicketDesign::examTimingLabel($exam->scheduled_at, $exam->duration_minutes) ?? 'TBA',
        'report_time_label' => \App\Support\Mcq\McqHallTicketDesign::reportTimeLabel($exam->scheduled_at, $design),
        'gate_closure_label' => \App\Support\Mcq\McqHallTicketDesign::gateClosureLabel($exam->scheduled_at, $design),
        'hall_instructions' => $exam->hall_instructions,
        'qr_src' => app(\App\Services\Events\FestIdCardQrService::class)->dataUri("MCQHT|{$exam->id}|{$registration->id}|{$registration->hall_ticket_no}"),
    ];
@endphp
    @include('mcq.partials.hall-ticket-card', ['design' => $design, 'logoUrl' => $logoUrl, 'sample' => $sample])
    <p class="no-print" style="text-align:center;margin-top:16px;"><button onclick="window.print()">Print</button></p>
</body>
</html>
