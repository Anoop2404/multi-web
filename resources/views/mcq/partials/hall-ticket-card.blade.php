@php
    $design = \App\Support\Mcq\McqHallTicketDesign::normalize($design ?? null);
    $sample = $sample ?? [];
    $headerTitle = $design['header_title'];
    $showSchoolAdmissionNo = $design['show_reg_no'];
    $showSchool = $design['show_school'];
    $footerNote = $design['footer_note'] ?? null;
    $primary = $design['primary_color'];
    $accent = $design['accent_color'];
    $layout = $design['layout'];
    $logoUrl = $logoUrl ?? null;
    $examTitle = $sample['exam_title'] ?? 'Sample Exam';
    $studentName = $sample['student_name'] ?? 'Sample Student';
    $studentRegNo = $sample['student_reg_no'] ?? 'ADM-001';
    $schoolName = $sample['school_name'] ?? 'Sample School';
    $examRegNo = $sample['hall_ticket_no'] ?? '100';
    $scheduledLabel = $sample['scheduled_at_label'] ?? 'TBA';
    $hallRoom = $sample['hall_room'] ?? null;
    $seatNo = $sample['seat_no'] ?? null;
    $hallInstructions = $sample['hall_instructions'] ?? null;
    $padding = $layout === 'compact' ? '12px' : '16px';
    $titleSize = $layout === 'compact' ? '12px' : '13px';
    $regSize = $layout === 'compact' ? '22px' : '28px';
@endphp
<div class="mcq-hall-ticket" style="border:2px solid {{ $primary }};padding:{{ $padding }};font-family:Arial,sans-serif;color:#111;max-width:720px;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:10px;">
        <div style="display:flex;gap:10px;align-items:flex-start;min-width:0;">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="" style="width:48px;height:48px;object-fit:contain;flex-shrink:0;">
            @endif
            <div style="min-width:0;">
                <p style="margin:0;font-size:{{ $titleSize }};font-weight:700;color:{{ $primary }};">{{ $examTitle }}</p>
                <p style="margin:2px 0 0;font-size:10px;color:#64748b;">{{ $headerTitle }}</p>
            </div>
        </div>
        <div style="flex-shrink:0;text-align:right;">
            <span style="font-size:9px;color:#64748b;display:block;">Reg. No.</span>
            <div style="font-size:{{ $regSize }};font-weight:900;color:{{ $accent }};line-height:1;">{{ $examRegNo }}</div>
        </div>
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:12px;">
        <tr><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;color:#64748b;width:38%;">Student name</td><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;"><strong>{{ $studentName }}</strong></td></tr>
        @if($showSchoolAdmissionNo && filled($studentRegNo))
            <tr><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;color:#64748b;">School admission no.</td><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;">{{ $studentRegNo }}</td></tr>
        @endif
        @if($showSchool && filled($schoolName))
            <tr><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;color:#64748b;">School</td><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;">{{ $schoolName }}</td></tr>
        @endif
        <tr><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;color:#64748b;">Date & time</td><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;">{{ $scheduledLabel }}</td></tr>
        @if($hallRoom)
            <tr><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;color:#64748b;">Hall / room</td><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;">{{ $hallRoom }}</td></tr>
        @endif
        @if($seatNo)
            <tr><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;color:#64748b;">Seat</td><td style="padding:4px 0;border-bottom:1px dotted #cbd5e1;">{{ $seatNo }}</td></tr>
        @endif
    </table>
    @if($hallInstructions)
        <p style="margin-top:8px;font-size:10px;color:#334155;"><strong>Instructions:</strong> {{ $hallInstructions }}</p>
    @endif
    @if($footerNote)
        <p style="margin-top:8px;font-size:10px;color:#334155;">{{ $footerNote }}</p>
    @endif
</div>
