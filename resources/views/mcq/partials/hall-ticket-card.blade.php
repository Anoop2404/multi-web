@php
    $design = \App\Support\Mcq\McqHallTicketDesign::normalize($design ?? null);
    $sample = $sample ?? [];
    $headerTitle = $design['header_title'];
    $showSchoolAdmissionNo = $design['show_reg_no'];
    $showSchool = $design['show_school'];
    $showPhoto = $design['show_photo'];
    $showQr = $design['show_qr'];
    $showSignature = $design['show_signature'];
    $footerNote = $design['footer_note'] ?? null;
    $primary = $design['primary_color'];
    $accent = $design['accent_color'];
    $layout = $design['layout'];
    $logoUrl = $logoUrl ?? null;
    $examTitle = $sample['exam_title'] ?? 'Sample Exam';
    $participantType = $sample['participant_type'] ?? 'student';
    $participantName = $sample['participant_name'] ?? $sample['student_name'] ?? 'Sample Student';
    $secondaryLabel = $sample['secondary_label'] ?? 'School admission no.';
    $secondaryValue = $sample['secondary_value'] ?? ($sample['student_reg_no'] ?? null);
    $tertiaryLabel = $sample['tertiary_label'] ?? null;
    $tertiaryValue = $sample['tertiary_value'] ?? null;
    $schoolName = $sample['school_name'] ?? 'Sample School';
    $examRegNo = $sample['hall_ticket_no'] ?? '100';
    $scheduledLabel = $sample['scheduled_at_label'] ?? 'TBA';
    $reportTimeLabel = $sample['report_time_label'] ?? null;
    $gateClosureLabel = $sample['gate_closure_label'] ?? null;
    $venue = $sample['venue'] ?? null;
    $hallRoom = $sample['hall_room'] ?? null;
    $seatNo = $sample['seat_no'] ?? null;
    $hallInstructions = $sample['hall_instructions'] ?? null;
    $photoSrc = $sample['photo_src'] ?? null;
    $qrSrc = $sample['qr_src'] ?? null;
    $padding = $layout === 'compact' ? '12px' : '16px';
    $titleSize = $layout === 'compact' ? '12px' : '13px';
    $regSize = $layout === 'compact' ? '22px' : '28px';
    $photoSize = $layout === 'compact' ? '56px' : '72px';
@endphp
<div class="mcq-hall-ticket" style="border:2px solid {{ $primary }};padding:{{ $padding }};font-family:Arial,sans-serif;color:#111;max-width:720px;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:10px;">
        <div style="display:flex;gap:10px;align-items:flex-start;min-width:0;">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="" style="width:48px;height:48px;object-fit:contain;flex-shrink:0;">
            @endif
            <div style="min-width:0;">
                <p style="margin:0;font-size:{{ $titleSize }};font-weight:700;color:{{ $primary }};">{{ $examTitle }}</p>
                <p style="margin:2px 0 0;font-size:11px;color:#64748b;">{{ $headerTitle }}</p>
                <p style="margin:2px 0 0;font-size:9.5px;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em;">{{ $participantType === 'teacher' ? 'Teacher candidate' : 'Student candidate' }}</p>
            </div>
        </div>
        <div style="flex-shrink:0;text-align:right;">
            <span style="font-size:10.5px;color:#64748b;display:block;">Reg. No.</span>
            <div style="font-size:{{ $regSize }};font-weight:900;color:{{ $accent }};line-height:1;">{{ $examRegNo }}</div>
        </div>
    </div>
    <div style="display:flex;gap:12px;align-items:flex-start;">
        @if($showPhoto)
            <div style="flex-shrink:0;width:{{ $photoSize }};">
                @if($photoSrc)
                    <img src="{{ $photoSrc }}" alt="" style="width:{{ $photoSize }};height:{{ $photoSize }};object-fit:cover;border:1px solid #cbd5e1;border-radius:4px;">
                @else
                    <div style="width:{{ $photoSize }};height:{{ $photoSize }};border:1px dashed #cbd5e1;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:9px;color:#94a3b8;text-align:center;">Photo</div>
                @endif
            </div>
        @endif
        <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
            <tr><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;color:#64748b;width:38%;">Name</td><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;"><strong>{{ $participantName }}</strong></td></tr>
            @if($showSchoolAdmissionNo && filled($secondaryValue))
                <tr><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;color:#64748b;">{{ $secondaryLabel }}</td><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;">{{ $secondaryValue }}</td></tr>
            @endif
            @if($tertiaryLabel && filled($tertiaryValue))
                <tr><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;color:#64748b;">{{ $tertiaryLabel }}</td><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;">{{ $tertiaryValue }}</td></tr>
            @endif
            @if($showSchool && filled($schoolName))
                <tr><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;color:#64748b;">School</td><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;">{{ $schoolName }}</td></tr>
            @endif
            <tr><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;color:#64748b;">Exam timing</td><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;">{{ $scheduledLabel }}</td></tr>
            @if($reportTimeLabel)
                <tr><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;color:#64748b;">Reporting time</td><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;font-weight:700;color:{{ $accent }};">{{ $reportTimeLabel }}</td></tr>
            @endif
            @if($gateClosureLabel)
                <tr><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;color:#64748b;">Gate closure</td><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;">{{ $gateClosureLabel }}</td></tr>
            @endif
            @if($venue)
                <tr><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;color:#64748b;">Test center</td><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;">{{ $venue }}</td></tr>
            @endif
            @if($hallRoom)
                <tr><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;color:#64748b;">Hall / room</td><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;">{{ $hallRoom }}</td></tr>
            @endif
            @if($seatNo)
                <tr><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;color:#64748b;">Seat</td><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;">{{ $seatNo }}</td></tr>
            @endif
        </table>
        @if($showQr && $qrSrc)
            <div style="flex-shrink:0;text-align:center;">
                <img src="{{ $qrSrc }}" alt="" style="width:{{ $photoSize }};height:{{ $photoSize }};">
                <p style="margin:2px 0 0;font-size:8px;color:#94a3b8;">Scan to verify</p>
            </div>
        @endif
    </div>
    @if($hallInstructions)
        <p style="margin-top:8px;font-size:11px;color:#334155;"><strong>Instructions:</strong> {{ $hallInstructions }}</p>
    @endif
    @if($showSignature)
        <div style="display:flex;justify-content:space-between;gap:16px;margin-top:18px;">
            <div style="flex:1;text-align:center;">
                <div style="border-top:1px solid #64748b;margin-top:22px;padding-top:3px;font-size:9.5px;color:#64748b;">Candidate signature</div>
            </div>
            <div style="flex:1;text-align:center;">
                <div style="border-top:1px solid #64748b;margin-top:22px;padding-top:3px;font-size:9.5px;color:#64748b;">Invigilator signature</div>
            </div>
        </div>
    @endif
    @if($footerNote)
        <p style="margin-top:8px;font-size:11px;color:#334155;">{{ $footerNote }}</p>
    @endif
</div>
