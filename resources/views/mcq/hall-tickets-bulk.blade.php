@php
    // Neither caller (Sahodaya print-all, school hall tickets PDF) passes a design; use the exam's own.
    $design = $design ?? \App\Support\Mcq\McqHallTicketDesign::fromExam($exam);
    $isArtwork = in_array($design['layout'] ?? '', ['stse_three_up','custom_background'], true);
    $die = \App\Support\Mcq\McqHallTicketDesign::printGeometry($design);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Hall Tickets — {{ $exam->title }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; background: #fff; padding: 16px; }
        @php $isCompact = ($design['layout'] ?? 'standard') === 'compact'; @endphp
        @if($isArtwork)
            .tickets-wrapper { display:grid; grid-template-columns:repeat({{ $die['columns'] }},{{ $die['width'] }}mm); gap:{{ $die['gap'] }}mm; width:fit-content; margin:0 auto; }
            .ticket-item { width:{{ $die['width'] }}mm; height:{{ $die['height'] }}mm; break-inside:avoid; page-break-inside:avoid; }
            .ticket-page { break-after:page; page-break-after:always; margin-bottom:10mm; }
            .ticket-page:last-child { break-after:auto; page-break-after:auto; margin-bottom:0; }
        @elseif($isCompact)
            .tickets-wrapper { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
            .ticket-item { break-inside: avoid; page-break-inside: avoid; }
        @else
            .tickets-wrapper { display: block; max-width: 195mm; margin: 0 auto; }
            .ticket-item { break-inside: avoid; page-break-inside: avoid; page-break-after: always; margin-bottom: 20px; }
        @endif
        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
            .ticket-page { margin-bottom:0; }
            @if($isCompact)
                .tickets-wrapper { gap: 8px; }
            @else
                .tickets-wrapper { max-width: 100%; margin: 0; }
                .ticket-item { margin-bottom: 0; }
            @endif
        }
        @page { size: A4 {{ $isArtwork ? ($design['page_orientation'] ?? 'landscape') : 'portrait' }}; margin: {{ $isArtwork ? ($die['margin'].'mm') : '8mm 10mm' }}; }
    </style>
</head>
<body>
@php
    $tenant = \App\Models\Tenant::find($exam->tenant_id);
    $boardLogoUrl = \App\Support\Mcq\McqHallTicketDesign::boardLogoUrl($tenant, $design);
    $timingLabel = \App\Support\Mcq\McqHallTicketDesign::examTimingLabel($exam->scheduled_at, $exam->duration_minutes) ?? 'TBA';
    $reportLabel = \App\Support\Mcq\McqHallTicketDesign::reportTimeLabel($exam->scheduled_at, $design) ?? 'TBA';
    $gateLabel = \App\Support\Mcq\McqHallTicketDesign::gateClosureLabel($exam->scheduled_at, $design) ?? 'At Exam Start';
    $qrService = app(\App\Services\Events\FestIdCardQrService::class);
@endphp
    <p class="no-print" style="margin-bottom:16px;font-size:13px;font-family:sans-serif;display:flex;align-items:center;gap:12px;">
        <button onclick="window.print()" style="padding:8px 20px;background:{{ $design['primary_color'] }};color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:bold;">
            🖨️ Print All ({{ count($registrations) }} tickets)
        </button>
        <span style="color:#64748b;font-weight:600;">{{ $exam->title }} ({{ $isArtwork ? ($die['per_page'] . ' cards per A4') : ($isCompact ? 'Compact 2-Up' : 'Standard Full A4') }})</span>
    </p>

    @foreach(collect($registrations)->chunk($isArtwork ? $die['per_page'] : max(1, count($registrations))) as $pageRegistrations)
    <div class="ticket-page"><div class="tickets-wrapper">
        @foreach($pageRegistrations as $r)
            @php
                $participant = \App\Support\Mcq\McqHallTicketDesign::participantFields($r);
                $examCentre = \App\Support\Mcq\McqHallTicketDesign::centreForSchool($exam, $r->school);
                $classBucket = $participant['class_name'] ?? null;
                $totalQuestions = $exam->totalQuestionsForClass($classBucket);
                $sampleData = [
                    'exam_title'         => $exam->title,
                    'paper_name'         => $exam->title,
                    'exam_code'          => $exam->code ?: 'MCQ-' . $exam->id,
                    'participant_type'   => $participant['type'],
                    'participant_name'   => $participant['name'],
                    'secondary_label'    => $participant['secondary_label'],
                    'secondary_value'    => $participant['secondary_value'],
                    'admission_no' => $participant['secondary_value'],
                    'tertiary_label'     => $participant['tertiary_label'],
                    'tertiary_value'     => $participant['tertiary_value'],
                    'class_name'         => $participant['class_name'] ?? 'Class',
                    'section_name'       => $participant['section_name'] ?? null,
                    'father_name'        => $participant['father_name'] ?? null,
                    'mother_name'        => $participant['mother_name'] ?? null,
                    'academic_year'      => $participant['academic_year'] ?? null,
                    'school_name'        => $r->school?->name,
                    'hall_ticket_no'     => $r->hall_ticket_no ?? '—',
                    'exam_passcode'      => $exam->isOnlineDelivery() && $r->hall_ticket_no
                        ? \App\Services\Mcq\McqExamPasscodeService::format(app(\App\Services\Mcq\McqExamPasscodeService::class)->ensure($r))
                        : null,
                    'hall_room'          => $r->hall_room,
                    'seat_no'            => $r->seat_no,
                    'venue'              => $examCentre,
                    'center_code'        => \App\Support\Mcq\McqHallTicketDesign::centreCodeForSchool($exam, $r->school) ?? '',
                    'center_name'        => $examCentre ?: 'Designated Examination Centre',
                    'center_address'     => $examCentre,
                    'exam_date'          => $exam->scheduled_at ? $exam->scheduled_at->format('l, d F Y') : 'TBA',
                    'exam_time'          => $timingLabel,
                    'scheduled_at_label' => $timingLabel,
                    'report_time_label'  => $reportLabel,
                    'gate_closure_label' => $gateLabel,
                    'duration_minutes'   => $exam->duration_minutes ?: 60,
                    'total_questions'    => $totalQuestions,
                    'max_marks'          => $totalQuestions,
                    'hall_instructions'  => $exam->hall_instructions,
                    'generated_at'       => now()->format('d-m-Y H:i:s'),
                    'security_ref'       => 'SEC-' . strtoupper(substr(md5("{$exam->id}-{$r->id}-{$r->hall_ticket_no}"), 0, 8)),
                    'photo_src'          => $participant['photo'],
                    'principal_signature_src' => \App\Support\TenantStorage::logoUrl($r->school, $r->school?->getSetting('principal_signature')),
                    'qr_src'             => $r->hall_ticket_no ? $qrService->dataUri("MCQHT|{$exam->id}|{$r->id}|{$r->hall_ticket_no}") : null,
                ];
            @endphp
            <div class="ticket-item">
                @include('mcq.partials.hall-ticket-card', [
                    'design'       => $design,
                    'logoUrl'      => $logoUrl ?? \App\Support\Mcq\McqHallTicketDesign::logoUrl($r->school, $design),
                    'boardLogoUrl' => $boardLogoUrl,
                    'sample'       => $sampleData,
                ])
            </div>
        @endforeach
    </div></div>
    @endforeach
</body>
</html>
