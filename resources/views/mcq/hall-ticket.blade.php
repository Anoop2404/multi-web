<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Hall Ticket {{ $registration->hall_ticket_no }} — {{ $registration->exam->title }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; padding: 20px; color: #1e293b; background: #f8fafc; }
        .wrapper { max-width: 195mm; margin: 0 auto; }
        @media print {
            body { padding: 0; background: #fff; }
            .no-print { display: none !important; }
            .wrapper { max-width: 100%; margin: 0; }
        }
    </style>
</head>
<body>
@php
    $exam = $registration->exam;
    $tenant = \App\Models\Tenant::find($exam->tenant_id);
    $schoolModel = $school ?? $registration->school;
    $design = \App\Support\Mcq\McqHallTicketDesign::fromExam($exam);
    $logoUrl = \App\Support\Mcq\McqHallTicketDesign::logoUrl($schoolModel, $design);
    $boardLogoUrl = \App\Support\Mcq\McqHallTicketDesign::boardLogoUrl($tenant, $design);
    $participant = \App\Support\Mcq\McqHallTicketDesign::participantFields($registration);
    
    $student = $registration->student;
    $scheduledAt = $exam->scheduled_at;
    $timingLabel = \App\Support\Mcq\McqHallTicketDesign::examTimingLabel($scheduledAt, $exam->duration_minutes) ?? 'TBA';
    $reportLabel = \App\Support\Mcq\McqHallTicketDesign::reportTimeLabel($scheduledAt, $design) ?? 'TBA';
    $gateLabel = \App\Support\Mcq\McqHallTicketDesign::gateClosureLabel($scheduledAt, $design) ?? 'At Exam Start';
    $classBucket = $participant['class_name'] ?? null;
    $totalQuestions = $exam->totalQuestionsForClass($classBucket);

    $sample = [
        'exam_title'         => $exam->title,
        'paper_name'         => $exam->title,
        'exam_code'          => $exam->code ?: 'MCQ-' . $exam->id,
        'participant_type'   => $participant['type'],
        'participant_name'   => $participant['name'],
        'secondary_label'    => $participant['secondary_label'],
        'secondary_value'    => $participant['secondary_value'],
        'tertiary_label'     => $participant['tertiary_label'],
        'tertiary_value'     => $participant['tertiary_value'],
        'class_name'         => $participant['class_name'] ?? 'Class',
        'section_name'       => $participant['section_name'] ?? null,
        'father_name'        => $participant['father_name'] ?? null,
        'mother_name'        => $participant['mother_name'] ?? null,
        'academic_year'      => $participant['academic_year'] ?? null,
        'school_name'        => $schoolModel?->name,
        'hall_ticket_no'     => $registration->hall_ticket_no,
        'hall_room'          => $registration->hall_room,
        'seat_no'            => $registration->seat_no,
        'venue'              => $exam->venue,
        'center_code'        => 'CEN-' . str_pad((string) ($exam->id * 17 + 101), 4, '0', STR_PAD_LEFT),
        'center_name'        => $exam->venue ?: 'Designated Examination Centre',
        'center_address'     => $exam->venue,
        'exam_date'          => $scheduledAt ? $scheduledAt->format('l, d F Y') : 'TBA',
        'exam_time'          => $timingLabel,
        'scheduled_at_label' => $timingLabel,
        'report_time_label'  => $reportLabel,
        'gate_closure_label' => $gateLabel,
        'duration_minutes'   => $exam->duration_minutes ?: 60,
        'total_questions'    => $totalQuestions,
        'max_marks'          => $totalQuestions,
        'hall_instructions'  => $exam->hall_instructions,
        'generated_at'       => now()->format('d-m-Y H:i:s'),
        'security_ref'       => 'SEC-' . strtoupper(substr(md5("{$exam->id}-{$registration->id}-{$registration->hall_ticket_no}"), 0, 8)),
        'photo_src'          => $participant['photo'],
        'qr_src'             => app(\App\Services\Events\FestIdCardQrService::class)->dataUri("MCQHT|{$exam->id}|{$registration->id}|{$registration->hall_ticket_no}"),
    ];
@endphp
<div class="wrapper">
    <p class="no-print" style="text-align:center;margin-bottom:16px;">
        <button onclick="window.print()" style="background:#1e3a8a;color:#fff;font-weight:bold;border:none;padding:8px 24px;border-radius:6px;cursor:pointer;font-size:13px;">
            🖨️ Print Hall Ticket
        </button>
    </p>

    @include('mcq.partials.hall-ticket-card', [
        'design'       => $design,
        'logoUrl'      => $logoUrl,
        'boardLogoUrl' => $boardLogoUrl,
        'sample'       => $sample,
    ])
</div>
</body>
</html>
