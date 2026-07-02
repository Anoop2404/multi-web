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

    <div class="grid">
        @foreach($registrations as $r)
            @include('mcq.partials.hall-ticket-card', [
                'design' => $design,
                'logoUrl' => $logoUrl,
                'sample' => [
                    'exam_title' => $exam->title,
                    'student_name' => $r->student?->name ?? '—',
                    'student_reg_no' => $r->student?->reg_no,
                    'school_name' => $r->school?->name,
                    'hall_ticket_no' => $r->hall_ticket_no ?? '—',
                    'hall_room' => $r->hall_room,
                    'seat_no' => $r->seat_no,
                    'scheduled_at_label' => $exam->scheduled_at?->format('d M Y, h:i A') ?? 'TBA',
                    'hall_instructions' => $exam->hall_instructions,
                ],
            ])
        @endforeach
    </div>
</body>
</html>
