<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Hall Tickets — {{ $exam->title }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: #fff; padding: 16px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .mcq-hall-ticket { break-inside: avoid; page-break-inside: avoid; }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
            .grid { gap: 8px; }
        }
        @page { margin: 1cm; }
    </style>
</head>
<body>
@php
    $design = \App\Support\Mcq\McqHallTicketDesign::fromExam($exam);
    $logoUrl = \App\Support\Mcq\McqHallTicketDesign::logoUrl(\App\Models\Tenant::find($exam->tenant_id), $design);
@endphp
    <p class="no-print" style="margin-bottom:16px;font-size:13px;font-family:sans-serif;">
        <button onclick="window.print()" style="padding:6px 18px;background:{{ $design['primary_color'] }};color:#fff;border:none;border-radius:6px;cursor:pointer;margin-right:12px;">
            Print All ({{ count($registrations) }} tickets)
        </button>
        <span style="color:#64748b;">{{ $exam->title }}</span>
    </p>

    @php
        $examTimingLabel = \App\Support\Mcq\McqHallTicketDesign::examTimingLabel($exam->scheduled_at, $exam->duration_minutes) ?? 'TBA';
        $reportTimeLabel = \App\Support\Mcq\McqHallTicketDesign::reportTimeLabel($exam->scheduled_at, $design);
        $gateClosureLabel = \App\Support\Mcq\McqHallTicketDesign::gateClosureLabel($exam->scheduled_at, $design);
        $qrService = app(\App\Services\Events\FestIdCardQrService::class);
    @endphp
    <div class="grid">
        @foreach($registrations as $r)
            @php($participant = \App\Support\Mcq\McqHallTicketDesign::participantFields($r))
            @include('mcq.partials.hall-ticket-card', [
                'design' => $design,
                'logoUrl' => $logoUrl,
                'sample' => [
                    'exam_title' => $exam->title,
                    'participant_type' => $participant['type'],
                    'participant_name' => $participant['name'],
                    'secondary_label' => $participant['secondary_label'],
                    'secondary_value' => $participant['secondary_value'],
                    'tertiary_label' => $participant['tertiary_label'],
                    'tertiary_value' => $participant['tertiary_value'],
                    'photo_src' => $participant['photo'],
                    'school_name' => $r->school?->name,
                    'hall_ticket_no' => $r->hall_ticket_no ?? '—',
                    'hall_room' => $r->hall_room,
                    'seat_no' => $r->seat_no,
                    'venue' => $exam->venue,
                    'scheduled_at_label' => $examTimingLabel,
                    'report_time_label' => $reportTimeLabel,
                    'gate_closure_label' => $gateClosureLabel,
                    'hall_instructions' => $exam->hall_instructions,
                    'qr_src' => $r->hall_ticket_no ? $qrService->dataUri("MCQHT|{$exam->id}|{$r->id}|{$r->hall_ticket_no}") : null,
                ],
            ])
        @endforeach
    </div>
</body>
</html>
